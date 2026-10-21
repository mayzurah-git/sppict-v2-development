<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// UM11A: nyahaktif automatik akaun yang tidak log masuk melebihi 6 bulan — setiap Isnin 1:00 pagi.
// Pelayan perlu menjalankan `php artisan schedule:run` setiap minit (cron / Windows Task Scheduler).
Schedule::command('sppict:deactivate-dormant-users')
    ->weeklyOn(1, '01:00')
    ->withoutOverlapping();
