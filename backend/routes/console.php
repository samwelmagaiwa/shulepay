<?php

use App\Console\Commands\SendPromiseReminders;
use App\Console\Commands\SendVehicleDocumentAlerts;
use App\Console\Commands\SendWeeklyReport;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Promise reminders — run daily at 07:00
// Day-before: guardian SMS. On-the-day: accountant SMS.
Schedule::command(SendPromiseReminders::class)
    ->dailyAt('07:00')
    ->withoutOverlapping()
    ->runInBackground();

// Weekly fee-collection summary — runs every Monday at 08:00
Schedule::command(SendWeeklyReport::class)
    ->weekly()
    ->mondays()
    ->at('08:00')
    ->withoutOverlapping()
    ->runInBackground();

// Vehicle insurance / licence expiry alerts (in-app notices, no SMS) — daily, once a
// document reaches 30, 14 or 7 days before expiry, or expires.
Schedule::command(SendVehicleDocumentAlerts::class)
    ->dailyAt('07:30')
    ->withoutOverlapping()
    ->runInBackground();
