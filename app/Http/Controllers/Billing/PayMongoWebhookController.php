<?php

namespace App\Http\Controllers\Billing;

use App\Actions\Billing\ProcessOrganizationBillingWebhookEffect;
use App\Actions\Billing\SettlePayMongoPayment;
use App\Enums\BillingLifecycleEvent;
use App\Enums\BillingProvider;
use App\Http\Controllers\Controller;
use App\Models\BillingCustomer;
use App\Models\BillingPayment;
use App\Models\BillingSubscription;
use App\Models\Organization;
use App\Support\Billing\BillingObservability;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;

/**
 * Process verified PayMongo provider callbacks into controlled billing projections.
 *
 * @phpstan-type PaymentEvent array{
 *     resource: 'payment',
 *     event_id: string,
 *     payment_id: string,
 *     payment_intent_id: string,
 *     amount: int,
 *     currency: string,
 *     livemode: bool,
 *     paid_at: Carbon|null,
 *     lifecycle_event: BillingLifecycleEvent,
 *     audit_action: string
 * }
 * @phpstan-type SubscriptionEvent array{
 *     resource: 'subscription',
 *     event_id: string,
 *     customer_id: string,
 *     subscription_id: string,
 *     livemode: bool,
 *     lifecycle_event: BillingLifecycleEvent,
 *     audit_action: string,
 *     subscription_updates: array<string, mixed>
 * }
 */
final class PayMongoWebhookController extends Controller
{
    public function __construct(
        private readonly ProcessOrganizationBillingWebhookEffect $process,
        private readonly SettlePayMongoPayment $settlePayment,
        private readonly BillingObservability $observability,
    ) {}

    /** Process one already-authenticated PayMongo webhook request. */
    public function __invoke(Request $request): Response
    {
        try {
            return $this->processWebhook($request);
        } catch (\Throwable $exception) {
            $payload = $request->json()->all();

            $eventId = data_get($payload, 'data.id');
            $status = data_get(
                $payload,
                'data.attributes.data.attributes.status',
            );
            $livemode = data_get($payload, 'data.attributes.livemode');

            $this->observability->webhookFailure(
                null,
                BillingProvider::PayMongo,
                $exception,
                is_string($eventId) ? $eventId : null,
                is_string($status) ? $status : null,
                is_bool($livemode) ? $livemode : null,
            );

            throw $exception;
        }
    }

    /** Parse and route a verified webhook according to its normalized event shape. */
    private function processWebhook(Request $request): Response
    {
        $payload = json_decode($request->getContent(), true);

        if (! is_array($payload)) {
            abort(422, 'Invalid PayMongo webhook payload.');
        }

        $event = $this->event($payload);

        if ($event === null) {
            return response()->noContent();
        }

        if ($event['livemode'] !== (config('billing.providers.paymongo.mode') === 'live')) {
            abort(
                403,
                'PayMongo webhook mode does not match this environment.',
            );
        }

        if ($event['resource'] === 'payment') {
            return $this->processPaymentEvent($event);
        }

        $customer = BillingCustomer::query()
            ->where('provider', BillingProvider::PayMongo)
            ->where('external_customer_id', $event['customer_id'])
            ->where('livemode', $event['livemode'])
            ->first();

        $subscription = $customer === null
            ? null
            : BillingSubscription::query()
                ->where('organization_id', $customer->organization_id)
                ->where('billing_customer_id', $customer->id)
                ->where('provider', BillingProvider::PayMongo)
                ->where(
                    'external_subscription_id',
                    $event['subscription_id'],
                )
                ->where('livemode', $event['livemode'])
                ->first();

        if ($subscription === null) {
            return response()->noContent();
        }

        $this->process->handle(
            BillingProvider::PayMongo,
            $event['event_id'],
            $event['customer_id'],
            $event['lifecycle_event'],
            $event['audit_action'],
            function (Organization $organization) use ($customer, $event): void {
                $projection = BillingSubscription::query()
                    ->lockForUpdate()
                    ->where('organization_id', $organization->id)
                    ->where('billing_customer_id', $customer->id)
                    ->where('provider', BillingProvider::PayMongo)
                    ->where(
                        'external_subscription_id',
                        $event['subscription_id'],
                    )
                    ->where('livemode', $event['livemode'])
                    ->firstOrFail();

                $updates = $event['subscription_updates'];

                if ($updates !== []) {
                    $projection->update($updates);
                }
            },
        );

        return response()->noContent();
    }

    /**
     * Apply one manual-payment webhook to its exact local payment attempt.
     *
     * @param  PaymentEvent  $event
     */
    private function processPaymentEvent(array $event): Response
    {
        $payment = BillingPayment::query()
            ->where('provider', BillingProvider::PayMongo)
            ->where(
                'external_payment_intent_id',
                $event['payment_intent_id'],
            )
            ->where('livemode', $event['livemode'])
            ->first();

        if ($payment === null) {
            return response()->noContent();
        }

        $subscription = $payment->billingInvoice->billingSubscription;
        $customer = $subscription->billingCustomer;

        $this->process->handle(
            BillingProvider::PayMongo,
            $event['event_id'],
            $customer->external_customer_id,
            $event['lifecycle_event'],
            $event['audit_action'],
            function (Organization $_organization) use ($event, $payment): void {
                if ($event['lifecycle_event'] === BillingLifecycleEvent::Recovered) {
                    $this->settlePayment->handle(
                        $payment,
                        $event['payment_id'],
                        $event['amount'],
                        $event['currency'],
                        $event['livemode'],
                        $event['paid_at'],
                    );

                    return;
                }

                BillingPayment::query()
                    ->whereKey($payment->id)
                    ->whereIn('status', ['pending', 'awaiting_payment'])
                    ->update([
                        'status' => $event['lifecycle_event']
                            === BillingLifecycleEvent::PaymentExpired
                                ? 'expired'
                                : 'failed',
                        'failed_at' => now(),
                    ]);
            },
        );

        return response()->noContent();
    }

    /**
     * Normalize a supported PayMongo webhook into one controlled local shape.
     *
     * @param  array<string, mixed>  $payload
     * @return PaymentEvent|SubscriptionEvent|null
     */
    private function event(array $payload): ?array
    {
        $event = $payload['data'] ?? null;
        $attributes = is_array($event)
            ? $event['attributes'] ?? null
            : null;
        $resource = is_array($attributes)
            ? $attributes['data'] ?? null
            : null;
        $resourceAttributes = is_array($resource)
            ? $resource['attributes'] ?? null
            : null;

        if (! is_array($event)
            || ! is_array($attributes)
            || ! is_array($resource)
            || ! is_array($resourceAttributes)
            || ($event['type'] ?? null) !== 'event'
            || ! is_string($event['id'] ?? null)
            || ! is_string($attributes['type'] ?? null)
            || ! is_bool($attributes['livemode'] ?? null)) {
            abort(422, 'Invalid PayMongo webhook payload.');
        }

        $eventId = $event['id'];
        $type = $attributes['type'];

        return match ($type) {
            'payment.paid',
            'payment.failed',
            'qrph.expired' => $this->paymentEvent(
                $eventId,
                $attributes,
                $resource,
                $resourceAttributes,
            ),
            'subscription.activated',
            'subscription.past_due',
            'subscription.unpaid',
            'subscription.cancelled',
            'subscription.updated' => $this->subscriptionEvent(
                $eventId,
                $attributes,
                $resource,
                $resourceAttributes,
            ),
            'subscription.invoice.paid',
            'subscription.invoice.payment_failed' => $this->invoiceEvent(
                $eventId,
                $attributes,
                $resource,
                $resourceAttributes,
            ),
            default => null,
        };
    }

    /**
     * Normalize a PayMongo manual payment event.
     *
     * @param  array<string, mixed>  $attributes
     * @param  array<string, mixed>  $resource
     * @param  array<string, mixed>  $payment
     * @return PaymentEvent
     */
    private function paymentEvent(
        string $eventId,
        array $attributes,
        array $resource,
        array $payment,
    ): array {
        $paid = $attributes['type'] === 'payment.paid';
        $paymentIntentId = $payment['payment_intent_id'] ?? null;
        $amount = $payment['amount'] ?? null;
        $currency = $payment['currency'] ?? null;

        if (($resource['type'] ?? null) !== 'payment'
            || ! is_string($resource['id'] ?? null)
            || ! is_string($paymentIntentId)
            || ! str_starts_with($paymentIntentId, 'pi_')
            || ! is_int($amount)
            || $amount <= 0
            || ! is_string($currency)
            || preg_match('/^[A-Z]{3}$/', $currency) !== 1
            || ! is_bool($payment['livemode'] ?? null)
            || $payment['livemode'] !== $attributes['livemode']
            || ($paid && ($payment['status'] ?? null) !== 'paid')) {
            abort(
                422,
                'Invalid PayMongo payment webhook payload.',
            );
        }

        return [
            'resource' => 'payment',
            'event_id' => $eventId,
            'payment_id' => $resource['id'],
            'payment_intent_id' => $paymentIntentId,
            'amount' => $amount,
            'currency' => $currency,
            'livemode' => $attributes['livemode'],
            'paid_at' => $paid
                ? $this->timestamp($payment['paid_at'] ?? null)
                : null,
            'lifecycle_event' => $paid
                ? BillingLifecycleEvent::Recovered
                : (
                    $attributes['type'] === 'qrph.expired'
                        ? BillingLifecycleEvent::PaymentExpired
                        : BillingLifecycleEvent::PaymentFailed
                ),
            'audit_action' => $paid
                ? 'billing.payment.settled'
                : (
                    $attributes['type'] === 'qrph.expired'
                        ? 'billing.payment.expired'
                        : 'billing.payment.failed'
                ),
        ];
    }

    /**
     * Normalize one PayMongo subscription lifecycle event.
     *
     * @param  array<string, mixed>  $attributes
     * @param  array<string, mixed>  $resource
     * @param  array<string, mixed>  $subscription
     * @return SubscriptionEvent|null
     */
    private function subscriptionEvent(
        string $eventId,
        array $attributes,
        array $resource,
        array $subscription,
    ): ?array {
        $status = $subscription['status'] ?? null;

        if (($resource['type'] ?? null) !== 'subscription'
            || ! is_string($resource['id'] ?? null)
            || ! is_string($subscription['customer_id'] ?? null)
            || ! is_bool($subscription['livemode'] ?? null)
            || $subscription['livemode'] !== $attributes['livemode']
            || ! is_string($status)) {
            abort(422, 'Invalid PayMongo webhook payload.');
        }

        $lifecycleEvent = match ($attributes['type']) {
            'subscription.activated' => $status === 'active'
                ? BillingLifecycleEvent::SubscriptionStarted
                : null,
            'subscription.past_due' => $status === 'past_due'
                ? BillingLifecycleEvent::PaymentFailed
                : null,
            'subscription.unpaid' => $status === 'unpaid'
                ? BillingLifecycleEvent::PaymentFailed
                : null,
            'subscription.cancelled' => in_array(
                $status,
                ['cancelled', 'canceled'],
                true,
            )
                ? BillingLifecycleEvent::ScheduledCancellation
                : null,
            'subscription.updated' => match ($status) {
                'active' => BillingLifecycleEvent::Recovered,
                'past_due',
                'unpaid' => BillingLifecycleEvent::PaymentFailed,
                'cancelled',
                'canceled' => BillingLifecycleEvent::ScheduledCancellation,
                default => null,
            },
            default => null,
        };

        if ($lifecycleEvent === null) {
            return null;
        }

        return [
            'resource' => 'subscription',
            'event_id' => $eventId,
            'customer_id' => $subscription['customer_id'],
            'subscription_id' => $resource['id'],
            'livemode' => $attributes['livemode'],
            'lifecycle_event' => $lifecycleEvent,
            'audit_action' => match ($lifecycleEvent) {
                BillingLifecycleEvent::SubscriptionStarted =>
                    'billing.subscription.started',
                BillingLifecycleEvent::Recovered =>
                    'billing.payment.recovered',
                BillingLifecycleEvent::ScheduledCancellation =>
                    'billing.subscription.cancelled',
                default => 'billing.subscription.past_due',
            },
            'subscription_updates' => [
                'provider_status' => $status,
                'livemode' => $attributes['livemode'],
                'next_billing_at' => in_array(
                    $status,
                    ['cancelled', 'canceled'],
                    true,
                )
                    ? null
                    : $this->date(
                        $subscription['next_billing_schedule'] ?? null,
                    ),
                'ends_at' => in_array(
                    $status,
                    ['cancelled', 'canceled'],
                    true,
                )
                    ? $this->date(
                        $subscription['next_billing_schedule'] ?? null,
                    )
                    : null,
                'cancelled_at' => $this->timestamp(
                    $subscription['cancelled_at'] ?? null,
                ),
            ],
        ];
    }

    /**
     * Normalize a provider subscription-invoice lifecycle event.
     *
     * @param  array<string, mixed>  $attributes
     * @param  array<string, mixed>  $resource
     * @param  array<string, mixed>  $invoice
     * @return SubscriptionEvent
     */
    private function invoiceEvent(
        string $eventId,
        array $attributes,
        array $resource,
        array $invoice,
    ): array {
        $paid = $attributes['type'] === 'subscription.invoice.paid';

        if (($resource['type'] ?? null) !== 'invoice'
            || ! is_string($resource['id'] ?? null)
            || ! is_string($invoice['customer_id'] ?? null)
            || ! is_string($invoice['resource_id'] ?? null)
            || ! is_bool($invoice['livemode'] ?? null)
            || $invoice['livemode'] !== $attributes['livemode']
            || ($invoice['status'] ?? null) !== ($paid ? 'paid' : 'open')) {
            abort(422, 'Invalid PayMongo webhook payload.');
        }

        return [
            'resource' => 'subscription',
            'event_id' => $eventId,
            'customer_id' => $invoice['customer_id'],
            'subscription_id' => $invoice['resource_id'],
            'livemode' => $attributes['livemode'],
            'lifecycle_event' => $paid
                ? BillingLifecycleEvent::Recovered
                : BillingLifecycleEvent::PaymentFailed,
            'audit_action' => $paid
                ? 'billing.payment.recovered'
                : 'billing.subscription.past_due',
            'subscription_updates' => [],
        ];
    }

    /** Parse a provider date-only value into UTC. */
    private function date(mixed $value): ?Carbon
    {
        return is_string($value)
            && preg_match('/^\d{4}-\d{2}-\d{2}$/D', $value) === 1
                ? Carbon::createFromFormat('!Y-m-d', $value, 'UTC')
                : null;
    }

    /** Parse a provider Unix timestamp into UTC. */
    private function timestamp(mixed $value): ?Carbon
    {
        return is_int($value)
            || (is_string($value) && ctype_digit($value))
                ? Carbon::createFromTimestampUTC((int) $value)
                : null;
    }
}
