<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

use Illuminate\Support\Facades\Schedule;

Schedule::command('retention:check-disposals')->dailyAt('01:00');
Schedule::command('contracts:check-expirations')->dailyAt('02:00');
Schedule::command('reservations:reminders')->hourly();
Schedule::command('legal:milestone-reminders')->dailyAt('03:00');
Schedule::command('legal:milestone-reminders')->dailyAt('08:00');
