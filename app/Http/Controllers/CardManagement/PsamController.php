<?php

namespace App\Http\Controllers\CardManagement;

use App\Models\Psam;
use App\Models\PsamIssuanceOperation;
use App\Models\ValidatorDevice;
use App\Services\CardManagement\ResponseEnvelope;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * PSAM registry API (Phases 57–58, ADR 0015/0016).
 *
 * The card-desktop PSAM personalization ceremony reports each issuance
 * operation here. This table holds metadata ONLY — PSAM numbers, ATR,
 * AID, key-profile version, issuer workstation/operator. Key material
 * (DCCK/DCMK/DCAK/DAMK/DPK1/TAC roots, transport keys, session keys)
 * is never accepted or stored; the request validator simply drops any
 * unknown fields.
 */
final class PsamController extends BaseCardManagementController
{
    /**
     * POST /api/v1/psams
     *
     * Register (or update) a PSAM after a personalization run and append
     * an issuance-operation audit row. Upsert on `psamNumber` +
     * `operationId` makes retries idempotent.
     */
    public function register(Request $request)
    {
        $validated = $request->validate([
            'operationId'      => 'required|string|uuid',
            'psamNumber'       => 'required|string|regex:/^[0-9]{12}$/',
            'atr'              => 'nullable|string|regex:/^[0-9A-Fa-f]+$/|max:64',
            'adfAid'           => 'nullable|string|regex:/^[0-9A-Fa-f]+$/|max:32',
            'operationMode'    => 'required|string|in:INITIALIZE,REINITIALIZE',
            'result'           => 'required|string|in:SUCCESS,FAILED',
            'failedStep'       => 'nullable|string|max:64',
            'error'            => 'nullable|string|max:500',
            'statusWord'       => 'nullable|string|max:8',
            'steps'            => 'nullable|array',
            'steps.*'          => 'string|max:64',
            'keyProfileId'     => 'nullable|string|max:128',
            'rootKeyVersions'  => 'nullable|array',
            'issuedAt'         => 'nullable|date',
        ]);

        $workstationId = $request->header('x-workstation-id');
        $operatorId = $request->header('x-operator-id');

        $psam = DB::transaction(function () use ($validated, $workstationId, $operatorId) {
            $psam = Psam::where('psam_number', $validated['psamNumber'])->first();

            $attrs = [
                'atr'                   => $validated['atr'] ?? null,
                'adf_aid'               => $validated['adfAid'] ?? null,
                'key_profile_id'        => $validated['keyProfileId'] ?? null,
                'root_key_versions'     => $validated['rootKeyVersions'] ?? null,
                'issuer_workstation_id' => $workstationId,
                'issuer_operator_id'    => $operatorId,
            ];

            if ($validated['result'] === 'SUCCESS') {
                $attrs['status'] = $psam && $psam->status === Psam::STATUS_INSTALLED
                    ? Psam::STATUS_INSTALLED
                    : Psam::STATUS_INITIALIZED;
                $attrs['issued_at'] = $validated['issuedAt'] ?? now();
            } else {
                // A failed run must not flip a live PSAM back to INITIALIZED.
                $attrs['status'] = $psam?->status ?? 'FAILED';
            }

            if ($psam) {
                $psam->update($attrs);
            } else {
                $psam = Psam::create(array_merge($attrs, [
                    'psam_number' => $validated['psamNumber'],
                ]));
            }

            // Append the audit row — unique on operationId for retry safety.
            PsamIssuanceOperation::updateOrCreate(
                ['id' => $validated['operationId']],
                [
                    'psam_id'        => $psam->id,
                    'psam_number'    => $validated['psamNumber'],
                    'operation_mode' => $validated['operationMode'],
                    'result'         => $validated['result'],
                    'failed_step'    => $validated['failedStep'] ?? null,
                    'error'          => $validated['error'] ?? null,
                    'status_word'    => $validated['statusWord'] ?? null,
                    'steps'          => $validated['steps'] ?? null,
                    'atr'            => $validated['atr'] ?? null,
                    'workstation_id' => $workstationId,
                    'operator_id'    => $operatorId,
                    'issued_at'      => $validated['issuedAt'] ?? null,
                ]
            );

            return $psam;
        });

        return ResponseEnvelope::success([
            'psam' => $this->serialize($psam),
        ], $validated['result'] === 'SUCCESS' ? 200 : 200);
    }

    /**
     * POST /api/v1/psams/{psamNumber}/bind-device
     *
     * Record that a personalized PSAM was physically installed into a
     * validator's SAM slot.
     */
    public function bindDevice(Request $request, string $psamNumber)
    {
        $validated = $request->validate([
            'deviceId' => 'required|string|max:128',
        ]);

        $psam = Psam::where('psam_number', $psamNumber)->first();
        if (!$psam) {
            return ResponseEnvelope::error('PSAM_NOT_FOUND', 'Unknown PSAM number.', 404);
        }

        $device = ValidatorDevice::where('device_id', $validated['deviceId'])->first();
        if (!$device) {
            return ResponseEnvelope::error('DEVICE_NOT_FOUND', 'Unknown validator device.', 404);
        }

        $psam->update([
            'installed_device_id' => $device->device_id,
            'installed_at' => now(),
            'status' => Psam::STATUS_INSTALLED,
        ]);

        return ResponseEnvelope::success(['psam' => $this->serialize($psam)]);
    }

    /**
     * GET /api/v1/psams — registry listing for the issuance console.
     */
    public function index(Request $request)
    {
        $query = Psam::query()->orderBy('psam_number');
        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }
        $psams = $query->limit((int) $request->query('limit', 200))->get();

        return ResponseEnvelope::success([
            'psams' => $psams->map(fn (Psam $p) => $this->serialize($p))->all(),
        ]);
    }

    /**
     * GET /api/v1/psams/{psamNumber}
     */
    public function show(string $psamNumber)
    {
        $psam = Psam::where('psam_number', $psamNumber)->first();
        if (!$psam) {
            return ResponseEnvelope::error('PSAM_NOT_FOUND', 'Unknown PSAM number.', 404);
        }

        return ResponseEnvelope::success([
            'psam' => $this->serialize($psam),
            'issuanceOperations' => $psam->issuanceOperations()
                ->orderByDesc('created_at')->limit(50)->get()
                ->map(fn (PsamIssuanceOperation $op) => [
                    'operationId' => $op->id,
                    'operationMode' => $op->operation_mode,
                    'result' => $op->result,
                    'failedStep' => $op->failed_step,
                    'error' => $op->error,
                    'issuedAt' => $op->issued_at?->toIso8601ZuluString(),
                ])->all(),
        ]);
    }

    private function serialize(Psam $psam): array
    {
        return [
            'id' => $psam->id,
            'psamNumber' => $psam->psam_number,
            'atr' => $psam->atr,
            'adfAid' => $psam->adf_aid,
            'status' => $psam->status,
            'keyProfileId' => $psam->key_profile_id,
            'issuerWorkstationId' => $psam->issuer_workstation_id,
            'issuerOperatorId' => $psam->issuer_operator_id,
            'issuedAt' => $psam->issued_at?->toIso8601ZuluString(),
            'installedDeviceId' => $psam->installed_device_id,
            'installedAt' => $psam->installed_at?->toIso8601ZuluString(),
        ];
    }
}
