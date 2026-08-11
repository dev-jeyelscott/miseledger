<?php

namespace App\Enums;

enum OrganizationRole: string
{
    case Owner = 'owner';
    case Manager = 'manager';
    case InventoryStaff = 'inventory_staff';
    case KitchenStaff = 'kitchen_staff';
    case Auditor = 'auditor';

    /**
     * Return the fixed MVP permissions assigned to this role.
     *
     * @return list<OrganizationPermission>
     */
    public function permissions(): array
    {
        return match ($this) {
            self::Owner => OrganizationPermission::cases(),

            self::Manager => [
                OrganizationPermission::InventoryView,
                OrganizationPermission::InventoryAdjust,
                OrganizationPermission::PurchasingView,
                OrganizationPermission::PurchasingManage,
                OrganizationPermission::ReceivingFinalize,
                OrganizationPermission::CountsCreate,
                OrganizationPermission::CountsFinalize,
                OrganizationPermission::WasteRecord,
                OrganizationPermission::TransfersCreate,
                OrganizationPermission::TransfersShip,
                OrganizationPermission::TransfersReceive,
                OrganizationPermission::RecipesView,
                OrganizationPermission::RecipesManage,
                OrganizationPermission::ReportsView,
                OrganizationPermission::CostsView,
            ],

            self::InventoryStaff => [
                OrganizationPermission::InventoryView,
                OrganizationPermission::InventoryAdjust,
                OrganizationPermission::PurchasingView,
                OrganizationPermission::ReceivingFinalize,
                OrganizationPermission::CountsCreate,
                OrganizationPermission::CountsFinalize,
                OrganizationPermission::WasteRecord,
                OrganizationPermission::TransfersCreate,
                OrganizationPermission::TransfersShip,
                OrganizationPermission::TransfersReceive,
                OrganizationPermission::ReportsView,
            ],

            self::KitchenStaff => [
                OrganizationPermission::InventoryView,
                OrganizationPermission::WasteRecord,
                OrganizationPermission::RecipesView,
            ],

            self::Auditor => [
                OrganizationPermission::InventoryView,
                OrganizationPermission::PurchasingView,
                OrganizationPermission::RecipesView,
                OrganizationPermission::ReportsView,
                OrganizationPermission::CostsView,
            ],
        };
    }

    /**
     * Determine whether this role includes the requested permission.
     */
    public function allows(OrganizationPermission $permission): bool
    {
        return in_array($permission, $this->permissions(), true);
    }
}
