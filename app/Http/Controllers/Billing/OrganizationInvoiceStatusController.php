<?php

namespace App\Http\Controllers\Billing;

use App\Enums\OrganizationPermission;
use App\Http\Controllers\Controller;
use App\Models\BillingInvoice;
use App\Models\Organization;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

final class OrganizationInvoiceStatusController extends Controller
{
    public function show(Organization $organization, BillingInvoice $invoice): JsonResponse
    {
        Gate::authorize(OrganizationPermission::BillingManage->value, $organization);
        abort_unless($invoice->organization_id === $organization->getKey(), 404);

        $payment = $invoice->payments()->latest()->first();

        return response()->json([
            'invoice_status' => $invoice->status->value,
            'payment_status' => $payment?->status->value,
            'expires_at' => $payment?->expires_at?->toISOString(),
            'subscription_status' => $invoice->billingSubscription->provider_status,
            'access_ends_at' => $invoice->billingSubscription->current_period_ends_at?->toISOString(),
        ]);
    }
}
