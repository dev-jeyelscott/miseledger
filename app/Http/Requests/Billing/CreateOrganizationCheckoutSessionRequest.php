<?php

namespace App\Http\Requests\Billing;

use App\Enums\OrganizationPermission;
use App\Enums\PlanCode;
use App\Models\Organization;
use App\Models\User;
use App\Support\Billing\PlanCatalog;
use App\Support\Billing\Providers\BillingProviderManager;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Validator;
use InvalidArgumentException;

/**
 * Accepts only an internal plan code and billing interval. The external
 * provider plan ID is never read from the request: it is resolved exclusively
 * through `PlanCatalog` once the plan/interval combination validates.
 */
class CreateOrganizationCheckoutSessionRequest extends FormRequest
{
    /**
     * Restrict Checkout initiation to members with billing.manage for the
     * requested organization.
     */
    public function authorize(): bool
    {
        $user = $this->user();
        $organization = $this->route('organization');

        return $user instanceof User
            && $organization instanceof Organization
            && Gate::forUser($user)->allows(
                OrganizationPermission::BillingManage->value,
                $organization,
            );
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'plan' => ['required', 'string'],
            'interval' => ['required', 'string', 'in:monthly,yearly'],
        ];
    }

    /**
     * Reject plan/interval combinations that do not resolve to a
     * configured provider plan ID for the selected acquisition provider.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            if ($this->resolvedPriceId() === null) {
                $validator->errors()->add(
                    'plan',
                    __('The selected plan is not available for the chosen billing interval.'),
                );
            }
        });
    }

    /**
     * Resolve the internal plan code from the validated input.
     */
    public function planCode(): PlanCode
    {
        return PlanCode::from((string) $this->input('plan'));
    }

    /**
     * Resolve the trusted, configured provider plan ID for the submitted
     * plan/interval, or null when unresolvable.
     */
    private function resolvedPriceId(): ?string
    {
        $plan = $this->input('plan');
        $interval = $this->input('interval');

        if (! is_string($plan) || ! is_string($interval)) {
            return null;
        }

        try {
            $planCode = PlanCode::from($plan);
        } catch (InvalidArgumentException) {
            return null;
        }

        $provider = app(BillingProviderManager::class)->defaultProvider();

        return app(PlanCatalog::class)->externalPlanId($planCode, $provider, $interval);
    }
}
