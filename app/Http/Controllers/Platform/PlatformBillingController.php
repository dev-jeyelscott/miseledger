<?php

namespace App\Http\Controllers\Platform;

use App\Enums\BillingCollectionMethod;
use App\Enums\BillingPaymentMethod;
use App\Enums\BillingPaymentStatus;
use App\Enums\BillingProvider;
use App\Http\Controllers\Controller;
use App\Models\BillingPayment;
use App\Models\BillingSubscription;
use App\Support\Billing\PlanCatalog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use LogicException;

final class PlatformBillingController extends Controller
{
    /**
     * Render the read-only billing and captured-payment overview from local projections only.
     */
    public function index(): Response
    {
        $planLabels = $this->planLabels(new PlanCatalog);

        $subscriptionStats = BillingSubscription::query()
            ->selectRaw(
                'SUM(CASE WHEN livemode THEN 1 ELSE 0 END) AS live_count, '
                .'SUM(CASE WHEN NOT livemode THEN 1 ELSE 0 END) AS test_count',
            )
            ->first();

        $paymentSignals = BillingPayment::query()
            ->selectRaw(
                'currency, livemode, '
                .'SUM(CASE WHEN status = ? AND paid_at IS NOT NULL THEN 1 ELSE 0 END) AS captured_count, '
                .'SUM(CASE WHEN status = ? AND paid_at IS NOT NULL THEN amount ELSE 0 END) AS captured_amount_minor, '
                .'SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS failed_count',
                [
                    BillingPaymentStatus::Paid->value,
                    BillingPaymentStatus::Paid->value,
                    BillingPaymentStatus::Failed->value,
                ],
            )
            ->groupBy('currency', 'livemode')
            ->orderBy('currency')
            ->orderByDesc('livemode')
            ->get();

        $failedLiveCount = 0;
        $failedTestCount = 0;

        foreach ($paymentSignals as $signal) {
            $failedCount = (int) $signal->getAttribute('failed_count');

            if ($signal->livemode) {
                $failedLiveCount += $failedCount;
            } else {
                $failedTestCount += $failedCount;
            }
        }

        $planMix = BillingSubscription::query()
            ->selectRaw(
                'plan_code, provider, livemode, COUNT(*) AS subscription_count',
            )
            ->groupBy('plan_code', 'provider', 'livemode')
            ->orderBy('plan_code')
            ->orderBy('provider')
            ->orderByDesc('livemode')
            ->get()
            ->map(fn (BillingSubscription $subscription): array => [
                'planCode' => $subscription->plan_code,
                'planLabel' => $this->planLabel(
                    $subscription->plan_code,
                    $planLabels,
                ),
                'provider' => $subscription->provider->value,
                'providerLabel' => $this->providerLabel(
                    $subscription->provider,
                ),
                'livemode' => $subscription->livemode,
                'subscriptionCount' => (int) $subscription->getAttribute(
                    'subscription_count',
                ),
            ])
            ->values()
            ->all();

        return Inertia::render('admin/billing/index', [
            'metrics' => [
                'subscriptionProjections' => [
                    'live' => (int) ($subscriptionStats?->getAttribute(
                        'live_count',
                    ) ?? 0),
                    'test' => (int) ($subscriptionStats?->getAttribute(
                        'test_count',
                    ) ?? 0),
                ],
                'failedPaymentAttempts' => [
                    'live' => $failedLiveCount,
                    'test' => $failedTestCount,
                ],
            ],
            'paymentSignals' => $paymentSignals
                ->map(fn (BillingPayment $signal): array => [
                    'currency' => $signal->currency,
                    'livemode' => $signal->livemode,
                    'capturedCount' => (int) $signal->getAttribute(
                        'captured_count',
                    ),
                    'capturedAmountMinor' => $this->minorUnitString(
                        $signal->getAttribute('captured_amount_minor'),
                    ),
                    'failedCount' => (int) $signal->getAttribute(
                        'failed_count',
                    ),
                ])
                ->values()
                ->all(),
            'planMix' => $planMix,
        ]);
    }

    /**
     * Render the bounded local subscription-projection index without provider lookups.
     */
    public function subscriptions(Request $request): Response
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'provider' => [
                'nullable',
                Rule::enum(BillingProvider::class),
            ],
            'plan' => [
                'nullable',
                'string',
                'max:80',
                'regex:/^[a-z][a-z0-9_]*$/',
            ],
            'mode' => [
                'nullable',
                Rule::in(['live', 'test']),
            ],
            'per_page' => [
                'nullable',
                'integer',
                Rule::in([15, 25, 50]),
            ],
        ]);

        $search = trim((string) ($validated['search'] ?? ''));
        $provider = isset($validated['provider'])
            ? (string) $validated['provider']
            : null;
        $plan = isset($validated['plan'])
            ? (string) $validated['plan']
            : null;
        $mode = isset($validated['mode'])
            ? (string) $validated['mode']
            : null;
        $perPage = (int) ($validated['per_page'] ?? 25);
        $planLabels = $this->planLabels(new PlanCatalog);

        $query = BillingSubscription::query()
            ->select([
                'id',
                'organization_id',
                'provider',
                'type',
                'plan_code',
                'interval',
                'collection_method',
                'provider_status',
                'livemode',
                'current_period_ends_at',
                'ends_at',
                'updated_at',
            ])
            ->with('organization:id,name');

        if ($search !== '') {
            $searchPattern = '%'.$search.'%';

            $query->whereHas(
                'organization',
                static fn (Builder $organizationQuery): Builder => $organizationQuery->whereLike(
                    'name',
                    $searchPattern,
                ),
            );
        }

        if ($provider !== null) {
            $query->where('provider', $provider);
        }

        if ($plan !== null) {
            $query->where('plan_code', $plan);
        }

        if ($mode !== null) {
            $query->where('livemode', $mode === 'live');
        }

        $subscriptions = $query
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (BillingSubscription $subscription): array => [
                'id' => $subscription->id,
                'organization' => [
                    'id' => $subscription->organization->id,
                    'name' => $subscription->organization->name,
                ],
                'provider' => $subscription->provider->value,
                'providerLabel' => $this->providerLabel(
                    $subscription->provider,
                ),
                'type' => $subscription->type,
                'planCode' => $subscription->plan_code,
                'planLabel' => $this->planLabel(
                    $subscription->plan_code,
                    $planLabels,
                ),
                'interval' => $subscription->interval,
                'collectionMethod' => $subscription->collection_method->value,
                'collectionMethodLabel' => $this->collectionMethodLabel(
                    $subscription->collection_method,
                ),
                'providerStatus' => $subscription->provider_status,
                'livemode' => $subscription->livemode,
                'currentPeriodEndsAt' => $subscription
                    ->current_period_ends_at
                    ?->toIso8601String(),
                'endsAt' => $subscription->ends_at?->toIso8601String(),
                'updatedAt' => $subscription->updated_at?->toIso8601String(),
            ]);

        return Inertia::render('admin/billing/subscriptions', [
            'subscriptions' => $subscriptions->items(),
            'pagination' => [
                'current_page' => $subscriptions->currentPage(),
                'from' => $subscriptions->firstItem(),
                'last_page' => $subscriptions->lastPage(),
                'next_page_url' => $subscriptions->nextPageUrl(),
                'per_page' => $subscriptions->perPage(),
                'prev_page_url' => $subscriptions->previousPageUrl(),
                'to' => $subscriptions->lastItem(),
                'total' => $subscriptions->total(),
            ],
            'filters' => [
                'search' => $search,
                'provider' => $provider,
                'plan' => $plan,
                'mode' => $mode,
                'perPage' => $perPage,
            ],
            'filterOptions' => [
                'providers' => $this->providerOptions(),
                'plans' => $this->subscriptionPlanOptions($planLabels),
            ],
        ]);
    }

    /**
     * Render the bounded local payment-attempt index while retaining failed history.
     */
    public function payments(Request $request): Response
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'status' => [
                'nullable',
                Rule::enum(BillingPaymentStatus::class),
            ],
            'provider' => [
                'nullable',
                Rule::enum(BillingProvider::class),
            ],
            'currency' => [
                'nullable',
                'string',
                'regex:/^[A-Z]{3}$/',
            ],
            'mode' => [
                'nullable',
                Rule::in(['live', 'test']),
            ],
            'per_page' => [
                'nullable',
                'integer',
                Rule::in([15, 25, 50]),
            ],
        ]);

        $search = trim((string) ($validated['search'] ?? ''));
        $status = isset($validated['status'])
            ? (string) $validated['status']
            : null;
        $provider = isset($validated['provider'])
            ? (string) $validated['provider']
            : null;
        $currency = isset($validated['currency'])
            ? (string) $validated['currency']
            : null;
        $mode = isset($validated['mode'])
            ? (string) $validated['mode']
            : null;
        $perPage = (int) ($validated['per_page'] ?? 25);

        $query = BillingPayment::query()
            ->select([
                'id',
                'organization_id',
                'provider',
                'payment_method',
                'currency',
                'amount',
                'status',
                'livemode',
                'paid_at',
                'failed_at',
                'provider_error_code',
                'created_at',
            ])
            ->with('organization:id,name');

        if ($search !== '') {
            $searchPattern = '%'.$search.'%';

            $query->whereHas(
                'organization',
                static fn (Builder $organizationQuery): Builder => $organizationQuery->whereLike(
                    'name',
                    $searchPattern,
                ),
            );
        }

        if ($status !== null) {
            $query->where('status', $status);
        }

        if ($provider !== null) {
            $query->where('provider', $provider);
        }

        if ($currency !== null) {
            $query->where('currency', $currency);
        }

        if ($mode !== null) {
            $query->where('livemode', $mode === 'live');
        }

        $payments = $query
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (BillingPayment $payment): array => [
                'id' => $payment->id,
                'organization' => [
                    'id' => $payment->organization->id,
                    'name' => $payment->organization->name,
                ],
                'provider' => $payment->provider->value,
                'providerLabel' => $this->providerLabel(
                    $payment->provider,
                ),
                'paymentMethod' => $payment->payment_method->value,
                'paymentMethodLabel' => $this->paymentMethodLabel(
                    $payment->payment_method,
                ),
                'currency' => $payment->currency,
                'amountMinor' => $this->minorUnitString(
                    $payment->amount,
                ),
                'status' => $payment->status->value,
                'statusLabel' => $this->paymentStatusLabel(
                    $payment->status,
                ),
                'captured' => $payment->status === BillingPaymentStatus::Paid
                    && $payment->paid_at !== null,
                'livemode' => $payment->livemode,
                'paidAt' => $payment->paid_at?->toIso8601String(),
                'failedAt' => $payment->failed_at?->toIso8601String(),
                'providerErrorCode' => $payment->provider_error_code,
                'createdAt' => $payment->created_at?->toIso8601String(),
            ]);

        $currencies = BillingPayment::query()
            ->select('currency')
            ->distinct()
            ->orderBy('currency')
            ->pluck('currency')
            ->filter(static fn (mixed $value): bool => is_string($value))
            ->values()
            ->all();

        return Inertia::render('admin/billing/payments', [
            'payments' => $payments->items(),
            'pagination' => [
                'current_page' => $payments->currentPage(),
                'from' => $payments->firstItem(),
                'last_page' => $payments->lastPage(),
                'next_page_url' => $payments->nextPageUrl(),
                'per_page' => $payments->perPage(),
                'prev_page_url' => $payments->previousPageUrl(),
                'to' => $payments->lastItem(),
                'total' => $payments->total(),
            ],
            'filters' => [
                'search' => $search,
                'status' => $status,
                'provider' => $provider,
                'currency' => $currency,
                'mode' => $mode,
                'perPage' => $perPage,
            ],
            'filterOptions' => [
                'statuses' => $this->paymentStatusOptions(),
                'providers' => $this->providerOptions(),
                'currencies' => $currencies,
            ],
        ]);
    }

    /**
     * Build display labels keyed only by stable internal plan code.
     *
     * @return array<string, string>
     */
    private function planLabels(PlanCatalog $catalog): array
    {
        $labels = [];

        foreach ($catalog->all() as $plan) {
            $labels[$plan->code->value] = $plan->name;
        }

        return $labels;
    }

    /**
     * Return a display label without consulting any provider-owned plan identifier.
     *
     * @param  array<string, string>  $planLabels
     */
    private function planLabel(
        ?string $planCode,
        array $planLabels,
    ): string {
        if ($planCode === null || $planCode === '') {
            return 'Unmapped';
        }

        return $planLabels[$planCode] ?? Str::headline($planCode);
    }

    /**
     * Return the distinct persisted plan codes available to the subscription filter.
     *
     * @param  array<string, string>  $planLabels
     * @return list<array{value: string, label: string}>
     */
    private function subscriptionPlanOptions(array $planLabels): array
    {
        return array_values(
            BillingSubscription::query()
                ->select('plan_code')
                ->whereNotNull('plan_code')
                ->distinct()
                ->orderBy('plan_code')
                ->pluck('plan_code')
                ->filter(static fn (mixed $value): bool => is_string($value))
                ->map(fn (string $planCode): array => [
                    'value' => $planCode,
                    'label' => $this->planLabel($planCode, $planLabels),
                ])
                ->all(),
        );
    }

    /**
     * Return provider filter options from the supported provider enum.
     *
     * @return list<array{value: string, label: string}>
     */
    private function providerOptions(): array
    {
        return array_map(
            fn (BillingProvider $provider): array => [
                'value' => $provider->value,
                'label' => $this->providerLabel($provider),
            ],
            BillingProvider::cases(),
        );
    }

    /**
     * Return payment-status filter options from the durable application enum.
     *
     * @return list<array{value: string, label: string}>
     */
    private function paymentStatusOptions(): array
    {
        return array_map(
            fn (BillingPaymentStatus $status): array => [
                'value' => $status->value,
                'label' => $this->paymentStatusLabel($status),
            ],
            BillingPaymentStatus::cases(),
        );
    }

    /**
     * Preserve exact integer minor units as a decimal string for JSON/JavaScript safety.
     */
    private function minorUnitString(mixed $value): string
    {
        if (is_int($value)) {
            return (string) $value;
        }

        if (is_string($value) && preg_match('/^-?\d+$/', $value) === 1) {
            return $value;
        }

        throw new LogicException(
            'Billing minor-unit values must remain exact integers.',
        );
    }

    /**
     * Preserve official provider branding without exposing provider configuration.
     */
    private function providerLabel(BillingProvider $provider): string
    {
        return match ($provider) {
            BillingProvider::Stripe => 'Stripe',
            BillingProvider::PayMongo => 'PayMongo',
        };
    }

    /**
     * Convert the persisted collection method into platform-facing copy.
     */
    private function collectionMethodLabel(
        BillingCollectionMethod $method,
    ): string {
        return match ($method) {
            BillingCollectionMethod::Automatic => 'Automatic',
            BillingCollectionMethod::Manual => 'Manual',
        };
    }

    /**
     * Convert the persisted payment method into platform-facing copy.
     */
    private function paymentMethodLabel(BillingPaymentMethod $method): string
    {
        return match ($method) {
            BillingPaymentMethod::Card => 'Card',
            BillingPaymentMethod::Maya => 'Maya',
            BillingPaymentMethod::QrPh => 'QR Ph',
        };
    }

    /**
     * Convert the durable payment status into platform-facing copy.
     */
    private function paymentStatusLabel(BillingPaymentStatus $status): string
    {
        return match ($status) {
            BillingPaymentStatus::Pending => 'Pending',
            BillingPaymentStatus::AwaitingPayment => 'Awaiting payment',
            BillingPaymentStatus::Paid => 'Paid',
            BillingPaymentStatus::Failed => 'Failed',
            BillingPaymentStatus::Expired => 'Expired',
            BillingPaymentStatus::Cancelled => 'Cancelled',
        };
    }
}
