<?php

namespace App\Http\Requests\Purchasing;

use App\Enums\OrganizationPermission;
use App\Models\GoodsReceipt;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class GoodsReceiptTransitionRequest extends FormRequest
{
    /**
     * Require receiving permission and a tenant-owned receipt.
     */
    public function authorize(): bool
    {
        $user = $this->user();
        $organization = $this->organization();

        return $user instanceof User
            && $organization !== null
            && $this->goodsReceipt() !== null
            && Gate::forUser($user)->allows(
                OrganizationPermission::ReceivingFinalize->value,
                $organization,
            );
    }

    /**
     * Lifecycle transitions require no client-controlled values.
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
        $organization = $this->attributes->get('activeOrganization');

        return $organization instanceof Organization
            ? $organization
            : null;
    }

    /**
     * Resolve the tenant-owned receipt.
     */
    public function goodsReceipt(): ?GoodsReceipt
    {
        $organization = $this->organization();
        $routeId = $this->route('goodsReceipt');

        if (
            $organization === null
            || $routeId === null
            || ! is_numeric($routeId)
        ) {
            return null;
        }

        return GoodsReceipt::query()
            ->where('organization_id', $organization->id)
            ->find((int) $routeId);
    }
}
