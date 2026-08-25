<?php

namespace App\Http\Requests\Billing;

use App\Enums\OrganizationPermission;
use App\Models\BillingInvoice;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class CreateOrganizationInvoicePaymentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $organization = $this->route('organization');
        $invoice = $this->route('invoice');

        return $this->user() instanceof User
            && $organization instanceof Organization
            && $invoice instanceof BillingInvoice
            && $invoice->organization_id === $organization->getKey()
            && Gate::forUser($this->user())->allows(OrganizationPermission::BillingManage->value, $organization);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [];
    }
}
