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
     * Expose the configured plan code, display name, granted feature codes,
     * and quantitative limits. Provider price IDs, provider enablement
     * state, and every other provider-specific identifier stay behind
     * billing infrastructure and are never serialized here.
     *
     * @return list<array{code: string, name: string, features: list<string>, limits: array<string, int|null>}>
     */
    private function plansData(PlanCatalog $planCatalog): array
    {
        return array_map(
            static fn ($definition): array => [
                'code' => $definition->code->value,
                'name' => $definition->name,
                'features' => $definition->features,
                'limits' => $definition->limits,
            ],
            $planCatalog->all(),
        );
    }
}
