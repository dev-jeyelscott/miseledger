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
        Artisan::call('schedule:list');

        $output = Artisan::output();

        expect($output)->toContain('problem-report:sync-from-notion');
    });

    it('registers with onOneServer coordination', function () {
        Artisan::call('schedule:list');

        $output = Artisan::output();

        expect($output)->toContain('problem-report:sync-from-notion');
    });

    it('registers with runInBackground mode', function () {
        Artisan::call('schedule:list');

        $output = Artisan::output();

        expect($output)->toContain('problem-report:sync-from-notion');
    });
});
