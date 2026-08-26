<?php

namespace App\Http\Controllers\Billing;

use App\Actions\Billing\CreatePayMongoQrPhPayment;
use App\Actions\Billing\CreateUpgradeInvoice;
use App\Enums\BillingCollectionMethod;
use App\Enums\BillingProvider;
use App\Enums\PlanCode;
use App\Http\Controllers\Controller;
use App\Http\Requests\Billing\CreateOrganizationSubscriptionUpgradeRequest;
use App\Models\BillingSubscription;
use App\Models\Organization;
use App\Support\Billing\ManualRenewalCheckout;
use App\Support\Billing\PlanUpgradePolicy;
use App\Support\Billing\Providers\PayMongoRequestException;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

final class OrganizationSubscriptionUpgradeController extends Controller
{
    public function store(
        CreateOrganizationSubscriptionUpgradeRequest $request,
        Organization $organization,
        PlanUpgradePolicy $upgradePolicy,
        CreateUpgradeInvoice $createUpgradeInvoice,
        CreatePayMongoQrPhPayment $createPayment,
    ): JsonResponse {
        abort_unless(config('billing.providers.paymongo.manual_qrph') === true, 404);

        $subscriptions = $organization->billingSubscriptions()
            ->where('type', (string) config('billing.subscription_type'))
            ->get();

        if ($subscriptions->count() !== 1) {
            throw ValidationException::withMessages([
                'organization' => __('This organization does not have an active subscription to upgrade.'),
            ]);
        }

        $subscription = $subscriptions->first();

        if (! $subscription instanceof BillingSubscription || $subscription->plan_code === null) {
            throw ValidationException::withMessages([
                'organization' => __('This organization does not have an active subscription to upgrade.'),
            ]);
        }

        $targetPlan = $request->targetPlanCode();
        $currentPlan = PlanCode::from($subscription->plan_code);

        if (! $upgradePolicy->isEligibleUpgrade($currentPlan, $targetPlan)) {
            throw ValidationException::withMessages([
                'plan' => __('This plan change is not a supported upgrade.'),
            ]);
        }

        if ($subscription->provider !== BillingProvider::PayMongo || $subscription->collection_method !== BillingCollectionMethod::Manual) {
            throw ValidationException::withMessages([
                'plan' => __('Plan upgrades are not yet available for this subscription.'),
            ]);
        }

        try {
            $invoice = $createUpgradeInvoice->handle($subscription, $targetPlan);
            $checkout = $createPayment->handle($invoice);
        } catch (PayMongoRequestException) {
            return response()->json(['message' => 'The payment service is temporarily unavailable.'], 503);
        }

        return response()->json($this->checkoutData($checkout));
    }

    /** @return array<string, int|string|null> */
    private function checkoutData(ManualRenewalCheckout $checkout): array
    {
        return [
            'kind' => 'upgrade',
            'invoice_id' => $checkout->invoice->getKey(),
            'invoice_status' => $checkout->invoice->status->value,
            'target_plan' => $checkout->invoice->target_plan_code,
            'payment_id' => $checkout->payment->getKey(),
            'payment_status' => $checkout->payment->status->value,
            'amount' => $checkout->payment->amount,
            'currency' => $checkout->payment->currency,
            'qr_code_url' => $checkout->payment->qr_code_url,
            'expires_at' => $checkout->payment->expires_at?->toISOString(),
        ];
    }
}
