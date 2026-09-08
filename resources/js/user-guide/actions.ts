import AiAssistantController from '@/actions/App/Http/Controllers/Ai/AiAssistantController';
import OrganizationBillingController from '@/actions/App/Http/Controllers/Billing/OrganizationBillingController';
import InventoryItemController from '@/actions/App/Http/Controllers/Inventory/InventoryItemController';
import StockCountController from '@/actions/App/Http/Controllers/Inventory/StockCountController';
import StockTransferController from '@/actions/App/Http/Controllers/Inventory/StockTransferController';
import WasteController from '@/actions/App/Http/Controllers/Inventory/WasteController';
import OrganizationController from '@/actions/App/Http/Controllers/OrganizationController';
import OrganizationLocationController from '@/actions/App/Http/Controllers/OrganizationLocationController';
import OrganizationMemberController from '@/actions/App/Http/Controllers/OrganizationMemberController';
import GoodsReceiptController from '@/actions/App/Http/Controllers/Purchasing/GoodsReceiptController';
import PurchaseOrderController from '@/actions/App/Http/Controllers/Purchasing/PurchaseOrderController';
import RecipeController from '@/actions/App/Http/Controllers/Recipes/RecipeController';
import SupplierController from '@/actions/App/Http/Controllers/Suppliers/SupplierController';
import { dashboard } from '@/routes';
import { edit as profileEdit } from '@/routes/profile';
import type { GuideAccessContext, GuideActionKey } from './types';

type AvailableGuideAction = {
    href: string;
    label: string;
};

export function resolveGuideAction(
    key: GuideActionKey,
    context: GuideAccessContext,
): AvailableGuideAction | null {
    switch (key) {
        case 'dashboard':
            return { label: 'Open dashboard', href: dashboard().url };
        case 'profile-settings':
            return { label: 'Open profile settings', href: profileEdit().url };
        case 'ai-assistant':
            return context.aiCanUse
                ? {
                      label: 'Open AI Assistant',
                      href: AiAssistantController.index().url,
                  }
                : null;
        case 'inventory-items':
            return context.hasPermission('inventory.view')
                ? {
                      label: 'Open inventory items',
                      href: InventoryItemController.index().url,
                  }
                : null;
        case 'stock-counts':
            return hasAnyPermission(context, [
                'counts.create',
                'counts.finalize',
                'reports.view',
            ])
                ? {
                      label: 'Open stock counts',
                      href: StockCountController.index().url,
                  }
                : null;
        case 'waste':
            return hasAnyPermission(context, ['waste.record', 'reports.view'])
                ? { label: 'Open waste', href: WasteController.index().url }
                : null;
        case 'stock-transfers':
            return hasAnyPermission(context, [
                'transfers.create',
                'transfers.ship',
                'transfers.receive',
                'reports.view',
            ])
                ? {
                      label: 'Open stock transfers',
                      href: StockTransferController.index().url,
                  }
                : null;
        case 'purchase-orders':
            return canUsePurchasing(context)
                ? {
                      label: 'Open purchase orders',
                      href: PurchaseOrderController.index().url,
                  }
                : null;
        case 'suppliers':
            return canUsePurchasing(context)
                ? {
                      label: 'Open suppliers',
                      href: SupplierController.index().url,
                  }
                : null;
        case 'receiving':
            return canUsePurchasing(context)
                ? {
                      label: 'Open receiving',
                      href: GoodsReceiptController.index().url,
                  }
                : null;
        case 'recipes':
            return context.hasFeature('recipes') &&
                context.hasPermission('recipes.view')
                ? { label: 'Open recipes', href: RecipeController.index().url }
                : null;
        case 'organization-settings':
            return context.activeOrganizationId !== null &&
                context.hasPermission('organization.manage')
                ? {
                      label: 'Open organization settings',
                      href: OrganizationController.edit(
                          context.activeOrganizationId,
                      ).url,
                  }
                : null;
        case 'locations':
            return context.activeOrganizationId !== null &&
                context.hasFeature('locations.multi') &&
                context.hasPermission('locations.manage')
                ? {
                      label: 'Open locations',
                      href: OrganizationLocationController.index(
                          context.activeOrganizationId,
                      ).url,
                  }
                : null;
        case 'members':
            return context.activeOrganizationId !== null &&
                context.hasPermission('users.manage')
                ? {
                      label: 'Open members',
                      href: OrganizationMemberController.index(
                          context.activeOrganizationId,
                      ).url,
                  }
                : null;
        case 'billing':
            return context.activeOrganizationId !== null &&
                context.hasPermission('billing.manage')
                ? {
                      label: 'Open billing',
                      href: OrganizationBillingController.show(
                          context.activeOrganizationId,
                      ).url,
                  }
                : null;
    }
}

function canUsePurchasing(context: GuideAccessContext): boolean {
    return (
        context.hasFeature('purchasing') &&
        context.hasPermission('purchasing.view')
    );
}

function hasAnyPermission(
    context: GuideAccessContext,
    permissions: Parameters<GuideAccessContext['hasPermission']>[0][],
): boolean {
    return permissions.some((permission) => context.hasPermission(permission));
}
