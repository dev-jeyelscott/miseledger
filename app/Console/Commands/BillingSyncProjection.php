<?php

namespace App\Console\Commands;

use App\Actions\Billing\SynchronizeStripeBillingProjection;
use App\Models\Organization;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Laravel\Cashier\Subscription;

final class BillingSyncProjection extends Command
{
    public function __construct(private readonly SynchronizeStripeBillingProjection $synchronize)
    {
        parent::__construct();
    }

    protected $signature = 'billing:sync-projection
        {--chunk=100 : Number of Cashier subscriptions to process per database batch}';

    protected $description =
        'Backfill the durable billing_customers/billing_subscriptions projection from Cashier\'s local subscription state. Reads local data only; issues no provider API calls.';

    public function handle(): int
    {
        $synchronized = 0;

        Subscription::query()->chunkById(
            $this->chunkSize(),
            function (Collection $batch) use (&$synchronized): void {
                $organizations = Organization::query()
                    ->whereIn('id', $batch->pluck('organization_id')->unique())
                    ->get()
                    ->keyBy('id');

                foreach ($batch as $subscription) {
                    $organization = $organizations->get($subscription->organization_id);

                    if ($organization === null) {
                        continue;
                    }

                    // No webhook payload is available for a local-only
                    // bootstrap, so fields Cashier does not persist
                    // (current_period_end, cancel_at, canceled_at,
                    // livemode) are left null/false rather than fabricated
                    // or fetched via a Stripe API call.
                    $this->synchronize->handle($organization, $subscription);
                    $synchronized++;
                }
            },
        );

        $this->line("Billing projection sync completed: {$synchronized} subscription(s) processed.");

        return self::SUCCESS;
    }

    private function chunkSize(): int
    {
        return max(1, (int) $this->option('chunk'));
    }
}
