<?php

namespace App\Support\Billing;

use App\Enums\BillingPaymentStatus;
use App\Models\BillingPayment;
use Illuminate\Support\Carbon;
use InvalidArgumentException;
use LogicException;
use stdClass;

final class PlatformBillingPaymentSignals
{
    /**
     * Return current-month payment signals for one explicitly selected billing mode.
     *
     * @return array{
     *     mode: string,
     *     period: array{
     *         from: string,
     *         toExclusive: string,
     *         fromDate: string,
     *         toDate: string,
     *         timezone: string
     *     },
     *     capturedPayments: list<array{
     *         currency: string,
     *         capturedCount: int,
     *         capturedAmountMinor: string
     *     }>,
     *     capturedPaymentCount: int,
     *     failedPaymentAttempts: int
     * }
     */
    public function currentMonth(string $mode): array
    {
        $livemode = match ($mode) {
            'live' => true,
            'test' => false,
            default => throw new InvalidArgumentException(
                "Unsupported billing mode [{$mode}].",
            ),
        };

        $from = now('UTC')->startOfMonth();
        $toExclusive = $from->copy()->addMonth();

        $capturedPayments = $this->capturedPayments(
            $livemode,
            $from,
            $toExclusive,
        );

        return [
            'mode' => $mode,
            'period' => [
                'from' => $from->toIso8601String(),
                'toExclusive' => $toExclusive->toIso8601String(),
                'fromDate' => $from->toDateString(),
                'toDate' => $toExclusive->copy()->subDay()->toDateString(),
                'timezone' => 'UTC',
            ],
            'capturedPayments' => $capturedPayments,
            'capturedPaymentCount' => (int) array_sum(
                array_column($capturedPayments, 'capturedCount'),
            ),
            'failedPaymentAttempts' => $this->failedPaymentAttempts(
                $livemode,
                $from,
                $toExclusive,
            ),
        ];
    }

    /**
     * Aggregate only paid rows with confirmed paid timestamps, grouped by currency.
     *
     * @return list<array{
     *     currency: string,
     *     capturedCount: int,
     *     capturedAmountMinor: string
     * }>
     */
    private function capturedPayments(
        bool $livemode,
        Carbon $from,
        Carbon $toExclusive,
    ): array {
        return BillingPayment::query()
            ->where('status', BillingPaymentStatus::Paid->value)
            ->whereNotNull('paid_at')
            ->where('livemode', $livemode)
            ->where('paid_at', '>=', $from)
            ->where('paid_at', '<', $toExclusive)
            ->toBase()
            ->select('currency')
            ->selectRaw('COUNT(*) AS captured_count')
            ->selectRaw(
                'CAST(SUM(amount) AS TEXT) AS captured_amount_minor',
            )
            ->groupBy('currency')
            ->orderBy('currency')
            ->get()
            ->map(
                static fn (stdClass $row): array => [
                    'currency' => (string) $row->currency,
                    'capturedCount' => (int) $row->captured_count,
                    'capturedAmountMinor' => self::minorUnitString(
                        $row->captured_amount_minor,
                    ),
                ],
            )
            ->values()
            ->all();
    }

    /** Count only failed rows with confirmed failure timestamps in the selected scope. */
    private function failedPaymentAttempts(
        bool $livemode,
        Carbon $from,
        Carbon $toExclusive,
    ): int {
        return BillingPayment::query()
            ->where('status', BillingPaymentStatus::Failed->value)
            ->whereNotNull('failed_at')
            ->where('livemode', $livemode)
            ->where('failed_at', '>=', $from)
            ->where('failed_at', '<', $toExclusive)
            ->count();
    }

    /** Preserve exact aggregate minor units as a decimal string. */
    private static function minorUnitString(mixed $value): string
    {
        if (is_int($value)) {
            return (string) $value;
        }

        if (is_string($value) && preg_match('/^\d+$/', $value) === 1) {
            return $value;
        }

        throw new LogicException(
            'Billing aggregate minor-unit values must remain exact integers.',
        );
    }
}
