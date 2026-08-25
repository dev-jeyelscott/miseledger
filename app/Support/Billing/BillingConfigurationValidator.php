<?php

namespace App\Support\Billing;

use Illuminate\Support\Arr;
use RuntimeException;

final class BillingConfigurationValidator
{
    /**
     * Provider identifiers approved by the MiseLedger commercial contract.
     *
     * @var list<string>
     */
    private const SUPPORTED_PROVIDERS = [
        'stripe',
        'paymongo',
    ];

    /**
     * Reject incomplete, unsupported, disabled, or non-live provider
     * configuration before production requests can use billing services.
     *
     * @param  array<string, mixed>  $configuration
     */
    public static function validateProduction(array $configuration): void
    {
        self::validateCommonConfiguration($configuration);

        $selectedProvider = self::selectedProvider($configuration);

        $providers = Arr::get($configuration, 'providers');

        if (! is_array($providers)) {
            throw new RuntimeException('Production billing configuration requires provider configuration.');
        }

        $selectedConfiguration = $providers[$selectedProvider] ?? null;

        if (
            ! is_array($selectedConfiguration)
            || Arr::get($selectedConfiguration, 'enabled') !== true
        ) {
            throw new RuntimeException('The selected billing provider must be enabled in production.');
        }

        foreach (self::SUPPORTED_PROVIDERS as $provider) {
            $providerConfiguration = $providers[$provider] ?? null;

            if (! is_array($providerConfiguration)) {
                if ($provider === $selectedProvider) {
                    throw new RuntimeException('The selected billing provider configuration is missing.');
                }

                continue;
            }

            $enabled = Arr::get($providerConfiguration, 'enabled') === true;

            if ($provider !== $selectedProvider && ! $enabled) {
                continue;
            }

            match ($provider) {
                'stripe' => self::validateStripe($providerConfiguration),
                'paymongo' => self::validatePayMongo($providerConfiguration),
            };
        }
    }

    /**
     * Validate provider-neutral billing values required in production.
     *
     * @param  array<string, mixed>  $configuration
     */
    private static function validateCommonConfiguration(array $configuration): void
    {
        $requiredKeys = array_values(array_filter(
            (array) Arr::get($configuration, 'required_in_production', []),
            'is_string',
        ));

        $missing = array_values(array_filter(
            $requiredKeys,
            static fn (string $key): bool => ! filled(Arr::get($configuration, $key)),
        ));

        if ($missing !== []) {
            throw new RuntimeException(
                'Missing required billing configuration: '.implode(', ', $missing),
            );
        }
    }

    /**
     * Resolve and validate the explicitly selected acquisition provider.
     *
     * @param  array<string, mixed>  $configuration
     */
    private static function selectedProvider(array $configuration): string
    {
        $provider = Arr::get($configuration, 'provider');

        if (
            ! is_string($provider)
            || ! in_array($provider, self::SUPPORTED_PROVIDERS, true)
        ) {
            throw new RuntimeException(
                'Production billing configuration requires BILLING_PROVIDER to be explicitly set to stripe or paymongo.',
            );
        }

        return $provider;
    }

    /**
     * Validate live Stripe credentials for an operational Stripe provider.
     *
     * @param  array<string, mixed>  $configuration
     */
    private static function validateStripe(array $configuration): void
    {
        self::validateRequiredProviderConfiguration(
            'Stripe',
            $configuration,
            ['key', 'secret', 'webhook_secret', 'mode'],
        );

        $key = Arr::get($configuration, 'key');
        $secret = Arr::get($configuration, 'secret');
        $webhookSecret = Arr::get($configuration, 'webhook_secret');

        if (
            ! is_string($key)
            || preg_match('/^pk_live_[^\s]+$/', $key) !== 1
            || ! is_string($secret)
            || preg_match('/^(?:sk|rk)_live_[^\s]+$/', $secret) !== 1
            || Arr::get($configuration, 'mode') !== 'live'
        ) {
            throw new RuntimeException(
                'Production Stripe billing configuration requires matching live Stripe API keys.',
            );
        }

        if (
            ! is_string($webhookSecret)
            || preg_match('/^whsec_[^\s]+$/', $webhookSecret) !== 1
        ) {
            throw new RuntimeException(
                'Production Stripe billing configuration requires a Stripe webhook secret.',
            );
        }
    }

    /**
     * Validate live PayMongo API credentials and webhook-secret structure for
     * an operational PayMongo provider.
     *
     * PayMongo identifies API mode in pk_test_/sk_test_ versus
     * pk_live_/sk_live_ prefixes. Its whsk_ webhook secret does not encode
     * endpoint livemode, so endpoint mode must also be verified operationally.
     *
     * @param  array<string, mixed>  $configuration
     */
    private static function validatePayMongo(array $configuration): void
    {
        self::validateRequiredProviderConfiguration(
            'PayMongo',
            $configuration,
            ['public_key', 'secret_key', 'webhook_secret'],
        );

        $publicKey = Arr::get($configuration, 'public_key');
        $secretKey = Arr::get($configuration, 'secret_key');
        $webhookSecret = Arr::get($configuration, 'webhook_secret');

        if (
            ! is_string($publicKey)
            || preg_match('/^pk_live_[^\s]+$/', $publicKey) !== 1
            || ! is_string($secretKey)
            || preg_match('/^sk_live_[^\s]+$/', $secretKey) !== 1
        ) {
            throw new RuntimeException(
                'Production PayMongo billing configuration requires matching live PayMongo API keys.',
            );
        }

        if (
            ! is_string($webhookSecret)
            || preg_match('/^whsk_[^\s]+$/', $webhookSecret) !== 1
        ) {
            throw new RuntimeException(
                'Production PayMongo billing configuration requires a PayMongo webhook secret.',
            );
        }
    }

    /**
     * Reject missing provider-specific fields without exposing credential
     * values in exceptions.
     *
     * @param  array<string, mixed>  $configuration
     * @param  list<string>  $requiredKeys
     */
    private static function validateRequiredProviderConfiguration(
        string $provider,
        array $configuration,
        array $requiredKeys,
    ): void {
        $missing = array_values(array_filter(
            $requiredKeys,
            static fn (string $key): bool => ! filled(Arr::get($configuration, $key)),
        ));

        if ($missing !== []) {
            throw new RuntimeException(
                'Missing required '.$provider.' billing configuration: '.implode(', ', $missing),
            );
        }
    }
}
