<?php

namespace App\Http\Requests\Billing;

use App\Enums\OrganizationPermission;
use App\Enums\PlanCode;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

class CreateOrganizationManualRenewalRequest extends FormRequest
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
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'plan' => ['nullable', 'string'],
            'interval' => ['nullable', 'string', 'in:monthly,yearly'],
        ];
    }

    public function planCode(): ?PlanCode
    {
        $plan = $this->validated('plan');

        if ($plan === null) {
            return null;
        }

        try {
            return PlanCode::from($plan);
        } catch (InvalidArgumentException) {
            abort(422, 'Invalid plan.');
        }
    }
}
