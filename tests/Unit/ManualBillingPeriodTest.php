<?php

use App\Support\Billing\ManualBillingPeriod;
use Illuminate\Support\Carbon;

test('a manual renewal starts at the existing entitlement boundary when it is still in the future', function (): void {
    $period = (new ManualBillingPeriod)->next(
        Carbon::parse('2026-09-26 00:00:00', 'UTC'),
        'monthly',
        Carbon::parse('2026-09-20 14:00:00', 'UTC'),
    );

    expect($period['starts_at']->toISOString())->toBe('2026-09-26T00:00:00.000000Z')
        ->and($period['ends_at']->toISOString())->toBe('2026-10-26T00:00:00.000000Z');
});

test('an expired manual subscription starts a new period at settlement time', function (): void {
    $period = (new ManualBillingPeriod)->next(
        Carbon::parse('2026-08-01 00:00:00', 'UTC'),
        'yearly',
        Carbon::parse('2026-08-26 14:00:00', 'UTC'),
    );

    expect($period['starts_at']->toISOString())->toBe('2026-08-26T14:00:00.000000Z')
        ->and($period['ends_at']->toISOString())->toBe('2027-08-26T14:00:00.000000Z');
});
