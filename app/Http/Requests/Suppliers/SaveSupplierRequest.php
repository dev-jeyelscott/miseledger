<?php

namespace App\Http\Requests\Suppliers;

use App\Enums\OrganizationPermission;
use App\Models\Organization;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class SaveSupplierRequest extends FormRequest
{
    /**
     * Require purchasing management permission and a tenant-safe target.
     */
    public function authorize(): bool
    {
        $user = $this->user();
        $organization = $this->organization();

        if (
            ! $user instanceof User
            || $organization === null
            || ! Gate::forUser($user)->allows(
                OrganizationPermission::PurchasingManage->value,
                $organization,
            )
        ) {
            return false;
        }

        return $this->route('supplier') === null
            || $this->supplier() !== null;
    }

    /**
     * Validate organization-scoped supplier master data.
     *
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        $organizationId = (int) (
            $this->organization()?->getKey() ?? 0
        );

        return [
            'name' => [
                'required',
                'string',
                'max:180',
            ],
            'code' => [
                'required',
                'string',
                'max:60',
                Rule::unique('suppliers', 'code')
                    ->where(
                        fn (Builder $query): Builder => $query->where(
                            'organization_id',
                            $organizationId,
                        ),
                    )
                    ->ignore($this->supplier()),
            ],
            'contact_name' => [
                'nullable',
                'string',
                'max:120',
            ],
            'email' => [
                'nullable',
                'email',
                'max:180',
            ],
            'phone' => [
                'nullable',
                'string',
                'max:60',
            ],
            'payment_terms' => [
                'nullable',
                'string',
                'max:120',
            ],
            'lead_time_days' => [
                'nullable',
                'integer',
                'min:0',
            ],
            'active' => [
                'required',
                'boolean',
            ],
        ];
    }

    /**
     * Return the active organization.
     */
    public function organization(): ?Organization
    {
        $organization = $this->attributes->get('activeOrganization');

        return $organization instanceof Organization
            ? $organization
            : null;
    }

    /**
     * Resolve a supplier only inside the active organization.
     */
    public function supplier(): ?Supplier
    {
        $organization = $this->organization();
        $routeId = $this->route('supplier');

        if (
            $organization === null
            || $routeId === null
            || ! is_numeric($routeId)
        ) {
            return null;
        }

        return Supplier::query()
            ->where('organization_id', $organization->id)
            ->find((int) $routeId);
    }

    /**
     * Normalize supplier identity and optional contact fields.
     */
    protected function prepareForValidation(): void
    {
        $name = $this->input('name');
        $code = $this->input('code');
        $contactName = $this->input('contact_name');
        $email = $this->input('email');
        $phone = $this->input('phone');
        $paymentTerms = $this->input('payment_terms');
        $leadTimeDays = $this->input('lead_time_days');

        $this->merge([
            'name' => is_string($name)
                ? Str::squish($name)
                : $name,

            'code' => is_string($code)
                ? Str::upper(trim($code))
                : $code,

            'contact_name' => $this->nullableString(
                $contactName,
                true,
            ),

            'email' => is_string($email) && trim($email) !== ''
                ? Str::lower(trim($email))
                : null,

            'phone' => $this->nullableString($phone),

            'payment_terms' => $this->nullableString(
                $paymentTerms,
                true,
            ),

            'lead_time_days' => $leadTimeDays === ''
                ? null
                : $leadTimeDays,

            'active' => $this->boolean('active'),
        ]);
    }

    /**
     * Normalize optional text while retaining null for empty values.
     */
    private function nullableString(
        mixed $value,
        bool $squish = false,
    ): ?string {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        return $squish
            ? Str::squish($value)
            : trim($value);
    }
}
