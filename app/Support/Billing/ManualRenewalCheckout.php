<?php

namespace App\Support\Billing;

use App\Models\BillingInvoice;
use App\Models\BillingPayment;

final readonly class ManualRenewalCheckout
{
    public function __construct(
        public BillingInvoice $invoice,
        public BillingPayment $payment,
    ) {}
}
