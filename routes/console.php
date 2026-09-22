<?php

use App\Actions\Platform\Alerts\RecordBackupCommandAlert;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\App;
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

Schedule::command('backup:database')
    ->dailyAt((string) config('backup.schedule_time'))
    ->withoutOverlapping(120)
    ->onOneServer()
    ->runInBackground()
    ->onFailure(fn () => App::make(RecordBackupCommandAlert::class)->recordFailure())
    ->onSuccess(fn () => App::make(RecordBackupCommandAlert::class)->recordSuccess());

Schedule::command('platform-alerts:evaluate')
    ->hourly()
    ->withoutOverlapping(10)
    ->onOneServer()
    ->runInBackground();

Schedule::command('platform-health:snapshot-table-sizes')
    ->dailyAt('01:00')
    ->withoutOverlapping(30)
    ->onOneServer()
    ->runInBackground();

Schedule::command('problem-report:sync-from-notion')
    ->hourly()
    ->withoutOverlapping(30)
    ->onOneServer()
    ->runInBackground();

Schedule::command('problem-report:sync-to-notion')
    ->hourly()
    ->when(fn (): bool => (bool) config('services.notion.enabled'))
    ->withoutOverlapping(30)
    ->onOneServer()
    ->runInBackground();
