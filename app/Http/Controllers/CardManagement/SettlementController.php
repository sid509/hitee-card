<?php

namespace App\Http\Controllers\CardManagement;

use App\Services\CardManagement\CardManagementError;
use App\Services\CardManagement\ResponseEnvelope;
use App\Services\CardManagement\SettlementService;
use Illuminate\Http\Request;
use Carbon\Carbon;

/**
 * Settlement controller.
 * Phase 61: Daily settlement engine API.
 *
 * Endpoints:
 *   POST   /api/v1/settlement/run           — trigger settlement for a date
 *   GET    /api/v1/settlement/batches        — list batches
 *   GET    /api/v1/settlement/batches/{id}   — get batch with entries
 *   POST   /api/v1/settlement/batches/{id}/approve  — approve batch
 *   POST   /api/v1/settlement/batches/{id}/pay      — mark as paid
 *   POST   /api/v1/settlement/batches/{id}/close    — close batch
 *   GET    /api/v1/settlement/batches/{id}/payout-file — generate CSV
 */
final class SettlementController extends BaseCardManagementController
{
    private SettlementService $service;

    public function __construct()
    {
        $this->service = new SettlementService();
    }

    public function run(Request $request)
    {
        $date = $request->input('date')
            ? Carbon::parse($request->input('date'))
            : Carbon::yesterday();

        $batchId = $this->service->runDailySettlement($date);
        $data = $this->service->getBatch($batchId);

        return ResponseEnvelope::success($data, 201);
    }

    public function listBatches(Request $request)
    {
        $limit = (int) $request->input('limit', 50);
        $batches = $this->service->listBatches($limit);

        return ResponseEnvelope::success(['batches' => $batches]);
    }

    public function getBatch(string $batchId)
    {
        $data = $this->service->getBatch($batchId);
        return ResponseEnvelope::success($data);
    }

    public function approve(string $batchId)
    {
        $batch = $this->service->approveBatch($batchId);
        return ResponseEnvelope::success(['batch' => $this->batchToArray($batch)]);
    }

    public function markPaid(string $batchId)
    {
        $batch = $this->service->markPaid($batchId);
        return ResponseEnvelope::success(['batch' => $this->batchToArray($batch)]);
    }

    public function close(string $batchId)
    {
        $batch = $this->service->closeBatch($batchId);
        return ResponseEnvelope::success(['batch' => $this->batchToArray($batch)]);
    }

    public function payoutFile(Request $request, string $batchId)
    {
        $format = $request->input('format', 'csv');
        $content = $this->service->generatePayoutFile($batchId, $format);

        $contentTypes = [
            'csv' => 'text/csv',
            'nacha' => 'application/octet-stream',
            'iso20022' => 'application/xml',
        ];
        $extensions = [
            'csv' => 'csv',
            'nacha' => 'ach',
            'iso20022' => 'xml',
        ];

        $ct = $contentTypes[$format] ?? 'text/csv';
        $ext = $extensions[$format] ?? 'csv';

        return response($content, 200, [
            'Content-Type' => $ct,
            'Content-Disposition' => "attachment; filename=\"settlement-{$batchId}.{$ext}\"",
        ]);
    }

    private function batchToArray(object $batch): array
    {
        return [
            'id' => $batch->id,
            'settlementDate' => $batch->settlement_date,
            'status' => $batch->status,
            'totalTrips' => $batch->total_trips,
            'totalFareMinorUnits' => $batch->total_fare_minor_units,
            'totalCommissionMinorUnits' => $batch->total_commission_minor_units,
            'totalPayoutMinorUnits' => $batch->total_payout_minor_units,
        ];
    }
}
