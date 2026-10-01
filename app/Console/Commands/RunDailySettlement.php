<?php

namespace App\Console\Commands;

use App\Services\CardManagement\SettlementService;
use Illuminate\Console\Command;
use Carbon\Carbon;

class RunDailySettlement extends Command
{
    protected $signature = 'settlement:run-daily {date? : Settlement date (YYYY-MM-DD), defaults to yesterday}';
    protected $description = 'Phase 61: Run daily settlement for the given date (defaults to yesterday)';

    public function handle(SettlementService $service): int
    {
        $date = $this->argument('date')
            ? Carbon::parse($this->argument('date'))
            : Carbon::yesterday();

        $this->info("Running settlement for {$date->toDateString()}...");

        $batchId = $service->runDailySettlement($date);

        // Approval and payout are deliberate operator actions — the cron only
        // calculates the batch. Operators approve/pay/deliver via the API.
        $data = $service->getBatch($batchId);

        $batch = $data['batch'];
        $this->info("Settlement batch created: {$batch['id']}");
        $this->info("  Status: {$batch['status']}");
        $this->info("  Total trips: {$batch['totalTrips']}");
        $this->info("  Total fare: {$batch['totalFareMinorUnits']} minor units");
        $this->info("  Total commission: {$batch['totalCommissionMinorUnits']} minor units");
        $this->info("  Total payout: {$batch['totalPayoutMinorUnits']} minor units");
        $this->info("  Entries: " . count($data['entries']));

        return self::SUCCESS;
    }
}
