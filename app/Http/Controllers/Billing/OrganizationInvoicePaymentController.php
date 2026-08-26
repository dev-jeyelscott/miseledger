<?php

namespace App\Http\Controllers\Billing;

use App\Actions\Billing\CreatePayMongoQrPhPayment;
use App\Enums\BillingInvoiceType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Billing\CreateOrganizationInvoicePaymentRequest;
use App\Models\BillingInvoice;
use App\Models\Organization;
use App\Support\Billing\Providers\PayMongoRequestException;
use Illuminate\Http\JsonResponse;

final class OrganizationInvoicePaymentController extends Controller
{
    public function store(CreateOrganizationInvoicePaymentRequest $request, Organization $organization, BillingInvoice $invoice, CreatePayMongoQrPhPayment $createPayment): JsonResponse
    {
        abort_unless(config('billing.providers.paymongo.manual_qrph') === true, 404);

        try {
            $checkout = $createPayment->handle($invoice);
        } catch (PayMongoRequestException) {
            return response()->json(['message' => 'The payment service is temporarily unavailable.'], 503);
        }

        return response()->json([
            'kind' => $checkout->invoice->invoice_type === BillingInvoiceType::Upgrade ? 'upgrade' : 'renewal',
            'invoice_id' => $checkout->invoice->getKey(),
            'invoice_status' => $checkout->invoice->status->value,
            'target_plan' => $checkout->invoice->target_plan_code,
            'payment_id' => $checkout->payment->getKey(),
            'payment_status' => $checkout->payment->status->value,
            'amount' => $checkout->payment->amount,
            'currency' => $checkout->payment->currency,
            'qr_code_url' => $checkout->payment->qr_code_url,
            'expires_at' => $checkout->payment->expires_at?->toISOString(),
        ]);
    }
}
