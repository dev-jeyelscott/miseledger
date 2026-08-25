<?php

namespace App\Http\Controllers\Billing;

use App\Actions\Billing\CancelOrganizationSubscription;
use App\Enums\OrganizationPermission;
use App\Http\Controllers\Controller;
use App\Models\Organization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class OrganizationBillingCancellationController extends Controller
{
    public function store(Organization $organization, CancelOrganizationSubscription $cancelSubscription): RedirectResponse
    {
        Gate::authorize(OrganizationPermission::BillingManage->value, $organization);

        $cancelSubscription->handle($organization);

        return to_route('organizations.billing.show', $organization)
            ->with('success', 'Your subscription will not renew. Paid access remains available until the end of the current billing period.');
    }
}
