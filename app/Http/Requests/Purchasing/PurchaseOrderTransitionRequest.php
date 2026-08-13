<?php

namespace App\Http\Requests\Purchasing;

use App\Enums\OrganizationPermission;
use App\Models\Organization;
use App\Models\PurchaseOrder;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class PurchaseOrderTransitionRequest extends FormRequest
{
    /**
     * Require purchasing management permission and tenant ownership.
     */
    public function authorize(): bool
    {
        $user = $this->user();
        $organization = $this->organization();

        return $user instanceof User
            && $organization !== null
            && $this->purchaseOrder() !== null
            && Gate::forUser($user)->allows(
                OrganizationPermission::PurchasingManage->value,
                $organization,
            );
    }

    /**
     * No payload is required for explicit PO lifecycle transitions.
     *
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [];
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
     * Resolve the tenant-owned PO.
     */
    public function purchaseOrder(): ?PurchaseOrder
    {
        $organization = $this->organization();
        $routeId = $this->route('purchaseOrder');

        if (
            $organization === null
            || $routeId === null
            || ! is_numeric($routeId)
        ) {
            return null;
        }

        return PurchaseOrder::query()
            ->where('organization_id', $organization->id)
            ->find((int) $routeId);
    }
}
