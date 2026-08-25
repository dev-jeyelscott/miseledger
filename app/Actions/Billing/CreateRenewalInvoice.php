<?php

namespace App\Actions\Billing;

use App\Enums\BillingCollectionMethod;
use App\Enums\BillingInvoiceStatus;
use App\Enums\PlanCode;
use App\Models\BillingInvoice;
use App\Models\BillingSubscription;
use App\Support\Billing\ManualBillingPeriod;
use App\Support\Billing\PlanCatalog;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

final class CreateRenewalInvoice
{
    public function __construct(
        private readonly ManualBillingPeriod $period,
        private readonly PlanCatalog $planCatalog,
    ) {}

    public function handle(BillingSubscription $subscription, ?Carbon $activationPoint = null): BillingInvoice
    {
        return DB::transaction(function () use ($subscription, $activationPoint): BillingInvoice {
            $subscription = BillingSubscription::query()->lockForUpdate()->findOrFail($subscription->getKey());

            if ($subscription->collection_method !== BillingCollectionMethod::Manual
                || $subscription->plan_code === null
                || $subscription->interval === null
                || $subscription->cancelled_at !== null) {
                throw new RuntimeException('This subscription cannot be renewed manually.');
            }

            $plan = $this->planCatalog->get(PlanCode::from($subscription->plan_code));
            $amount = $plan?->manualAmount($subscription->interval);
            $currency = config('billing.currency');
            $currency = is_string($currency) ? mb_strtoupper($currency) : null;

            if ($plan === null || $amount === null || $currency === null || preg_match('/^[A-Z]{3}$/', $currency) !== 1) {
                throw new RuntimeException('Manual renewal pricing is unavailable.');
            }

            $period = $this->period->next(
                $subscription->current_period_ends_at,
                $subscription->interval,
                $activationPoint ?? now(),
            );

            $existing = BillingInvoice::query()
                ->where('billing_subscription_id', $subscription->getKey())
                ->where('period_starts_at', $period['starts_at'])
                ->where('period_ends_at', $period['ends_at'])
                ->lockForUpdate()
                ->first();

            if ($existing !== null) {
                return $existing;
            }

            return BillingInvoice::query()->create([
                'organization_id' => $subscription->organization_id,
                'billing_subscription_id' => $subscription->getKey(),
                'provider' => $subscription->provider,
                'invoice_number' => 'INV-'.Str::upper((string) Str::ulid()),
                'plan_code' => $subscription->plan_code,
                'billing_interval' => $subscription->interval,
                'currency' => $currency,
                'amount' => $amount,
                'status' => BillingInvoiceStatus::Pending,
                'period_starts_at' => $period['starts_at'],
                'period_ends_at' => $period['ends_at'],
                'due_at' => $period['starts_at'],
            ]);
        }, attempts: 3);
    }
}
