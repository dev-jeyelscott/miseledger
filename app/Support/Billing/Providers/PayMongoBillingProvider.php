<?php

namespace App\Support\Billing\Providers;

use App\Enums\BillingProvider as BillingProviderEnum;
use App\Models\BillingCustomer;
use App\Models\BillingSubscription;
use App\Models\Organization;
use App\Models\User;
use App\Support\Billing\PlanCatalog;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use RuntimeException;

final class PayMongoBillingProvider implements BillingProvider
{
    public function __construct(
        private readonly PayMongoClient $client,
        private readonly PlanCatalog $planCatalog,
    ) {}

    public function identity(): BillingProviderEnum
    {
        return BillingProviderEnum::PayMongo;
    }

    /** @param array<string, string> $metadata */
    public function startCheckout(
        Organization $organization,
        string $externalPriceId,
        string $successUrl,
        string $cancelUrl,
        array $metadata,
        User $actor,
    ): BillingCheckoutOutcome {
        $customer = $this->ensureCustomer($organization, $actor);
        $subscription = $this->createSubscription($organization, $customer, $externalPriceId);
        $paymentIntentId = $this->paymentIntentId($subscription);
        $paymentIntent = $this->client->get('retrieve_subscription_payment_intent', "/payment_intents/{$paymentIntentId}", (string) $organization->getKey());
        $clientKey = $this->paymentIntentClientKey($paymentIntent, $paymentIntentId);
        $this->persistSubscription($organization, $customer, $externalPriceId, $subscription);

        $publicKey = config('billing.providers.paymongo.public_key');
        $apiBaseUrl = config('billing.providers.paymongo.api_base_url');

        if (! is_string($publicKey) || ! str_starts_with($publicKey, 'pk_') || ! is_string($apiBaseUrl) || ! filter_var($apiBaseUrl, FILTER_VALIDATE_URL)) {
            throw new RuntimeException('PayMongo checkout payment configuration is unavailable.');
        }

        return BillingCheckoutOutcome::payment([
            'payment_intent_id' => $paymentIntentId,
            'client_key' => $clientKey,
            'public_key' => $publicKey,
            'api_base_url' => rtrim($apiBaseUrl, '/'),
        ]);
    }

    public function billingPortalUrl(Organization $organization, string $returnUrl): string
    {
        throw new RuntimeException('The PayMongo billing portal is not available.');
    }

    public function retrieveSubscription(BillingSubscription $subscription): RemoteBillingSubscription
    {
        try {
            $response = $this->client->get(
                'retrieve_subscription',
                "/subscriptions/{$subscription->external_subscription_id}",
                (string) $subscription->organization_id,
            );
        } catch (PayMongoRequestException $exception) {
            if ($exception->httpStatus === 404) {
                throw new RemoteBillingSubscriptionNotFoundException;
            }

            throw $exception;
        }

        $resource = $this->resource($response, 'subscription');
        $attributes = $resource['attributes'] ?? null;
        $customer = $subscription->billingCustomer;

        if (! is_array($attributes) || ($resource['id'] ?? null) !== $subscription->external_subscription_id
            || ($attributes['customer_id'] ?? null) !== $customer->external_customer_id
            || ! is_string($attributes['status'] ?? null) || ! is_bool($attributes['livemode'] ?? null)
            || ! is_string($attributes['plan']['id'] ?? null)) {
            throw new RuntimeException('PayMongo returned an invalid subscription response.');
        }

        $nextBillingAt = $this->date($attributes['next_billing_schedule'] ?? null);
        $cancelled = in_array($attributes['status'], ['cancelled', 'canceled'], true);

        return new RemoteBillingSubscription(
            externalSubscriptionId: $resource['id'],
            externalCustomerId: $customer->external_customer_id,
            externalPlanId: $attributes['plan']['id'],
            status: $attributes['status'],
            livemode: $attributes['livemode'],
            currentPeriodEndsAt: $nextBillingAt,
            nextBillingAt: $cancelled ? null : $nextBillingAt,
            endsAt: $cancelled ? $nextBillingAt : null,
            cancelledAt: $this->timestamp($attributes['cancelled_at'] ?? null),
        );
    }

    public function cancelSubscription(BillingSubscription $subscription): void
    {
        if ($subscription->provider !== BillingProviderEnum::PayMongo || ! str_starts_with($subscription->external_subscription_id, 'subs_')) {
            throw new RuntimeException('PayMongo subscription ownership could not be resolved safely.');
        }

        $response = $this->client->post(
            'cancel_subscription',
            "/subscriptions/{$subscription->external_subscription_id}/cancel",
            [],
            (string) $subscription->organization_id,
            "miseledger:paymongo:subscription-cancel:{$subscription->getKey()}",
        );
        $resource = $this->resource($response, 'subscription');
        $status = $resource['attributes']['status'] ?? null;

        if (($resource['id'] ?? null) !== $subscription->external_subscription_id || ! in_array($status, ['cancelled', 'canceled'], true)) {
            throw new RuntimeException('PayMongo returned an invalid cancellation response.');
        }
    }

    public function ensureCustomer(Organization $organization, User $actor): BillingCustomer
    {
        $customer = BillingCustomer::query()
            ->where('organization_id', $organization->getKey())
            ->where('provider', BillingProviderEnum::PayMongo)
            ->first();

        if ($customer !== null) {
            $this->assertCustomerOwnership($customer, $organization);

            return $customer;
        }

        [$firstName, $lastName] = $this->customerName($actor);
        $phone = $this->customerPhone();

        $response = $this->client->post(
            'create_customer',
            '/customers',
            ['data' => ['attributes' => [
                'first_name' => $firstName,
                'last_name' => $lastName,
                'email' => $actor->email,
                'phone' => $phone,
                'default_device' => 'email',
            ]]],
            (string) $organization->getKey(),
            $this->customerIdempotencyKey($organization, $firstName, $lastName, $actor->email, $phone),
        );

        $data = $this->resource($response, 'customer');
        $externalCustomerId = $data['id'] ?? null;
        $attributes = $data['attributes'] ?? null;

        if (! is_string($externalCustomerId) || ! str_starts_with($externalCustomerId, 'cus_') || ! is_array($attributes) || ! is_bool($attributes['livemode'] ?? null)) {
            throw new RuntimeException('PayMongo returned an invalid customer response.');
        }

        try {
            $customer = BillingCustomer::query()->create([
                'organization_id' => $organization->getKey(),
                'provider' => BillingProviderEnum::PayMongo,
                'external_customer_id' => $externalCustomerId,
                'livemode' => $attributes['livemode'],
            ]);
        } catch (QueryException) {
            $customer = BillingCustomer::query()
                ->where('organization_id', $organization->getKey())
                ->where('provider', BillingProviderEnum::PayMongo)
                ->first();

            if ($customer === null) {
                throw new RuntimeException('PayMongo customer ownership could not be established safely.');
            }
        }

        $this->assertCustomerOwnership($customer, $organization);

        if ($customer->external_customer_id !== $externalCustomerId) {
            throw new RuntimeException('PayMongo customer identity conflicts with this organization.');
        }

        return $customer;
    }

    /** @return array{0: string, 1: string} */
    private function customerName(User $actor): array
    {
        $parts = preg_split('/\s+/', trim($actor->name)) ?: [];
        $firstName = array_shift($parts);
        $lastName = implode(' ', $parts);

        if (! is_string($firstName) || $firstName === '' || $lastName === '' || $actor->email === '') {
            throw new RuntimeException('The billing contact is incomplete for PayMongo customer creation.');
        }

        return [$firstName, $lastName];
    }

    private function customerPhone(): string
    {
        $phone = config('billing.providers.paymongo.customer_phone');

        if (! is_string($phone)) {
            throw new RuntimeException('PayMongo billing contact phone configuration is unavailable.');
        }

        $phone = trim($phone);

        if (preg_match('/^09\d{9}$/', $phone) === 1) {
            return '+63'.substr($phone, 1);
        }

        if (preg_match('/^639\d{9}$/', $phone) === 1) {
            return '+'.$phone;
        }

        if (preg_match('/^\+639\d{9}$/', $phone) === 1) {
            return $phone;
        }

        throw new RuntimeException('PayMongo billing contact phone configuration is unavailable.');
    }

    private function customerIdempotencyKey(
        Organization $organization,
        string $firstName,
        string $lastName,
        string $email,
        string $phone,
    ): string {
        $fingerprint = hash('sha256', implode('|', [
            $organization->getKey(),
            $firstName,
            $lastName,
            mb_strtolower($email),
            $phone,
        ]));

        return "miseledger:paymongo:customer:{$organization->getKey()}:v3:".substr($fingerprint, 0, 16);
    }

    /** @return array<string, mixed> */
    private function createSubscription(Organization $organization, BillingCustomer $customer, string $externalPlanId): array
    {
        $response = $this->client->post(
            'create_subscription',
            '/subscriptions',
            ['data' => ['attributes' => [
                'plan_id' => $externalPlanId,
                'customer_id' => $customer->external_customer_id,
            ]]],
            (string) $organization->getKey(),
            "miseledger:paymongo:subscription:{$organization->getKey()}:{$externalPlanId}",
        );
        $data = $this->resource($response, 'subscription');
        $attributes = $data['attributes'] ?? null;

        if (! is_string($data['id'] ?? null) || ! str_starts_with($data['id'], 'subs_') || ! is_array($attributes)
            || ($attributes['customer_id'] ?? null) !== $customer->external_customer_id
            || ($attributes['plan']['id'] ?? null) !== $externalPlanId
            || ! is_string($attributes['status'] ?? null) || ! is_bool($attributes['livemode'] ?? null)) {
            throw new RuntimeException('PayMongo returned an invalid subscription response.');
        }

        return $data;
    }

    /** @param array<string, mixed> $subscription */
    private function paymentIntentId(array $subscription): string
    {
        $paymentIntentId = $subscription['attributes']['latest_invoice']['payment_intent']['id'] ?? null;

        if (! is_string($paymentIntentId) || ! str_starts_with($paymentIntentId, 'pi_')) {
            throw new RuntimeException('PayMongo did not return a usable first-payment intent.');
        }

        return $paymentIntentId;
    }

    /** @param array<string, mixed> $paymentIntent */
    private function paymentIntentClientKey(array $paymentIntent, string $paymentIntentId): string
    {
        $data = $this->resource($paymentIntent, 'payment_intent');
        $clientKey = $data['attributes']['client_key'] ?? null;

        if (($data['id'] ?? null) !== $paymentIntentId || ! is_string($clientKey) || $clientKey === '') {
            throw new RuntimeException('PayMongo returned an invalid first-payment intent response.');
        }

        return $clientKey;
    }

    /** @param array<string, mixed> $subscription */
    private function persistSubscription(Organization $organization, BillingCustomer $customer, string $externalPlanId, array $subscription): void
    {
        $attributes = $subscription['attributes'];
        $existing = BillingSubscription::query()
            ->where('provider', BillingProviderEnum::PayMongo)
            ->where('external_subscription_id', $subscription['id'])
            ->first();

        if ($existing !== null) {
            if ($existing->organization_id !== $organization->getKey() || $existing->billing_customer_id !== $customer->getKey() || $existing->external_plan_id !== $externalPlanId) {
                throw new RuntimeException('PayMongo subscription ownership conflicts with this organization.');
            }

            return;
        }

        try {
            BillingSubscription::query()->create([
                'organization_id' => $organization->getKey(),
                'billing_customer_id' => $customer->getKey(),
                'provider' => BillingProviderEnum::PayMongo,
                'type' => config('billing.subscription_type'),
                'external_subscription_id' => $subscription['id'],
                'external_plan_id' => $externalPlanId,
                'plan_code' => $this->planCodeFor($externalPlanId),
                'interval' => $this->intervalFor($externalPlanId),
                'provider_status' => $attributes['status'],
                'livemode' => $attributes['livemode'],
                'next_billing_at' => $this->date($attributes['next_billing_schedule'] ?? null),
                'cancelled_at' => $this->timestamp($attributes['cancelled_at'] ?? null),
            ]);
        } catch (QueryException) {
            $this->persistSubscription($organization, $customer, $externalPlanId, $subscription);
        }
    }

    private function planCodeFor(string $externalPlanId): string
    {
        $plan = $this->planCatalog->resolveExternalPlan(BillingProviderEnum::PayMongo, $externalPlanId);

        if ($plan === null) {
            throw new RuntimeException('PayMongo plan identity could not be resolved safely.');
        }

        return $plan->code->value;
    }

    private function intervalFor(string $externalPlanId): string
    {
        $interval = $this->planCatalog->resolveExternalPlanInterval(BillingProviderEnum::PayMongo, $externalPlanId);

        if ($interval === null) {
            throw new RuntimeException('PayMongo plan interval could not be resolved safely.');
        }

        return $interval;
    }

    /** @param array<string, mixed> $response @return array<string, mixed> */
    private function resource(array $response, string $type): array
    {
        $data = $response['data'] ?? null;

        if (! is_array($data) || ($data['type'] ?? null) !== $type) {
            throw new RuntimeException("PayMongo returned an invalid {$type} response.");
        }

        return $data;
    }

    private function assertCustomerOwnership(BillingCustomer $customer, Organization $organization): void
    {
        if ($customer->organization_id !== $organization->getKey() || $customer->provider !== BillingProviderEnum::PayMongo || $customer->external_customer_id === '') {
            throw new RuntimeException('PayMongo customer ownership conflicts with this organization.');
        }
    }

    private function date(mixed $value): ?Carbon
    {
        return is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1 ? Carbon::createFromFormat('!Y-m-d', $value, 'UTC') : null;
    }

    private function timestamp(mixed $value): ?Carbon
    {
        return is_int($value) || (is_string($value) && ctype_digit($value)) ? Carbon::createFromTimestampUTC((int) $value) : null;
    }
}
