<?php

namespace App\Support\Billing;

use Carbon\CarbonInterface;

final readonly class ManualBillingPeriod
{
    /** @return array{starts_at: CarbonInterface, ends_at: CarbonInterface} */
    public function next(?CarbonInterface $currentPeriodEndsAt, string $interval, CarbonInterface $activationPoint): array
    {
        $startsAt = $currentPeriodEndsAt?->greaterThan($activationPoint) === true
            ? $currentPeriodEndsAt->copy()
            : $activationPoint->copy();

        $endsAt = match ($interval) {
            'monthly' => $startsAt->copy()->addMonthNoOverflow(),
            'yearly' => $startsAt->copy()->addYearNoOverflow(),
            default => throw new \InvalidArgumentException('Unsupported manual billing interval.'),
        };

        return ['starts_at' => $startsAt, 'ends_at' => $endsAt];
    }
}
