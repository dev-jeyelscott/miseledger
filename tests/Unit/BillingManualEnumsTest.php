<?php

use App\Enums\BillingCollectionMethod;
use App\Enums\BillingInvoiceStatus;
use App\Enums\BillingPaymentMethod;
use App\Enums\BillingPaymentStatus;

test('manual billing uses controlled collection, invoice, and payment values', function (): void {
    expect(BillingCollectionMethod::Manual->value)->toBe('manual')
        ->and(BillingPaymentMethod::QrPh->value)->toBe('qrph')
        ->and(BillingInvoiceStatus::PaymentPending->isPayable())->toBeTrue()
        ->and(BillingInvoiceStatus::Paid->isPayable())->toBeFalse()
        ->and(BillingPaymentStatus::AwaitingPayment->isReusable())->toBeTrue()
        ->and(BillingPaymentStatus::Expired->isReusable())->toBeFalse();
});
