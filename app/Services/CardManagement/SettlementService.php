<?php

namespace App\Services\CardManagement;

use App\Models\CardManagement\ValidatorTrip;
use App\Models\CardManagement\ValidatorDevice;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;

/**
 * Phase 61: Settlement Engine.
 *
 * Aggregates completed validator trips by date, calculates operator commissions,
 * and generates settlement batches. Designed to run as a daily CRON job.
 *
 * Settlement flow:
 * 1. CRON triggers `runDailySettlement($date)` at 02:00 each day for the previous day
 * 2. All completed trips for that date are aggregated by device/operator
 * 3. Commission is calculated (default 10% of fare)
 * 4. A settlement batch is created with entries per device
 * 5. The batch goes through: OPEN → CALCULATED → APPROVED → PAID → CLOSED
 *
 * Payout = Total Fare - Commission
 */
class SettlementService
{
    private SettlementRulesEngine $rulesEngine;

    public function __construct(
        private readonly float $defaultCommissionRate = 0.10,
    ) {
        $this->rulesEngine = new SettlementRulesEngine();
    }

    /**
     * Get the default rules configuration (10% flat commission).
     */
    private function defaultRulesConfig(): array
    {
        return [
            'rules' => [
                [
                    'condition' => ['default' => true],
                    'commissionPercent' => $this->defaultCommissionRate * 100,
                    'description' => 'Default ' . ($this->defaultCommissionRate * 100) . '% commission',
                ],
            ],
        ];
    }

    /**
     * Load the rules configuration from the database or fall back to default.
     */
    private function loadRulesConfig(): array
    {
        $stored = DB::table('cm_settlement_rules')
            ->where('is_active', true)
            ->orderBy('version', 'desc')
            ->first();

        if ($stored) {
            $config = json_decode($stored->rules_json, true);
            if (is_array($config) && isset($config['rules'])) {
                return $config;
            }
        }

        return $this->defaultRulesConfig();
    }

    /**
     * Run the daily settlement for a given date.
     * Returns the settlement batch ID.
     */
    public function runDailySettlement(Carbon $date): string
    {
        $dateOnly = $date->toDateString();

        // Check if a batch already exists for this date (idempotent).
        $existing = DB::table('cm_settlement_batches')
            ->where('settlement_date', $dateOnly)
            ->first();

        // A finalized batch is left untouched; its trips are immutable. Any
        // unsettled trips for earlier dates are swept into the next run, so
        // the early return below can never strand them.
        if ($existing && !in_array($existing->status, ['OPEN', 'CALCULATED'], true)) {
            return $existing->id;
        }

        $batchId = $existing->id ?? Str::uuid()->toString();
        $rulesConfig = $this->loadRulesConfig();

        DB::transaction(function () use ($batchId, $dateOnly, $date, $rulesConfig, $existing) {
            if (!$existing) {
                DB::table('cm_settlement_batches')->insert([
                    'id' => $batchId,
                    'settlement_date' => $dateOnly,
                    'status' => 'OPEN',
                    'commission_rate' => $this->defaultCommissionRate,
                    'rules_config' => json_encode($rulesConfig),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            // Aggregate trips by device. Eligible trips are those with a
            // tap-out on or before the settlement date that are either
            // unsettled (settlement_batch_id IS NULL) or already tagged to
            // this batch — so late-synced trips are always picked up and a
            // re-run of an unfinalized batch recomputes cleanly.
            $trips = ValidatorTrip::whereDate('tap_out_at', '<=', $dateOnly)
                ->whereNotNull('fare_minor_units')
                ->where(function ($q) use ($batchId) {
                    $q->whereNull('settlement_batch_id')
                      ->orWhere('settlement_batch_id', $batchId);
                })
                ->lockForUpdate()
                ->get();

            $byDevice = [];
            foreach ($trips as $trip) {
                $deviceId = $trip->device_id;
                if (!isset($byDevice[$deviceId])) {
                    $byDevice[$deviceId] = [
                        'device_id' => $deviceId,
                        'trip_count' => 0,
                        'fare_minor_units' => 0,
                    ];
                }
                $byDevice[$deviceId]['trip_count']++;
                $byDevice[$deviceId]['fare_minor_units'] += $trip->fare_minor_units ?? 0;
            }

            $totalTrips = 0;
            $totalFare = 0;
            $totalCommission = 0;
            $totalPayout = 0;

            // Rebuild entries from scratch so a recalculation never leaves
            // stale rows from a previous run.
            DB::table('cm_settlement_entries')->where('batch_id', $batchId)->delete();

            foreach ($byDevice as $deviceData) {
                $fare = $deviceData['fare_minor_units'];

                // Look up device for operator mapping
                $device = ValidatorDevice::find($deviceData['device_id']);

                // Evaluate commission via the JSON rules engine
                $ruleResult = $this->rulesEngine->evaluate([
                    'deviceId' => $deviceData['device_id'],
                    'operatorId' => $device?->vehicle_id,
                    'routeId' => $device?->route_id,
                    'fareMinorUnits' => $fare,
                    'tripCount' => $deviceData['trip_count'],
                    'metadata' => [
                        'routeId' => $device?->route_id,
                    ],
                ], $rulesConfig);

                $commission = $ruleResult->commissionMinorUnits;
                $payout = $ruleResult->payoutMinorUnits;

                DB::table('cm_settlement_entries')->insert([
                    'id' => Str::uuid()->toString(),
                    'batch_id' => $batchId,
                    'device_id' => $deviceData['device_id'],
                    'operator_id' => $device?->vehicle_id,
                    'trip_count' => $deviceData['trip_count'],
                    'fare_minor_units' => $fare,
                    'commission_minor_units' => $commission,
                    'payout_minor_units' => $payout,
                    'metadata' => json_encode([
                        'routeId' => $device?->route_id,
                        'matchedRule' => $ruleResult->matchedRuleDescription,
                        'commissionPercent' => $ruleResult->commissionPercent,
                    ]),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $totalTrips += $deviceData['trip_count'];
                $totalFare += $fare;
                $totalCommission += $commission;
                $totalPayout += $payout;
            }

            // Tag every included trip with this batch — settled trips are
            // excluded from future runs and frozen against re-sync mutation.
            if ($trips->isNotEmpty()) {
                ValidatorTrip::whereIn('id', $trips->pluck('id'))
                    ->update(['settlement_batch_id' => $batchId, 'updated_at' => now()]);
            }

            // Update batch totals and mark as CALCULATED
            DB::table('cm_settlement_batches')->where('id', $batchId)->update([
                'status' => 'CALCULATED',
                'total_trips' => $totalTrips,
                'total_fare_minor_units' => $totalFare,
                'total_commission_minor_units' => $totalCommission,
                'total_payout_minor_units' => $totalPayout,
                'calculated_at' => now(),
                'updated_at' => now(),
            ]);
        });

        return $batchId;
    }

    /**
     * Approve a settlement batch.
     */
    public function approveBatch(string $batchId): object
    {
        $batch = DB::table('cm_settlement_batches')->where('id', $batchId)->first();
        if (!$batch) {
            throw new CardManagementError('SETTLEMENT_NOT_FOUND', 404, 'Settlement batch not found.');
        }

        if ($batch->status !== 'CALCULATED') {
            throw new CardManagementError('INVALID_STATE_TRANSITION', 409, 'Batch must be CALCULATED to approve.');
        }

        DB::table('cm_settlement_batches')->where('id', $batchId)->update([
            'status' => 'APPROVED',
            'approved_at' => now(),
            'updated_at' => now(),
        ]);

        return DB::table('cm_settlement_batches')->where('id', $batchId)->first();
    }

    /**
     * Mark a settlement batch as paid.
     */
    public function markPaid(string $batchId): object
    {
        $batch = DB::table('cm_settlement_batches')->where('id', $batchId)->first();
        if (!$batch) {
            throw new CardManagementError('SETTLEMENT_NOT_FOUND', 404, 'Settlement batch not found.');
        }

        if ($batch->status !== 'APPROVED') {
            throw new CardManagementError('INVALID_STATE_TRANSITION', 409, 'Batch must be APPROVED to mark as paid.');
        }

        DB::table('cm_settlement_batches')->where('id', $batchId)->update([
            'status' => 'PAID',
            'paid_at' => now(),
            'updated_at' => now(),
        ]);

        return DB::table('cm_settlement_batches')->where('id', $batchId)->first();
    }

    /**
     * Close a settlement batch (final state).
     */
    public function closeBatch(string $batchId): object
    {
        $batch = DB::table('cm_settlement_batches')->where('id', $batchId)->first();
        if (!$batch) {
            throw new CardManagementError('SETTLEMENT_NOT_FOUND', 404, 'Settlement batch not found.');
        }

        if ($batch->status !== 'PAID') {
            throw new CardManagementError('INVALID_STATE_TRANSITION', 409, 'Batch must be PAID to close.');
        }

        DB::table('cm_settlement_batches')->where('id', $batchId)->update([
            'status' => 'CLOSED',
            'closed_at' => now(),
            'updated_at' => now(),
        ]);

        return DB::table('cm_settlement_batches')->where('id', $batchId)->first();
    }

    /**
     * Get a settlement batch with its entries.
     */
    public function getBatch(string $batchId): array
    {
        $batch = DB::table('cm_settlement_batches')->where('id', $batchId)->first();
        if (!$batch) {
            throw new CardManagementError('SETTLEMENT_NOT_FOUND', 404, 'Settlement batch not found.');
        }

        $entries = DB::table('cm_settlement_entries')
            ->where('batch_id', $batchId)
            ->get();

        return [
            'batch' => $this->batchToPublicArray($batch),
            'entries' => $entries->map(fn ($e) => $this->entryToPublicArray($e))->toArray(),
        ];
    }

    /**
     * List settlement batches.
     */
    public function listBatches(int $limit = 50): array
    {
        $batches = DB::table('cm_settlement_batches')
            ->orderBy('settlement_date', 'desc')
            ->limit($limit)
            ->get();

        return $batches->map(fn ($b) => $this->batchToPublicArray($b))->toArray();
    }

    /**
     * Generate a payout file in the specified format.
     * Supports CSV, NACHA, and ISO20022 (Target §5.2).
     */
    public function generatePayoutFile(string $batchId, string $format = 'csv'): string
    {
        $format = strtolower($format);

        // Bank payout formats (NACHA ACH, ISO 20022 pain.001) are not yet
        // compliant with the real file specifications — gated behind an
        // explicit opt-in so a non-conformant file can never reach a bank.
        // CSV stays available for internal / manual settlement flows.
        if (
            in_array($format, ['nacha', 'iso20022'], true)
            && !config('card_management.settlement.bank_payout_formats_enabled', false)
        ) {
            throw new CardManagementError(
                'PAYOUT_FORMAT_UNAVAILABLE',
                422,
                "Payout format '{$format}' is not enabled: the bank file layout is not yet compliant."
            );
        }

        return match ($format) {
            'nacha' => $this->generateNachaFile($batchId),
            'iso20022' => $this->generateIso20022File($batchId),
            default => $this->generateCsvFile($batchId),
        };
    }

    /**
     * Generate a CSV payout file (legacy format).
     */
    private function generateCsvFile(string $batchId): string
    {
        $data = $this->getBatch($batchId);

        $lines = [];
        $lines[] = 'BatchID,SettlementDate,Status,TotalTrips,TotalFareMinorUnits,TotalCommissionMinorUnits,TotalPayoutMinorUnits';
        $b = $data['batch'];
        $lines[] = "{$b['id']},{$b['settlementDate']},{$b['status']},{$b['totalTrips']},{$b['totalFareMinorUnits']},{$b['totalCommissionMinorUnits']},{$b['totalPayoutMinorUnits']}";
        $lines[] = '';
        $lines[] = 'DeviceID,OperatorID,TripCount,FareMinorUnits,CommissionMinorUnits,PayoutMinorUnits';

        foreach ($data['entries'] as $e) {
            $lines[] = "{$e['deviceId']},{$e['operatorId']},{$e['tripCount']},{$e['fareMinorUnits']},{$e['commissionMinorUnits']},{$e['payoutMinorUnits']}";
        }

        return implode("\n", $lines);
    }

    /**
     * Generate a NACHA ACH file (Target §5.2).
     *
     * NACHA format: fixed-width records, 94 bytes per line.
     * File Header → Batch Header → Entry Detail → Batch Control → File Control.
     *
     * This generates a CCD (Corporate Credit/Debit) batch for operator payouts.
     */
    private function generateNachaFile(string $batchId): string
    {
        $data = $this->getBatch($batchId);
        $batch = $data['batch'];
        $entries = $data['entries'];

        $lines = [];

        // 1. File Header Record (1)
        $lines[] = $this->nachaFileHeader($batch);

        // 2. Batch Header Record (5)
        $lines[] = $this->nachaBatchHeader($batch);

        // 3. Entry Detail Records (6) — one per operator entry
        $entryCount = 0;
        $entryHash = 0;
        $totalDebit = 0;

        foreach ($entries as $e) {
            $lines[] = $this->nachaEntryDetail($e, $entryCount + 1);
            $entryCount++;
            $totalDebit += $e['payoutMinorUnits'];
            // Hash: sum of first 8 digits of routing number (simulated)
            $entryHash += crc32($e['operatorId'] ?? '') % 100000000;
        }

        // 4. Batch Control Record (8)
        $lines[] = $this->nachaBatchControl($entryCount, $entryHash, $totalDebit);

        // 5. File Control Record (9)
        $lines[] = $this->nachaFileControl(1, $entryCount, $entryHash, $totalDebit);

        // Pad to multiple of 94 bytes per block (10 records per block)
        $blockCount = 10 - (count($lines) % 10);
        if ($blockCount < 10) {
            for ($i = 0; $i < $blockCount; $i++) {
                $lines[] = str_repeat('9', 94);
            }
        }

        return implode("\n", $lines);
    }

    private function nachaFileHeader(array $batch): string
    {
        $date = str_replace('-', '', $batch['settlementDate']);
        $time = gmdate('Hi');
        $fileId = 'A'; // File ID modifier
        $routing = '123456789'; // Originating routing (configurable)
        $origin = 'HITEEAFCTF'; // Immediate origin (10 chars)

        return sprintf(
            '1%01s%02s%-10s%-9s%-1s%6s%4s%1s%-23s%-23s',
            '01', // Priority code
            '01', // Record type
            $origin,
            $routing,
            $fileId,
            $date,
            $time,
            'A',
            'HITEE AFC SETTLEMENT',
            'FEDERAL RESERVE BANK'
        );
    }

    private function nachaBatchHeader(array $batch): string
    {
        $date = str_replace('-', '', $batch['settlementDate']);
        return sprintf(
            '5%01s%-3sCCD%-10s%-2s%-9s%-16s%-20s%-10s%-3s%-1s%-15s',
            '200', // Service class code
            'CCD',
            'HITEE AFC',
            '01',
            '123456789',
            'SETTLEMENT',
            $batch['id'],
            '',
            '',
            '',
            $date
        );
    }

    private function nachaEntryDetail(array $e, int $traceNum): string
    {
        $routing = '987654321'; // Receiving routing (configurable per operator)
        $account = str_pad(substr($e['operatorId'] ?? 'OPS-000000', 0, 17), 17, '0', STR_PAD_LEFT);
        $amount = str_pad((string) $e['payoutMinorUnits'], 10, '0', STR_PAD_LEFT);
        $name = str_pad(substr($e['operatorId'] ?? 'OPERATOR', 0, 22), 22);

        return sprintf(
            '6%-9s%-1s%-17s%-1s%-10s%-15s%-22s%-2s%-7s%07d',
            $routing,
            'C', // Checking account
            $account,
            '1', // Transaction code (checking credit)
            $amount,
            'SETTLEMENT',
            $name,
            '',
            '1234567',
            $traceNum
        );
    }

    private function nachaBatchControl(int $entryCount, int $entryHash, int $totalDebit): string
    {
        return sprintf(
            '8%-3s%06d%010d%012d%012d%-10s%-25s%-7s',
            '200',
            $entryCount,
            $entryHash,
            0, // Total credit
            $totalDebit,
            'HITEE AFC',
            '',
            ''
        );
    }

    private function nachaFileControl(int $batchCount, int $entryCount, int $entryHash, int $totalDebit): string
    {
        return sprintf(
            '9%06d%06d%010d%012d%012d',
            $batchCount,
            $entryCount,
            $entryHash,
            0, // Total credit
            $totalDebit
        );
    }

    /**
     * Generate an ISO20022 pain.001.001.03 XML file (Target §5.2).
     *
     * CustomerCreditTransferInitiation for bank payout processing.
     */
    private function generateIso20022File(string $batchId): string
    {
        $data = $this->getBatch($batchId);
        $batch = $data['batch'];
        $entries = $data['entries'];

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<Document xmlns="urn:iso:std:iso:20022:tech:xsd:pain.001.001.03">' . "\n";
        $xml .= '  <CstmrCdtTrfInitn>' . "\n";
        $xml .= '    <GrpHdr>' . "\n";
        $xml .= '      <MsgId>' . htmlspecialchars($batch['id']) . '</MsgId>' . "\n";
        $xml .= '      <CreDtTm>' . gmdate('Y-m-d\TH:i:s\Z') . '</CreDtTm>' . "\n";
        $xml .= '      <NbOfTxs>' . count($entries) . '</NbOfTxs>' . "\n";
        $xml .= '      <CtrlSum>' . $batch['totalPayoutMinorUnits'] . '</CtrlSum>' . "\n";
        $xml .= '      <InitgPty><Nm>HITEE AFC</Nm></InitgPty>' . "\n";
        $xml .= '    </GrpHdr>' . "\n";
        $xml .= '    <PmtInf>' . "\n";
        $xml .= '      <PmtInfId>' . htmlspecialchars($batch['id']) . '</PmtInfId>' . "\n";
        $xml .= '      <PmtMtd>TRF</PmtMtd>' . "\n";
        $xml .= '      <NbOfTxs>' . count($entries) . '</NbOfTxs>' . "\n";
        $xml .= '      <CtrlSum>' . $batch['totalPayoutMinorUnits'] . '</CtrlSum>' . "\n";
        $xml .= '      <PmtTpInf><SvcLvl><Cd>URGP</Cd></SvcLvl></PmtTpInf>' . "\n";
        $xml .= '      <ReqdExctnDt>' . $batch['settlementDate'] . '</ReqdExctnDt>' . "\n";
        $xml .= '      <Dbtr><Nm>HITEE AFC Operations</Nm></Dbtr>' . "\n";
        $xml .= '      <DbtrAcct><Id><Othr><Id>SETTLEMENT-001</Id></Othr></Id></DbtrAcct>' . "\n";
        $xml .= '      <DbtrAgt><FinInstnId><BIC>HITEEAF1</BIC></FinInstnId></DbtrAgt>' . "\n";

        foreach ($entries as $e) {
            $amount = $e['payoutMinorUnits'] / 100;
            $xml .= '      <CdtTrfTxInf>' . "\n";
            $xml .= '        <PmtId><EndToEndId>' . htmlspecialchars($e['id']) . '</EndToEndId></PmtId>' . "\n";
            $xml .= '        <Amt><InstdAmt Ccy="USD">' . number_format($amount, 2, '.', '') . '</InstdAmt></Amt>' . "\n";
            $xml .= '        <CdtrAgt><FinInstnId><BIC>OPERAT1</BIC></FinInstnId></CdtrAgt>' . "\n";
            $xml .= '        <Cdtr><Nm>' . htmlspecialchars($e['operatorId'] ?? 'OPERATOR') . '</Nm></Cdtr>' . "\n";
            $xml .= '        <CdtrAcct><Id><Othr><Id>' . htmlspecialchars($e['operatorId'] ?? 'OPS-001') . '</Id></Othr></Id></CdtrAcct>' . "\n";
            $xml .= '        <RmtInf><Ustrd>Settlement ' . htmlspecialchars($batch['settlementDate']) . '</Ustrd></RmtInf>' . "\n";
            $xml .= '      </CdtTrfTxInf>' . "\n";
        }

        $xml .= '    </PmtInf>' . "\n";
        $xml .= '  </CstmrCdtTrfInitn>' . "\n";
        $xml .= '</Document>';

        return $xml;
    }

    /**
     * Deliver the payout file for a batch via SFTP (Target §5.2).
     *
     * Stub implementation — real SFTP config comes when banking credentials arrive.
     */
    public function deliverPayoutFile(string $batchId, string $format = 'nacha'): bool
    {
        logger('[Settlement] Payout file delivered via SFTP (stub)', ['batch_id' => $batchId, 'format' => $format]);

        return true;
    }

    private function batchToPublicArray(object $batch): array
    {
        return [
            'id' => $batch->id,
            'settlementDate' => $batch->settlement_date,
            'status' => $batch->status,
            'totalTrips' => $batch->total_trips,
            'totalFareMinorUnits' => $batch->total_fare_minor_units,
            'totalCommissionMinorUnits' => $batch->total_commission_minor_units,
            'totalPayoutMinorUnits' => $batch->total_payout_minor_units,
            'commissionRate' => (float) $batch->commission_rate,
            'calculatedAt' => $batch->calculated_at,
            'approvedAt' => $batch->approved_at,
            'paidAt' => $batch->paid_at,
            'closedAt' => $batch->closed_at,
        ];
    }

    private function entryToPublicArray(object $entry): array
    {
        return [
            'id' => $entry->id,
            'batchId' => $entry->batch_id,
            'deviceId' => $entry->device_id,
            'operatorId' => $entry->operator_id,
            'tripCount' => $entry->trip_count,
            'fareMinorUnits' => $entry->fare_minor_units,
            'commissionMinorUnits' => $entry->commission_minor_units,
            'payoutMinorUnits' => $entry->payout_minor_units,
        ];
    }
}
