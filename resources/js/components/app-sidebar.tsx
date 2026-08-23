import { Link, usePage } from '@inertiajs/react';
import {
    AlertTriangle,
    ArrowLeftRight,
    Boxes,
    ClipboardCheck,
    ClipboardList,
    Coins,
    CreditCard,
    History,
    LayoutGrid,
    MapPin,
    NotebookText,
    PackageCheck,
    PackageSearch,
    Ruler,
    Settings,
    Tags,
    Trash2,
    Truck,
    Users,
} from 'lucide-react';
import OrganizationBillingController from '@/actions/App/Http/Controllers/Billing/OrganizationBillingController';
import InventoryCategoryController from '@/actions/App/Http/Controllers/Inventory/InventoryCategoryController';
import InventoryItemController from '@/actions/App/Http/Controllers/Inventory/InventoryItemController';
import InventoryValuationReportController from '@/actions/App/Http/Controllers/Inventory/InventoryValuationReportController';
import LowStockReportController from '@/actions/App/Http/Controllers/Inventory/LowStockReportController';
import PurchasingHistoryReportController from '@/actions/App/Http/Controllers/Inventory/PurchasingHistoryReportController';
import StockCountController from '@/actions/App/Http/Controllers/Inventory/StockCountController';
import StockMovementLedgerReportController from '@/actions/App/Http/Controllers/Inventory/StockMovementLedgerReportController';
import StockOnHandReportController from '@/actions/App/Http/Controllers/Inventory/StockOnHandReportController';
import StockTransferController from '@/actions/App/Http/Controllers/Inventory/StockTransferController';
import UnitOfMeasureController from '@/actions/App/Http/Controllers/Inventory/UnitOfMeasureController';
import WasteController from '@/actions/App/Http/Controllers/Inventory/WasteController';
import OrganizationController from '@/actions/App/Http/Controllers/OrganizationController';
import OrganizationLocationController from '@/actions/App/Http/Controllers/OrganizationLocationController';
import OrganizationMemberController from '@/actions/App/Http/Controllers/OrganizationMemberController';
import GoodsReceiptController from '@/actions/App/Http/Controllers/Purchasing/GoodsReceiptController';
import PurchaseOrderController from '@/actions/App/Http/Controllers/Purchasing/PurchaseOrderController';
import RecipeController from '@/actions/App/Http/Controllers/Recipes/RecipeController';
import SupplierController from '@/actions/App/Http/Controllers/Suppliers/SupplierController';
import AppLogo from '@/components/app-logo';
import { NavMain } from '@/components/nav-main';
import type { NavGroup } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import { OrganizationSwitcher } from '@/components/organization-switcher';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import type { NavItem, OrganizationContext } from '@/types';

type PageProps = {
    organizationContext: OrganizationContext;
};

/**
 * Render permission-aware navigation grouped around normal inventory workflows.
 */
export function AppSidebar() {
    const { organizationContext } = usePage<PageProps>().props;

    const activeMembership = organizationContext.memberships.find(
        (membership) =>
            membership.organization.id === organizationContext.active?.id,
    );

    const permissions = new Set(activeMembership?.permissions ?? []);

    const overviewNavItems: NavItem[] = [
        {
            title: 'Dashboard',
            href: dashboard(),
            icon: LayoutGrid,
        },
    ];

    const inventoryNavItems: NavItem[] = [];

    if (permissions.has('inventory.view')) {
        inventoryNavItems.push(
            {
                title: 'Items',
                href: InventoryItemController.index(),
                icon: Boxes,
            },
            {
                title: 'Categories',
                href: InventoryCategoryController.index(),
                icon: Tags,
            },
            {
                title: 'Units of measure',
                href: UnitOfMeasureController.index(),
                icon: Ruler,
            },
        );
    }

    if (
        permissions.has('counts.create') ||
        permissions.has('counts.finalize') ||
        permissions.has('reports.view')
    ) {
        inventoryNavItems.push({
            title: 'Stock counts',
            href: StockCountController.index(),
            icon: ClipboardCheck,
        });
    }

    if (permissions.has('waste.record') || permissions.has('reports.view')) {
        inventoryNavItems.push({
            title: 'Waste',
            href: WasteController.index(),
            icon: Trash2,
        });
    }

    if (
        permissions.has('transfers.create') ||
        permissions.has('transfers.ship') ||
        permissions.has('transfers.receive') ||
        permissions.has('reports.view')
    ) {
        inventoryNavItems.push({
            title: 'Stock transfers',
            href: StockTransferController.index(),
            icon: ArrowLeftRight,
        });
    }

    const purchasingNavItems: NavItem[] = [];

    if (permissions.has('purchasing.view')) {
        purchasingNavItems.push(
            {
                title: 'Suppliers',
                href: SupplierController.index(),
                icon: Truck,
            },
            {
                title: 'Purchase orders',
                href: PurchaseOrderController.index(),
                icon: ClipboardList,
            },
            {
                title: 'Receiving',
                href: GoodsReceiptController.index(),
                icon: PackageCheck,
            },
        );
    }

    const recipeNavItems: NavItem[] = [];

    if (permissions.has('recipes.view')) {
        recipeNavItems.push({
            title: 'Recipes',
            href: RecipeController.index(),
            icon: NotebookText,
        });
    }

    const reportNavItems: NavItem[] = [];

    if (permissions.has('reports.view')) {
        reportNavItems.push(
            {
                title: 'Stock on hand',
                href: StockOnHandReportController.index(),
                icon: PackageSearch,
            },
            {
                title: 'Low stock',
                href: LowStockReportController.index(),
                icon: AlertTriangle,
            },
            {
                title: 'Stock movement ledger',
                href: StockMovementLedgerReportController.index(),
                icon: ClipboardList,
            },
            {
                title: 'Inventory valuation',
                href: InventoryValuationReportController.index(),
                icon: Coins,
            },
            {
                title: 'Purchasing history',
                href: PurchasingHistoryReportController.index(),
                icon: History,
            },
        );
    }

    const organizationNavItems: NavItem[] = [];
    const activeOrganization = organizationContext.active;

    if (activeOrganization !== null) {
        if (permissions.has('locations.manage')) {
            organizationNavItems.push({
                title: 'Locations',
                href: OrganizationLocationController.index(
                    activeOrganization.id,
                ),
                icon: MapPin,
            });
        }

        if (permissions.has('users.manage')) {
            organizationNavItems.push({
                title: 'Members',
                href: OrganizationMemberController.index(activeOrganization.id),
                icon: Users,
            });
        }

        if (permissions.has('organization.manage')) {
            organizationNavItems.push({
                title: 'Settings',
                href: OrganizationController.edit(activeOrganization.id),
                icon: Settings,
            });
        }

        if (permissions.has('billing.manage')) {
            organizationNavItems.push({
                title: 'Billing',
                href: OrganizationBillingController.show(activeOrganization.id),
                icon: CreditCard,
            });
        }
    }

    const navigationGroups: NavGroup[] = [
        {
            title: null,
            items: overviewNavItems,
        },
        {
            title: 'Inventory',
            items: inventoryNavItems,
        },
        {
            title: 'Purchasing',
            items: purchasingNavItems,
        },
        {
            title: 'Recipes',
            items: recipeNavItems,
        },
        {
            title: 'Reports',
            items: reportNavItems,
        },
        {
            title: 'Organization',
            items: organizationNavItems,
        },
    ];

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader className="gap-1">
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={dashboard()} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>

                <OrganizationSwitcher
                    organizationContext={organizationContext}
                />
            </SidebarHeader>

            <SidebarContent className="gap-0">
                <NavMain groups={navigationGroups} />
            </SidebarContent>

            <SidebarFooter>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
