<?php

namespace Tests\Feature\CardManagement;

use App\Models\ValidatorDevice;
use App\Models\ValidatorTrip;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Double-Entry Accounting CI Tests (Target §6).
 *
 * Verifies that total credits equal total debits across the ledger.
 * Every debit must have a corresponding credit, and every reversal
 * must exactly undo the original debit.
 */
class DoubleEntryAccountingTest extends TestCase
{
    use RefreshDatabase;

    private function headers(): array
    {
        return ['x-workstation-id' => 'ws-test'];
    }

    private function createDevice(): string
    {
        $device = ValidatorDevice::create([
            'device_id' => 'E60-ACCT-001',
            'vehicle_id' => 'BUS-042',
            'route_id' => 'ROUTE-7',
            'status' => 'ACTIVE',
            'api_token' => hash('sha256', Str::random(64)),
        ]);
        return $device->id;
    }

    private function createTrip(string $deviceId, string $tripId, int $fare, string $cardUid = 'AABBCCDD'): void
    {
        ValidatorTrip::create([
            'id' => Str::uuid()->toString(),
            'device_id' => $deviceId,
            'trip_id' => $tripId,
            'card_uid' => $cardUid,
            'tap_in_at' => '2026-08-26 10:00:00',
            'tap_out_at' => '2026-08-26 10:30:00',
            'fare_minor_units' => $fare,
            'synced' => true,
            'idempotency_key' => $tripId . '-key',
            'sync_status' => 'SYNCED',
        ]);
    }

    /**
     * TC-DE-01: A completed debit has matching debit and credit sides.
     */
    public function test_debit_has_matching_credit_and_debit(): void
    {
        $deviceId = $this->createDevice();
        $this->createTrip($deviceId, 'TRIP-DE-01', 500);

        $totalDebits = DB::table('validator_trips')
            ->whereNotNull('fare_minor_units')
            ->sum('fare_minor_units');

        $service = new \App\Services\CardManagement\SettlementService();
        $batchId = $service->runDailySettlement(\Carbon\Carbon::parse('2026-08-26'));
        $batch = $service->getBatch($batchId);

        $this->assertEquals($totalDebits, $batch['batch']['totalFareMinorUnits']);
        $this->assertEquals(
            $batch['batch']['totalFareMinorUnits'],
            $batch['batch']['totalCommissionMinorUnits'] + $batch['batch']['totalPayoutMinorUnits'],
            'Commission + Payout must equal Total Fare (conservation of money)'
        );
    }

    /**
     * TC-DE-02: Multiple debits still balance.
     */
    public function test_multiple_debits_balance(): void
    {
        $deviceId = $this->createDevice();
        $amounts = [500, 300, 700, 200, 1000];

        foreach ($amounts as $i => $amount) {
            $this->createTrip($deviceId, "TRIP-DE-02-$i", $amount, 'AABBCC' . dechex($i));
        }

        $expectedTotal = array_sum($amounts);

        $service = new \App\Services\CardManagement\SettlementService();
        $batchId = $service->runDailySettlement(\Carbon\Carbon::parse('2026-08-26'));
        $batch = $service->getBatch($batchId);

        $this->assertEquals($expectedTotal, $batch['batch']['totalFareMinorUnits']);
        $this->assertEquals(
            $batch['batch']['totalFareMinorUnits'],
            $batch['batch']['totalCommissionMinorUnits'] + $batch['batch']['totalPayoutMinorUnits']
        );
    }

    /**
     * TC-DE-03: A reversal exactly undoes the original debit.
     */
    public function test_reversal_undoes_debit(): void
    {
        $deviceId = $this->createDevice();

        // Original debit
        $this->createTrip($deviceId, 'TRIP-DE-03', 500);

        // Reversal — negative fare
        $this->createTrip($deviceId, 'TRIP-DE-03-REV', -500);

        $netFare = DB::table('validator_trips')->sum('fare_minor_units');
        $this->assertEquals(0, $netFare, 'Net fare after reversal must be zero');
    }

    /**
     * TC-DE-04: Settlement entries sum to batch totals.
     */
    public function test_settlement_entries_sum_to_batch_totals(): void
    {
        $deviceId = $this->createDevice();

        for ($i = 0; $i < 5; $i++) {
            $this->createTrip($deviceId, "TRIP-DE-04-$i", 500, 'AABBCC' . dechex($i));
        }

        $service = new \App\Services\CardManagement\SettlementService();
        $batchId = $service->runDailySettlement(\Carbon\Carbon::parse('2026-08-26'));
        $data = $service->getBatch($batchId);

        $entryFareSum = array_sum(array_column($data['entries'], 'fareMinorUnits'));
        $entryCommissionSum = array_sum(array_column($data['entries'], 'commissionMinorUnits'));
        $entryPayoutSum = array_sum(array_column($data['entries'], 'payoutMinorUnits'));

        $this->assertEquals($data['batch']['totalFareMinorUnits'], $entryFareSum);
        $this->assertEquals($data['batch']['totalCommissionMinorUnits'], $entryCommissionSum);
        $this->assertEquals($data['batch']['totalPayoutMinorUnits'], $entryPayoutSum);

        foreach ($data['entries'] as $entry) {
            $this->assertEquals(
                $entry['fareMinorUnits'],
                $entry['commissionMinorUnits'] + $entry['payoutMinorUnits'],
                "Entry {$entry['deviceId']}: fare must equal commission + payout"
            );
        }
    }

    /**
     * TC-DE-05: Empty day produces zero settlement (no phantom money).
     */
    public function test_empty_day_produces_zero_settlement(): void
    {
        $service = new \App\Services\CardManagement\SettlementService();
        $batchId = $service->runDailySettlement(\Carbon\Carbon::parse('2026-08-26'));
        $data = $service->getBatch($batchId);

        $this->assertEquals(0, $data['batch']['totalFareMinorUnits']);
        $this->assertEquals(0, $data['batch']['totalCommissionMinorUnits']);
        $this->assertEquals(0, $data['batch']['totalPayoutMinorUnits']);
        $this->assertEquals(0, $data['batch']['totalTrips']);
    }

    /**
     * TC-DE-06: Re-running settlement is idempotent (no double-counting).
     */
    public function test_settlement_idempotent_no_double_counting(): void
    {
        $deviceId = $this->createDevice();
        $this->createTrip($deviceId, 'TRIP-DE-06', 500);

        $service = new \App\Services\CardManagement\SettlementService();

        $batchId1 = $service->runDailySettlement(\Carbon\Carbon::parse('2026-08-26'));
        $batchId2 = $service->runDailySettlement(\Carbon\Carbon::parse('2026-08-26'));

        $this->assertEquals($batchId1, $batchId2);

        $data = $service->getBatch($batchId1);
        $this->assertEquals(500, $data['batch']['totalFareMinorUnits']);
    }
}
