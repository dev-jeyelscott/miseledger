<?php

namespace App\Enums;

/**
 * A payment provider capable of owning billing-customer and subscription
 * identity for an organization. Configuration-owned selection lives in
 * `config('billing.provider')`; this enum is the sole conversion boundary
 * business logic should use instead of raw provider strings.
 */
enum BillingProvider: string
{
    case Stripe = 'stripe';
    case PayMongo = 'paymongo';
}
