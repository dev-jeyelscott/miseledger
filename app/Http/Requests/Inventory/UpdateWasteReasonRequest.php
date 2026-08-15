<?php

namespace App\Http\Requests\Inventory;

use App\Enums\OrganizationPermission;
use App\Models\Organization;
use App\Models\User;
use App\Models\WasteReason;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class UpdateWasteReasonRequest extends FormRequest
{
    /**
     * Require inventory-adjust permission and a tenant-owned reason.
     */
    public function authorize(): bool
    {
        $user = $this->user();
        $organization = $this->organization();

        return $user instanceof User
            && $organization !== null
            && $this->wasteReason() !== null
            && Gate::forUser($user)->allows(
                OrganizationPermission::InventoryAdjust->value,
                $organization,
            );
    }

    /**
     * Only activation state is mutable after reason creation.
     *
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'active' => [
                'required',
                'boolean',
            ],
        ];
    }

    /**
     * Return active organization middleware context.
     */
    public function organization(): ?Organization
    {
        $organization = $this->attributes->get(
            'activeOrganization',
        );

        return $organization instanceof Organization
            ? $organization
            : null;
    }

    /**
     * Resolve the target reason strictly inside the active tenant.
     */
    public function wasteReason(): ?WasteReason
    {
        $organization = $this->organization();
        $routeId = $this->route('wasteReason');

        if (
            $organization === null
            || $routeId === null
            || ! is_numeric($routeId)
        ) {
            return null;
        }

        return WasteReason::query()
            ->where('organization_id', $organization->id)
            ->find((int) $routeId);
    }
}
