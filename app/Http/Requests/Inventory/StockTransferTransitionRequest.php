<?php

namespace App\Http\Requests\Inventory;

use App\Enums\OrganizationPermission;
use App\Models\Organization;
use App\Models\StockTransfer;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class StockTransferTransitionRequest extends FormRequest
{
    /**
     * Authorize shipment or cancellation against a tenant-owned transfer.
     */
    public function authorize(): bool
    {
        $user = $this->user();
        $organization = $this->organization();
        $stockTransfer = $this->stockTransfer();

        if (
            ! $user instanceof User
            || $organization === null
            || $stockTransfer === null
        ) {
            return false;
        }

        $permission = $this->routeIs(
            'stock-transfers.ship',
        )
            ? OrganizationPermission::TransfersShip
            : OrganizationPermission::TransfersCreate;

        return Gate::forUser($user)->allows(
            $permission->value,
            $organization,
        );
    }

    /**
     * Shipment and cancellation accept no client-controlled state.
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
     * Resolve the requested transfer only inside the active tenant.
     */
    public function stockTransfer(): ?StockTransfer
    {
        $organization = $this->organization();
        $routeId = $this->route('stockTransfer');

        if (
            $organization === null
            || $routeId === null
            || ! is_numeric($routeId)
        ) {
            return null;
        }

        return StockTransfer::query()
            ->where(
                'organization_id',
                $organization->id,
            )
            ->find((int) $routeId);
    }
}
