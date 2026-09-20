<?php

namespace App\Support\Billing;

use App\Models\Organization;

/**
 * Scopes the flashed PayMongo card-payment handoff to the organization that
 * started the checkout, so it cannot be read from another organization's
 * checkout-success route in the same authenticated session.
 */
final class CheckoutPaymentSession
{
    public static function key(Organization $organization): string
    {
        return 'billing.checkout.payment.'.$organization->getKey();
    }
}
