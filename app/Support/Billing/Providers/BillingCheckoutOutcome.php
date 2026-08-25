<?php

namespace App\Support\Billing\Providers;

use InvalidArgumentException;

final readonly class BillingCheckoutOutcome
{
    /** @param array<string, string> $payment */
    private function __construct(
        public string $type,
        public ?string $redirectUrl = null,
        public array $payment = [],
    ) {}

    public static function redirect(string $url): self
    {
        return new self('redirect', $url);
    }

    /** @param array{payment_intent_id: string, client_key: string, public_key: string, api_base_url: string} $payment */
    public static function payment(array $payment): self
    {
        return new self('payment', payment: $payment);
    }

    /** @return array{type: string, redirect_url: string|null, payment: array<string, string>} */
    public function toCacheValue(): array
    {
        return ['type' => $this->type, 'redirect_url' => $this->redirectUrl, 'payment' => $this->payment];
    }

    /** @param array<string, mixed> $value */
    public static function fromCacheValue(array $value): self
    {
        $type = $value['type'] ?? null;

        if ($type === 'redirect' && is_string($value['redirect_url'] ?? null)) {
            return self::redirect($value['redirect_url']);
        }

        $payment = $value['payment'] ?? null;

        if ($type === 'payment' && is_array($payment) && array_all($payment, 'is_string') && isset($payment['payment_intent_id'], $payment['client_key'], $payment['public_key'], $payment['api_base_url'])) {
            return self::payment($payment);
        }

        throw new InvalidArgumentException('The cached billing checkout outcome is malformed.');
    }
}
