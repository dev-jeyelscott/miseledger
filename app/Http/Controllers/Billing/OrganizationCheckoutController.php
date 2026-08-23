<?php

namespace App\Http\Controllers\Billing;

use App\Actions\Billing\CreateOrganizationCheckoutSession;
use App\Http\Controllers\Controller;
use App\Http\Requests\Billing\CreateOrganizationCheckoutSessionRequest;
use App\Models\Organization;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class OrganizationCheckoutController extends Controller
{
    /**
     * Redirect the authorized member to a Stripe Checkout session for the
     * requested organization, plan, and interval.
     */
    public function store(
        CreateOrganizationCheckoutSessionRequest $request,
        CreateOrganizationCheckoutSession $createCheckoutSession,
        Organization $organization,
    ): Response {
        $checkout = $createCheckoutSession->handle(
            $organization,
            $request->planCode(),
            (string) $request->validated('interval'),
        );

        return Inertia::location($checkout->redirect());
    }
}
