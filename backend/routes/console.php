<?php

use App\Console\Commands\SendPromiseReminders;
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
