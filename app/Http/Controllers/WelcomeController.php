<?php

namespace App\Http\Controllers;

use App\Support\Billing\PlanCatalog;
use Inertia\Inertia;
use Inertia\Response;

class WelcomeController extends Controller
{
    /**
     * Show the public marketing landing page, sourcing every trial and
     * plan claim from the approved `config('billing.*')` contract so the
     * page never publishes a fabricated price, trial length, or plan name.
     */
    public function index(PlanCatalog $planCatalog): Response
    {
        $trialDays = config('billing.trial_days');

        return Inertia::render('welcome', [
            'trialDays' => $trialDays !== null ? (int) $trialDays : null,
            'plans' => $this->plansData($planCatalog),
        ]);
    }

    /**
     * Expose only the configured plan code and display name. Stripe Price
     * IDs, features, and limits are deliberately withheld: the marketing
     * page shows what plans exist, not what they cost or grant.
     *
     * @return list<array{code: string, name: string}>
     */
    private function plansData(PlanCatalog $planCatalog): array
    {
        return array_values(array_map(
            static fn ($definition): array => [
                'code' => $definition->code->value,
                'name' => $definition->name,
            ],
            $planCatalog->all(),
        ));
    }
}
