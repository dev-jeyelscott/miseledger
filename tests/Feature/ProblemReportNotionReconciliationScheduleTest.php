<?php

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Artisan;

describe('Problem Report Notion Reconciliation Schedule', function () {
    it('registers the reconciliation command hourly', function () {
        Artisan::call('schedule:list');

        $output = Artisan::output();

        expect($output)->toContain('problem-report:sync-from-notion');
        // Hourly schedule shows as "0 * * * *" in cron syntax
        expect($output)->toContain('0 * * * *');
    });

    it('registers with withoutOverlapping coordination', function () {
        $schedule = app(Schedule::class);
        $events = $schedule->events();

        $event = collect($events)->first(
            fn ($e) => str_contains($e->command, 'problem-report:sync-from-notion'),
        );

        expect($event)->not->toBeNull();
        expect($event->withoutOverlapping)->toBe(true);
        expect($event->mutexName())->not->toBeNull();
    });

    it('registers with onOneServer coordination', function () {
        $schedule = app(Schedule::class);
        $events = $schedule->events();

        $event = collect($events)->first(
            fn ($e) => str_contains($e->command, 'problem-report:sync-from-notion'),
        );

        expect($event)->not->toBeNull();
        expect($event->onOneServer)->toBe(true);
    });

    it('registers with runInBackground mode', function () {
        $schedule = app(Schedule::class);
        $events = $schedule->events();

        $event = collect($events)->first(
            fn ($e) => str_contains($e->command, 'problem-report:sync-from-notion'),
        );

        expect($event)->not->toBeNull();
        expect($event->runInBackground)->toBe(true);
    });
});
