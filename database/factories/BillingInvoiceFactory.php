<?php

namespace Database\Factories;

use App\Enums\BillingInvoiceStatus;
use App\Models\BillingInvoice;
use App\Models\BillingSubscription;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<BillingInvoice>
 */
class BillingInvoiceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'billing_subscription_id' => BillingSubscription::factory(),
            'organization_id' => fn (array $attributes): int => BillingSubscription::query()
                ->whereKey($attributes['billing_subscription_id'])
                ->value('organization_id'),
            'provider' => fn (array $attributes): string => BillingSubscription::query()
                ->whereKey($attributes['billing_subscription_id'])
                ->value('provider'),
            'invoice_number' => 'INV-'.Str::upper((string) Str::ulid()),
            'plan_code' => 'starter',
            'billing_interval' => 'monthly',
            'currency' => 'PHP',
            'amount' => 49_900,
            'status' => BillingInvoiceStatus::Pending,
            'period_starts_at' => now(),
            'period_ends_at' => now()->addMonthNoOverflow(),
            'due_at' => now(),
            'paid_at' => null,
            'cancelled_at' => null,
        ];
    }
}
