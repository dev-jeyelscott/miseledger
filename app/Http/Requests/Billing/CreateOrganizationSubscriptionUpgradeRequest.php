<?php

namespace App\Http\Requests\Billing;

use App\Enums\OrganizationPermission;
use App\Enums\PlanCode;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

class CreateOrganizationSubscriptionUpgradeRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() instanceof User
            && $this->route('organization') instanceof Organization
            && Gate::forUser($this->user())->allows(
                OrganizationPermission::BillingManage->value,
                $this->route('organization'),
            );
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * Only an internal plan code is ever accepted from the browser -- no
     * interval, external provider plan ID, or amount. Interval is always
     * read server-side from the subscription's existing interval.
     *
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'plan' => ['required', 'string'],
        ];
    }

    public function targetPlanCode(): PlanCode
    {
        try {
            return PlanCode::from((string) $this->validated('plan'));
        } catch (InvalidArgumentException) {
            abort(422, 'Invalid plan.');
        }
    }
}
