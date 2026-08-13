<?php

namespace App\Http\Requests\Inventory;

use App\Enums\OrganizationPermission;
use App\Models\Organization;
use App\Models\StockCount;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class StockCountTransitionRequest extends FormRequest
{
    /**
     * Authorize lifecycle operations against a tenant-owned count.
     */
    public function authorize(): bool
    {
        $user = $this->user();
        $organization = $this->organization();
        $stockCount = $this->stockCount();

        if (
            ! $user instanceof User
            || $organization === null
            || $stockCount === null
        ) {
            return false;
        }

        $permission = $this->routeIs(
            'stock-counts.finalize',
        )
            ? OrganizationPermission::CountsFinalize
            : OrganizationPermission::CountsCreate;

        return Gate::forUser($user)->allows(
            $permission->value,
            $organization,
        );
    }

    /**
     * Lifecycle transitions accept no client-controlled state.
     *
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [];
    }

    /**
     * Return active tenant context.
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
     * Resolve the requested count only inside the active tenant.
     */
    public function stockCount(): ?StockCount
    {
        $organization = $this->organization();
        $routeId = $this->route('stockCount');

        if (
            $organization === null
            || $routeId === null
            || ! is_numeric($routeId)
        ) {
            return null;
        }

        return StockCount::query()
            ->where(
                'organization_id',
                $organization->id,
            )
            ->find((int) $routeId);
    }
}
