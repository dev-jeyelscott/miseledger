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
        $outcome = $createCheckoutSession->handle(
            $organization,
            $request->user(),
            $request->planCode(),
            (string) $request->validated('interval'),
        );

        if ($outcome->type === 'redirect') {
            return Inertia::location((string) $outcome->redirectUrl);
        }

        return to_route('organizations.billing.checkout.success', $organization)
            ->with('billing.checkout.payment', $outcome->payment);
    }
}
