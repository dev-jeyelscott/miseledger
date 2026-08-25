<?php

namespace App\Http\Controllers\Billing;

use App\Actions\Billing\CreatePayMongoQrPhPayment;
use App\Actions\Billing\CreateRenewalInvoice;
use App\Actions\Billing\EnsureManualPayMongoSubscription;
use App\Http\Controllers\Controller;
use App\Http\Requests\Billing\CreateOrganizationManualRenewalRequest;
use App\Models\Organization;
use App\Support\Billing\ManualRenewalCheckout;
use App\Support\Billing\Providers\PayMongoRequestException;
use Illuminate\Http\JsonResponse;

final class OrganizationManualRenewalController extends Controller
{
    public function store(
        CreateOrganizationManualRenewalRequest $request,
        Organization $organization,
        EnsureManualPayMongoSubscription $ensureSubscription,
        CreateRenewalInvoice $createInvoice,
        CreatePayMongoQrPhPayment $createPayment,
    ): JsonResponse {
        abort_unless(config('billing.providers.paymongo.manual_qrph') === true, 404);

        try {
            $subscription = $ensureSubscription->handle(
                $organization,
                $request->user(),
                $request->planCode(),
                $request->validated('interval'),
            );
            $checkout = $createPayment->handle($createInvoice->handle($subscription));
        } catch (PayMongoRequestException) {
            return response()->json(['message' => 'The payment service is temporarily unavailable.'], 503);
        }

        return response()->json($this->checkoutData($checkout));
    }

    /** @return array<string, int|string|null> */
    private function checkoutData(ManualRenewalCheckout $checkout): array
    {
        return [
            'invoice_id' => $checkout->invoice->getKey(),
            'invoice_status' => $checkout->invoice->status->value,
            'payment_id' => $checkout->payment->getKey(),
            'payment_status' => $checkout->payment->status->value,
            'amount' => $checkout->payment->amount,
            'currency' => $checkout->payment->currency,
            'qr_code_url' => $checkout->payment->qr_code_url,
            'expires_at' => $checkout->payment->expires_at?->toISOString(),
        ];
    }
}
