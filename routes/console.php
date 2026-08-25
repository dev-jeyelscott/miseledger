<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('billing:reconcile')
    ->hourly()
    ->withoutOverlapping(30)
    ->onOneServer()
    ->runInBackground();

Schedule::command('billing:send-renewal-reminders')
    ->dailyAt('09:00')
    ->withoutOverlapping(30)
    ->onOneServer()
    ->runInBackground();
