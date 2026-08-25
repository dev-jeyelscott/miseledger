<?php

namespace App\Support\Billing\Providers;

use Illuminate\Support\Carbon;

final readonly class PayMongoQrPhCheckout
{
    public function __construct(
        public string $paymentIntentId,
        public string $qrCodeUrl,
        public bool $livemode,
        public ?Carbon $expiresAt,
    ) {}
}
