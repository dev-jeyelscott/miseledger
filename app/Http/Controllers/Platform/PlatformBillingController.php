<?php

namespace App\Http\Controllers\Platform;

use App\Enums\BillingCollectionMethod;
use App\Enums\BillingPaymentMethod;
use App\Enums\BillingPaymentStatus;
use App\Enums\BillingProvider;
use App\Enums\PlanCode;
use App\Http\Controllers\Controller;
use App\Models\BillingPayment;
use App\Models\BillingSubscription;
use App\Models\Organization;
use App\Support\Billing\PlanCatalog;
use App\Support\Billing\PlatformBillingPaymentSignals;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;
use LogicException;
use stdClass;

final class PlatformBillingController extends Controller
{
    /** @var list<string> */
    private const MODES = ['live', 'test'];

    /** @var list<string> */
    private const SUBSCRIPTION_INTERVALS = ['monthly', 'yearly'];

    /** @var list<string> */
    private const PROVIDER_STATUSES = [
        'pending',
        'incomplete',
        'incomplete_expired',
        'trialing',
        'active',
        'past_due',
        'unpaid',
        'paused',
        'canceled',
        'cancelled',
    ];

    /** @var list<string> */
    private const SUBSCRIPTION_SORTS = [
        'updated_at',
        'created_at',
        'next_billing_at',
        'current_period_ends_at',
        'ends_at',
    ];

    /** @var list<string> */
    private const PAYMENT_SORTS = [
        'created_at',
        'paid_at',
        'failed_at',
        'amount',
    ];

    /** @var list<string> */
    private const SORT_DIRECTIONS = ['asc', 'desc'];

    /** @var list<int> */
    private const PER_PAGE_OPTIONS = [15, 25, 50];

    /** Render the current UTC-month billing overview from local projections only. */
    public function index(
        Request $request,
        PlatformBillingPaymentSignals $paymentSignals,
    ): Response {
        $validated = $request->validate([
            'mode' => ['nullable', Rule::in(self::MODES)],
        ]);

        $mode = (string) ($validated['mode'] ?? 'live');
        $livemode = $mode === 'live';
        $signals = $paymentSignals->currentMonth($mode);
        $planLabels = $this->planLabels(new PlanCatalog);

        $planMix = BillingSubscription::query()
            ->where('livemode', $livemode)
            ->whereNotNull('plan_code')
            ->toBase()
            ->select('plan_code')
            ->selectRaw('COUNT(*) AS subscription_count')
            ->groupBy('plan_code')
            ->orderBy('plan_code')
            ->get()
            ->map(function (stdClass $row) use ($planLabels): ?array {
                $planCode = is_string($row->plan_code)
                    ? $row->plan_code
                    : null;

                if ($planCode === null || ! $this->isValidPlanCode($planCode)) {
                    return null;
                }

                return [
                    'planCode' => $planCode,
                    'planLabel' => $this->planLabel(
                        $planCode,
                        $planLabels,
                    ),
                    'subscriptionCount' => (int) $row->subscription_count,
                ];
            })
            ->filter()
            ->values()
            ->all();

        return Inertia::render('admin/billing/index', [
            'scope' => [
                'mode' => $signals['mode'],
                'period' => $signals['period'],
            ],
            'metrics' => [
                'subscriptionProjections' => BillingSubscription::query()
                    ->where('livemode', $livemode)
                    ->count(),
                'capturedPayments' => $signals['capturedPaymentCount'],
                'failedPaymentAttempts' => $signals['failedPaymentAttempts'],
            ],
            'paymentSignals' => $signals['capturedPayments'],
            'planMix' => $planMix,
        ]);
    }

    /** Render the bounded local subscription projection index. */
    public function subscriptions(Request $request): Response
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'provider' => ['nullable', Rule::enum(BillingProvider::class)],
            'plan' => [
                'nullable',
                'string',
                'max:80',
                'regex:/^[a-z][a-z0-9_]*$/',
            ],
            'mode' => ['nullable', Rule::in(self::MODES)],
            'interval' => [
                'nullable',
                Rule::in(self::SUBSCRIPTION_INTERVALS),
            ],
            'collection_method' => [
                'nullable',
                Rule::enum(BillingCollectionMethod::class),
            ],
            'provider_status' => [
                'nullable',
                Rule::in(self::PROVIDER_STATUSES),
            ],
            'sort' => [
                'nullable',
                Rule::in(self::SUBSCRIPTION_SORTS),
            ],
            'direction' => [
                'nullable',
                Rule::in(self::SORT_DIRECTIONS),
            ],
            'per_page' => [
                'nullable',
                'integer',
                Rule::in(self::PER_PAGE_OPTIONS),
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
        $interval = isset($validated['interval'])
            ? (string) $validated['interval']
            : null;
        $collectionMethod = isset($validated['collection_method'])
            ? (string) $validated['collection_method']
            : null;
        $providerStatus = isset($validated['provider_status'])
            ? (string) $validated['provider_status']
            : null;
        $sort = (string) ($validated['sort'] ?? 'updated_at');
        $direction = (string) ($validated['direction'] ?? 'desc');
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
                'trial_ends_at',
                'current_period_ends_at',
                'next_billing_at',
                'ends_at',
                'cancelled_at',
                'created_at',
                'updated_at',
            ])
            ->with('organization:id,name');

        if ($search !== '') {
            $query->whereIn(
                'organization_id',
                Organization::query()
                    ->select('id')
                    ->whereLike('name', '%'.$search.'%'),
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

        if ($interval !== null) {
            $query->where('interval', $interval);
        }

        if ($collectionMethod !== null) {
            $query->where('collection_method', $collectionMethod);
        }

        if ($providerStatus !== null) {
            $query->where('provider_status', $providerStatus);
        }

        $this->applySubscriptionSort($query, $sort, $direction);

        $subscriptions = $query
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
                'trialEndsAt' => $subscription->trial_ends_at
                    ?->toIso8601String(),
                'currentPeriodEndsAt' => $subscription
                    ->current_period_ends_at
                    ?->toIso8601String(),
                'nextBillingAt' => $subscription->next_billing_at
                    ?->toIso8601String(),
                'cancelledAt' => $subscription->cancelled_at
                    ?->toIso8601String(),
                'endsAt' => $subscription->ends_at?->toIso8601String(),
                'createdAt' => $subscription->created_at?->toIso8601String(),
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
                'interval' => $interval,
                'collectionMethod' => $collectionMethod,
                'providerStatus' => $providerStatus,
                'sort' => $sort,
                'direction' => $direction,
                'perPage' => $perPage,
            ],
            'filterOptions' => [
                'providers' => $this->providerOptions(),
                'plans' => $this->subscriptionPlanOptions($planLabels),
                'intervals' => $this->intervalOptions(),
                'collectionMethods' => $this->collectionMethodOptions(),
                'providerStatuses' => $this->providerStatusOptions(),
            ],
        ]);
    }

    /** Render the bounded payment-attempt index while retaining failed history. */
    public function payments(Request $request): Response
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'status' => [
                'nullable',
                Rule::enum(BillingPaymentStatus::class),
            ],
            'provider' => ['nullable', Rule::enum(BillingProvider::class)],
            'currency' => [
                'nullable',
                'string',
                'regex:/^[A-Z]{3}$/',
            ],
            'mode' => ['nullable', Rule::in(self::MODES)],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
            'sort' => ['nullable', Rule::in(self::PAYMENT_SORTS)],
            'direction' => [
                'nullable',
                Rule::in(self::SORT_DIRECTIONS),
            ],
            'per_page' => [
                'nullable',
                'integer',
                Rule::in(self::PER_PAGE_OPTIONS),
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
        $from = isset($validated['from'])
            ? (string) $validated['from']
            : null;
        $to = isset($validated['to'])
            ? (string) $validated['to']
            : null;
        $sort = (string) ($validated['sort'] ?? 'created_at');
        $direction = (string) ($validated['direction'] ?? 'desc');
        $perPage = (int) ($validated['per_page'] ?? 25);

        if ($from !== null && $to !== null && $from > $to) {
            throw ValidationException::withMessages([
                'to' => 'The to date must be on or after the from date.',
            ]);
        }

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
                'expires_at',
                'paid_at',
                'failed_at',
                'provider_error_code',
                'created_at',
            ])
            ->with('organization:id,name');

        if ($search !== '') {
            $query->whereIn(
                'organization_id',
                Organization::query()
                    ->select('id')
                    ->whereLike('name', '%'.$search.'%'),
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

        if ($from !== null) {
            $query->where(
                'created_at',
                '>=',
                $this->utcDateStart($from),
            );
        }

        if ($to !== null) {
            $query->where(
                'created_at',
                '<',
                $this->utcDateStart($to)->addDay(),
            );
        }

        $this->applyPaymentSort($query, $sort, $direction);

        $payments = $query
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
                'amountMinor' => $this->minorUnitString($payment->amount),
                'status' => $payment->status->value,
                'statusLabel' => $this->paymentStatusLabel($payment->status),
                'captured' => $payment->status === BillingPaymentStatus::Paid
                    && $payment->paid_at !== null,
                'livemode' => $payment->livemode,
                'expiresAt' => $payment->expires_at?->toIso8601String(),
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
                'from' => $from,
                'to' => $to,
                'sort' => $sort,
                'direction' => $direction,
                'perPage' => $perPage,
            ],
            'filterOptions' => [
                'statuses' => $this->paymentStatusOptions(),
                'providers' => $this->providerOptions(),
                'currencies' => $currencies,
            ],
        ]);
    }

    /** Build display labels keyed only by stable internal plan code. */
    private function planLabels(PlanCatalog $catalog): array
    {
        $labels = [];

        foreach ($catalog->all() as $plan) {
            $labels[$plan->code->value] = $plan->name;
        }

        return $labels;
    }

    /** Return a label without consulting a provider-owned plan identifier. */
    private function planLabel(?string $planCode, array $planLabels): string
    {
        if ($planCode === null || $planCode === '') {
            return 'Unmapped';
        }

        if (! $this->isValidPlanCode($planCode)) {
            return 'Invalid plan code';
        }

        return $planLabels[$planCode] ?? Str::headline($planCode);
    }

    /** Validate persisted plan identity through the stable PlanCode boundary. */
    private function isValidPlanCode(string $planCode): bool
    {
        try {
            PlanCode::from($planCode);

            return true;
        } catch (InvalidArgumentException) {
            return false;
        }
    }

    /** Return valid persisted plan codes available to the filter. */
    private function subscriptionPlanOptions(array $planLabels): array
    {
        return array_values(
            BillingSubscription::query()
                ->select('plan_code')
                ->whereNotNull('plan_code')
                ->distinct()
                ->orderBy('plan_code')
                ->pluck('plan_code')
                ->filter(
                    fn (mixed $value): bool => is_string($value)
                        && $this->isValidPlanCode($value),
                )
                ->map(fn (string $planCode): array => [
                    'value' => $planCode,
                    'label' => $this->planLabel($planCode, $planLabels),
                ])
                ->all(),
        );
    }

    /** Return provider filter options from the supported provider enum. */
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

    /** Return supported billing intervals for projection filtering. */
    private function intervalOptions(): array
    {
        return array_map(
            static fn (string $interval): array => [
                'value' => $interval,
                'label' => Str::headline($interval),
            ],
            self::SUBSCRIPTION_INTERVALS,
        );
    }

    /** Return collection-method filter options from the durable enum. */
    private function collectionMethodOptions(): array
    {
        return array_map(
            fn (BillingCollectionMethod $method): array => [
                'value' => $method->value,
                'label' => $this->collectionMethodLabel($method),
            ],
            BillingCollectionMethod::cases(),
        );
    }

    /** Return the bounded provider-status vocabulary accepted by the filter. */
    private function providerStatusOptions(): array
    {
        return array_map(
            static fn (string $status): array => [
                'value' => $status,
                'label' => Str::headline($status),
            ],
            self::PROVIDER_STATUSES,
        );
    }

    /** Return payment-status filter options from the durable enum. */
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

    /** Apply deterministic subscription sorting with nullable dates last. */
    private function applySubscriptionSort(
        Builder $query,
        string $sort,
        string $direction,
    ): void {
        $nullableColumn = match ($sort) {
            'next_billing_at' => 'next_billing_at',
            'current_period_ends_at' => 'current_period_ends_at',
            'ends_at' => 'ends_at',
            default => null,
        };

        if ($nullableColumn !== null) {
            $sqlDirection = $direction === 'asc' ? 'ASC' : 'DESC';

            $query->orderByRaw(
                "{$nullableColumn} {$sqlDirection} NULLS LAST",
            );
        } else {
            $query->orderBy($sort, $direction);
        }

        $query->orderBy('id', $direction);
    }

    /** Apply deterministic payment sorting with nullable evidence dates last. */
    private function applyPaymentSort(
        Builder $query,
        string $sort,
        string $direction,
    ): void {
        $nullableColumn = match ($sort) {
            'paid_at' => 'paid_at',
            'failed_at' => 'failed_at',
            default => null,
        };

        if ($nullableColumn !== null) {
            $sqlDirection = $direction === 'asc' ? 'ASC' : 'DESC';

            $query->orderByRaw(
                "{$nullableColumn} {$sqlDirection} NULLS LAST",
            );
        } else {
            $query->orderBy($sort, $direction);
        }

        $query->orderBy('id', $direction);
    }

    /** Convert one validated UTC calendar date to its inclusive start. */
    private function utcDateStart(string $date): Carbon
    {
        return Carbon::parse($date, 'UTC')->startOfDay();
    }

    /** Preserve exact integer minor units as a decimal string. */
    private function minorUnitString(mixed $value): string
    {
        if (is_int($value)) {
            return (string) $value;
        }

        if (is_string($value) && preg_match('/^\d+$/', $value) === 1) {
            return $value;
        }

        throw new LogicException(
            'Billing minor-unit values must remain exact integers.',
        );
    }

    /** Preserve official provider branding. */
    private function providerLabel(BillingProvider $provider): string
    {
        return match ($provider) {
            BillingProvider::Stripe => 'Stripe',
            BillingProvider::PayMongo => 'PayMongo',
        };
    }

    private function collectionMethodLabel(
        BillingCollectionMethod $method,
    ): string {
        return match ($method) {
            BillingCollectionMethod::Automatic => 'Automatic',
            BillingCollectionMethod::Manual => 'Manual',
        };
    }

    private function paymentMethodLabel(BillingPaymentMethod $method): string
    {
        return match ($method) {
            BillingPaymentMethod::Card => 'Card',
            BillingPaymentMethod::Maya => 'Maya',
            BillingPaymentMethod::QrPh => 'QR Ph',
        };
    }

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
