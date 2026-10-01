<?php

namespace App\Http\Controllers\CardManagement;

use App\Models\CardManagement\Card;
use App\Services\CardManagement\CardManagementError;
use App\Services\CardManagement\KeyServiceClient;
use App\Services\CardManagement\OperationService;
use App\Services\CardManagement\ResponseEnvelope;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Issuance controller.
 * Implements card issuance/personalization per PRODUCTION_BACKEND_API_REQUIREMENTS §5.6.
 *
 * Full CRUD implementation with optimistic locking, key envelope delivery,
 * checkpoints, customer management, and lifecycle events.
 */
final class IssuanceController extends BaseCardManagementController
{
    private OperationService $operations;

    public function __construct()
    {
        $this->operations = new OperationService(
            'cm_card_issuance_operations',
            'cm_card_issuance_checkpoints',
            'cm_card_issuance_key_envelopes',
        );
    }

    public function getConfiguration(Request $request)
    {
        return ResponseEnvelope::success([
            'ready' => (bool) config('card_management.issuance.lab_personalization_writes_enabled', true),
            'labPersonalizationWritesEnabled' => (bool) config('card_management.issuance.lab_personalization_writes_enabled', true),
            'reason' => null,
            'profileSnapshot' => [
                'cityCode' => '0000000000000001',
                'issuerCode' => '0000000000000001',
                'cardDataVersion' => 1,
                'cardEnableFlag' => true,
                'applicationIdentifier' => 'A000000001',
                'applicationVersion' => 1,
                'applicationType' => 1,
                'applicationEnableFlag' => true,
            ],
        ]);
    }

    public function createCustomer(Request $request)
    {
        $validated = $request->validate([
            'customerNumber' => 'required|string|max:32',
            'customerType' => 'nullable|string|in:NAMED,ANONYMOUS',
            'fullName' => 'nullable|string|max:160',
            'phone' => 'nullable|string|max:40',
            'email' => 'nullable|email|max:254',
            'externalReference' => 'nullable|string|max:100',
        ]);

        $id = Str::uuid()->toString();

        DB::table('cm_customers')->insert([
            'id' => $id,
            'customer_number' => $validated['customerNumber'],
            'customer_type' => $validated['customerType'] ?? 'NAMED',
            'full_name' => $validated['fullName'] ?? null,
            'phone' => $validated['phone'] ?? null,
            'email' => $validated['email'] ?? null,
            'external_reference' => $validated['externalReference'] ?? null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $customer = DB::table('cm_customers')->where('id', $id)->first();

        return ResponseEnvelope::success([
            'customer' => [
                'id' => $customer->id,
                'customerNumber' => $customer->customer_number,
                'customerType' => $customer->customer_type,
                'fullName' => $customer->full_name,
                'phone' => $customer->phone,
                'email' => $customer->email,
            ],
        ], 201);
    }

    public function searchCustomers(Request $request)
    {
        $query = $request->input('q', '');
        $type = $request->input('type');

        $dbQuery = DB::table('cm_customers');
        if ($query) {
            $dbQuery->where(function ($q) use ($query) {
                $q->where('customer_number', 'like', "%{$query}%")
                  ->orWhere('full_name', 'like', "%{$query}%")
                  ->orWhere('phone', 'like', "%{$query}%")
                  ->orWhere('email', 'like', "%{$query}%");
            });
        }
        if ($type) {
            $dbQuery->where('customer_type', $type);
        }

        $customers = $dbQuery->limit(50)->get();

        return ResponseEnvelope::success([
            'customers' => $customers->map(fn ($c) => [
                'id' => $c->id,
                'customerNumber' => $c->customer_number,
                'customerType' => $c->customer_type,
                'fullName' => $c->full_name,
                'phone' => $c->phone,
                'email' => $c->email,
            ])->toArray(),
        ]);
    }

    public function createIssuance(Request $request, string $uid)
    {
        $validated = $request->validate([
            'idempotencyKey' => 'required|string|max:128',
            'customerId' => 'nullable|string',
            'issuanceMode' => 'required|string|in:NEW,REISSUE',
            'cardCategory' => 'required|string|max:32',
            'cardTypeCode' => 'required|string|size:4',
            'applicationType' => 'required|string|size:2',
            'depositMinorUnits' => 'nullable|integer|min:0',
            'enableDate' => 'required|date',
            'expiryDate' => 'required|date|after:enableDate',
        ]);

        $card = Card::where('uid', $uid)->first();
        if (!$card) {
            throw new CardManagementError('CARD_NOT_REGISTERED', 404, 'Card not found.');
        }

        $operation = $this->operations->create([
            'card_id' => $card->id,
            'customer_id' => $validated['customerId'] ?? null,
            'idempotency_key' => $validated['idempotencyKey'],
            'expected_uid' => $uid,
            'card_number' => $card->card_number,
            'workstation_id' => $this->workstationId($request),
            'issuance_mode' => $validated['issuanceMode'],
            'card_category' => $validated['cardCategory'],
            'card_type_code' => $validated['cardTypeCode'],
            'application_type' => $validated['applicationType'],
            'deposit_minor_units' => $validated['depositMinorUnits'] ?? 0,
            'enable_date' => $validated['enableDate'],
            'expiry_date' => $validated['expiryDate'],
            'key_profile_version' => $card->key_profile_version ?? 'hitee-lab-v1',
        ]);

        return ResponseEnvelope::success(
            ['operation' => $this->operations->toPublicArray($operation)],
            201
        );
    }

    public function getActive(string $uid)
    {
        $card = Card::where('uid', $uid)->first();
        if (!$card) {
            return ResponseEnvelope::success(['card' => null, 'operation' => null]);
        }

        $operation = $this->operations->findActiveByCard($card->id);
        return ResponseEnvelope::success([
            'card' => ['uid' => $card->uid, 'cardNumber' => $card->card_number],
            'operation' => $operation ? $this->operations->toPublicArray($operation) : null,
        ]);
    }

    public function getHistory(Request $request, string $uid)
    {
        $card = Card::where('uid', $uid)->first();
        if (!$card) {
            return ResponseEnvelope::success(['card' => null, 'operations' => []]);
        }

        $operations = $this->operations->findByCard($card->id);
        return ResponseEnvelope::success([
            'card' => ['uid' => $card->uid, 'cardNumber' => $card->card_number],
            'operations' => array_map(fn ($op) => $this->operations->toPublicArray($op), $operations),
        ]);
    }

    public function getIssuanceDetails(string $uid)
    {
        $card = Card::where('uid', $uid)->first();
        if (!$card) {
            throw new CardManagementError('CARD_NOT_REGISTERED', 404, 'Card not found.');
        }

        $operation = DB::table('cm_card_issuance_operations')
            ->where('card_id', $card->id)
            ->orderBy('created_at', 'desc')
            ->first();

        if (!$operation) {
            // Return empty state — card is registered but not yet issued
            return ResponseEnvelope::success([
                'card' => ['uid' => $card->uid, 'cardNumber' => $card->card_number],
                'issuance' => null,
                'customer' => null,
            ]);
        }

        $customer = null;
        if ($operation->customer_id) {
            $customerRow = DB::table('cm_customers')->where('id', $operation->customer_id)->first();
            if ($customerRow) {
                $customer = [
                    'id' => $customerRow->id,
                    'customerNumber' => $customerRow->customer_number,
                    'fullName' => $customerRow->full_name,
                    'phone' => $customerRow->phone ?? null,
                    'email' => $customerRow->email ?? null,
                    'externalReference' => $customerRow->external_reference ?? null,
                ];
            }
        }

        return ResponseEnvelope::success([
            'card' => ['uid' => $card->uid, 'cardNumber' => $card->card_number],
            'issuance' => $this->operations->toPublicArray($operation),
            'customer' => $customer,
        ]);
    }

    public function prepareKeys(Request $request, string $operationId)
    {
        $operation = $this->operations->findOrFail($operationId);
        $this->assertVersion((int) $request->input('expectedVersion'), $operation->lock_version);

        if ($operation->status !== 'KEYS_PENDING') {
            throw new CardManagementError('INVALID_STATE_TRANSITION', 409, 'Operation must be in KEYS_PENDING.');
        }

        $recipientKeyId = $request->input('recipientKeyId');
        $recipientPublicKey = $request->input('recipientPublicKey');
        if (!$recipientKeyId || !$recipientPublicKey) {
            throw new CardManagementError('AMOUNT_INVALID', 400, 'recipientKeyId and recipientPublicKey are required.');
        }

        $card = Card::where('id', $operation->card_id)->firstOrFail();
        $fingerprint = hash('sha256', $recipientPublicKey);

        $keyService = KeyServiceClient::make();
        $result = $keyService->prepareKeyEnvelope([
            'requestId' => $this->requestId($request),
            'operationId' => $operation->id,
            'cardId' => $card->id,
            'expectedUid' => $operation->expected_uid,
            'cardNumber' => $card->card_number,
            'profileId' => $operation->key_profile_version,
            'workstationId' => $operation->workstation_id,
            'cityCode' => config('card_management.card_profile.lab_profile.city_code'),
            'issuerCode' => config('card_management.card_profile.lab_profile.issuer_code'),
            'recipient' => ['keyId' => $recipientKeyId, 'publicKey' => $recipientPublicKey],
        ], $this->requestId($request));

        DB::table('cm_card_issuance_key_envelopes')->insert([
            'operation_id' => $operation->id,
            'key_service_request_id' => Str::uuid()->toString(),
            'recipient_key_id' => $recipientKeyId,
            'recipient_fingerprint' => strtoupper($fingerprint),
            'envelope_version' => $result['envelope']['version'] ?? 1,
            'envelope' => json_encode($result['envelope']),
            'expires_at' => $result['expiresAt'],
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $updated = $this->operations->updateStatus($operation->id, 'KEYS_PREPARED', $operation->lock_version, 'KEYS_PREPARED');

        return ResponseEnvelope::success(['operation' => $this->operations->toPublicArray($updated)]);
    }

    public function authorize(Request $request, string $operationId)
    {
        $operation = $this->operations->findOrFail($operationId);
        $this->assertVersion((int) $request->input('expectedVersion'), $operation->lock_version);

        if ($operation->status !== 'KEYS_PREPARED') {
            throw new CardManagementError('INVALID_STATE_TRANSITION', 409, 'Operation must be in KEYS_PREPARED.');
        }

        $phrase = $request->input('confirmationPhrase');
        if (!$phrase) {
            throw new CardManagementError('INVALID_CONFIRMATION_PHRASE', 403, 'confirmationPhrase is required.');
        }

        DB::table('cm_card_issuance_operations')->where('id', $operation->id)->update([
            'status' => 'AUTHORIZED',
            'expected_confirmation_sha256' => hash('sha256', $phrase),
            'physical_write_authorized_at' => now(),
            'lock_version' => $operation->lock_version + 1,
            'updated_at' => now(),
        ]);

        return ResponseEnvelope::success([
            'operation' => $this->operations->toPublicArray($this->operations->findOrFail($operation->id)),
        ]);
    }

    public function getEnvelope(string $operationId)
    {
        $operation = $this->operations->findOrFail($operationId);

        $envelopeRow = DB::table('cm_card_issuance_key_envelopes')
            ->where('operation_id', $operation->id)
            ->first();

        if (!$envelopeRow) {
            throw new CardManagementError('KEY_ENVELOPE_NOT_READY', 409, 'Key envelope not yet prepared.');
        }

        if (now()->gt($envelopeRow->expires_at)) {
            throw new CardManagementError('KEY_ENVELOPE_EXPIRED', 410, 'Key envelope TTL exceeded.');
        }

        return ResponseEnvelope::success([
            'keyEnvelope' => [
                'operationId' => $operation->id,
                'envelope' => json_decode($envelopeRow->envelope, true),
                'expiresAt' => \Carbon\Carbon::parse($envelopeRow->expires_at)->toIso8601ZuluString(),
            ],
            'operation' => $this->operations->toPublicArray($operation),
        ]);
    }

    public function acknowledgeEnvelope(Request $request, string $operationId)
    {
        $operation = $this->operations->findOrFail($operationId);

        DB::table('cm_card_issuance_key_envelopes')
            ->where('operation_id', $operation->id)
            ->whereNull('acknowledged_at')
            ->update(['acknowledged_at' => now(), 'updated_at' => now()]);

        return ResponseEnvelope::success([
            'operation' => $this->operations->toPublicArray($operation),
        ]);
    }

    public function checkpoint(Request $request, string $operationId)
    {
        $operation = $this->operations->findOrFail($operationId);
        $step = $request->input('step');
        $metadata = $request->input('metadata');

        $this->operations->recordCheckpoint($operation->id, $step, $metadata);

        return ResponseEnvelope::success([
            'operation' => $this->operations->toPublicArray($operation),
            'checkpoints' => array_map(fn ($c) => [
                'step' => $c->step,
                'sequenceNo' => $c->sequence_no,
            ], $this->operations->getCheckpoints($operation->id)),
        ]);
    }

    public function complete(Request $request, string $operationId)
    {
        $operation = $this->operations->findOrFail($operationId);
        $this->assertVersion((int) $request->input('expectedVersion'), $operation->lock_version);

        $completed = $this->operations->complete($operation->id, $operation->lock_version);
        $this->operations->recordCheckpoint($operation->id, 'COMPLETED');

        // Update card status to ISSUED
        DB::table('cm_cards')->where('id', $operation->card_id)->update([
            'status' => 'ISSUED',
            'updated_at' => now(),
        ]);

        // Record lifecycle event
        DB::table('cm_card_lifecycle_events')->insert([
            'id' => Str::uuid()->toString(),
            'card_id' => $operation->card_id,
            'from_status' => 'INITIALIZED',
            'to_status' => 'ISSUED',
            'reason' => 'Issuance completed',
            'workstation_id' => $operation->workstation_id,
            'created_at' => now(),
        ]);

        return ResponseEnvelope::success(['operation' => $this->operations->toPublicArray($completed)]);
    }

    public function cancel(Request $request, string $operationId)
    {
        $operation = $this->operations->findOrFail($operationId);
        $this->assertVersion((int) $request->input('expectedVersion'), $operation->lock_version);

        $cancelled = $this->operations->cancel($operation->id, $operation->lock_version);

        return ResponseEnvelope::success(['operation' => $this->operations->toPublicArray($cancelled)]);
    }

    public function fail(Request $request, string $operationId)
    {
        $operation = $this->operations->findOrFail($operationId);
        $this->assertVersion((int) $request->input('expectedVersion'), $operation->lock_version);

        $failed = $this->operations->fail(
            $operation->id,
            $operation->lock_version,
            $request->input('failureCode', 'UNKNOWN'),
            $request->input('failureMessage', 'Issuance failed.'),
        );

        return ResponseEnvelope::success(['operation' => $this->operations->toPublicArray($failed)]);
    }

    public function lifecycleAction(Request $request, string $uid)
    {
        $validated = $request->validate([
            'action' => 'required|string|in:ACTIVATE,DEACTIVATE,BLOCK,UNBLOCK',
            'reason' => 'nullable|string|max:500',
        ]);

        $card = Card::where('uid', $uid)->first();
        if (!$card) {
            throw new CardManagementError('CARD_NOT_REGISTERED', 404, 'Card not found.');
        }

        $actionMap = [
            'ACTIVATE' => 'ACTIVE',
            'DEACTIVATE' => 'INACTIVE',
            'BLOCK' => 'BLOCKED',
            'UNBLOCK' => 'ACTIVE',
        ];

        $oldStatus = $card->status;
        $newStatus = $actionMap[$validated['action']];

        DB::table('cm_cards')->where('id', $card->id)->update([
            'status' => $newStatus,
            'updated_at' => now(),
        ]);

        DB::table('cm_card_lifecycle_events')->insert([
            'id' => Str::uuid()->toString(),
            'card_id' => $card->id,
            'from_status' => $oldStatus,
            'to_status' => $newStatus,
            'reason' => $validated['reason'] ?? $validated['action'],
            'workstation_id' => $this->workstationId($request),
            'created_at' => now(),
        ]);

        return ResponseEnvelope::success([
            'card' => ['uid' => $card->uid, 'status' => $newStatus],
        ]);
    }

    public function lifecycleHistory(string $uid)
    {
        $card = Card::where('uid', $uid)->first();
        if (!$card) {
            return ResponseEnvelope::success(['card' => null, 'events' => []]);
        }

        $events = DB::table('cm_card_lifecycle_events')
            ->where('card_id', $card->id)
            ->orderBy('created_at', 'desc')
            ->get();

        return ResponseEnvelope::success([
            'card' => ['uid' => $card->uid, 'cardNumber' => $card->card_number],
            'events' => $events->map(fn ($e) => [
                'id' => $e->id,
                'fromStatus' => $e->from_status,
                'toStatus' => $e->to_status,
                'reason' => $e->reason,
                'workstationId' => $e->workstation_id,
                'createdAt' => $e->created_at,
            ])->toArray(),
        ]);
    }
}
