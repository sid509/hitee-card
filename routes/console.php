<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Phase 61: Daily settlement CRON — runs at 00:00 UTC each day for the previous day
Schedule::command('settlement:run-daily')->dailyAt('00:00')->withoutOverlapping();
