import { Link, usePage } from '@inertiajs/react';
import {
    AlertTriangle,
    ArrowLeftRight,
    BookOpen,
    Boxes,
    ClipboardCheck,
    ClipboardList,
    Coins,
    FolderGit2,
    History,
    LayoutGrid,
    NotebookText,
    PackageCheck,
    PackageSearch,
    Trash2,
    Truck,
} from 'lucide-react';
import InventoryItemController from '@/actions/App/Http/Controllers/Inventory/InventoryItemController';
import InventoryValuationReportController from '@/actions/App/Http/Controllers/Inventory/InventoryValuationReportController';
import LowStockReportController from '@/actions/App/Http/Controllers/Inventory/LowStockReportController';
import PurchasingHistoryReportController from '@/actions/App/Http/Controllers/Inventory/PurchasingHistoryReportController';
import StockCountController from '@/actions/App/Http/Controllers/Inventory/StockCountController';
import StockMovementLedgerReportController from '@/actions/App/Http/Controllers/Inventory/StockMovementLedgerReportController';
import StockOnHandReportController from '@/actions/App/Http/Controllers/Inventory/StockOnHandReportController';
import StockTransferController from '@/actions/App/Http/Controllers/Inventory/StockTransferController';
import WasteController from '@/actions/App/Http/Controllers/Inventory/WasteController';
import GoodsReceiptController from '@/actions/App/Http/Controllers/Purchasing/GoodsReceiptController';
import PurchaseOrderController from '@/actions/App/Http/Controllers/Purchasing/PurchaseOrderController';
import RecipeController from '@/actions/App/Http/Controllers/Recipes/RecipeController';
import SupplierController from '@/actions/App/Http/Controllers/Suppliers/SupplierController';
import AppLogo from '@/components/app-logo';
import { NavFooter } from '@/components/nav-footer';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
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

const footerNavItems: NavItem[] = [
    {
        title: 'Repository',
        href: 'https://github.com/laravel/react-starter-kit',
        icon: FolderGit2,
    },
    {
        title: 'Documentation',
        href: 'https://laravel.com/docs/starter-kits#react',
        icon: BookOpen,
    },
];

export function AppSidebar() {
    const { organizationContext } = usePage<PageProps>().props;

    const activeMembership = organizationContext.memberships.find(
        (membership) =>
            membership.organization.id === organizationContext.active?.id,
    );

    const mainNavItems: NavItem[] = [
        {
            title: 'Dashboard',
            href: dashboard(),
            icon: LayoutGrid,
        },
    ];

    if (activeMembership?.permissions.includes('inventory.view')) {
        mainNavItems.push({
            title: 'Inventory',
            href: InventoryItemController.index(),
            icon: Boxes,
        });
    }

    if (activeMembership?.permissions.includes('reports.view')) {
        mainNavItems.push({
            title: 'Stock on hand',
            href: StockOnHandReportController.index(),
            icon: PackageSearch,
        });

        mainNavItems.push({
            title: 'Low stock',
            href: LowStockReportController.index(),
            icon: AlertTriangle,
        });

        mainNavItems.push({
            title: 'Stock movement ledger',
            href: StockMovementLedgerReportController.index(),
            icon: ClipboardList,
        });

        mainNavItems.push({
            title: 'Inventory valuation',
            href: InventoryValuationReportController.index(),
            icon: Coins,
        });

        mainNavItems.push({
            title: 'Purchasing history',
            href: PurchasingHistoryReportController.index(),
            icon: History,
        });
    }

    if (activeMembership?.permissions.includes('purchasing.view')) {
        mainNavItems.push(
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

    if (
        activeMembership?.permissions.includes('counts.create') ||
        activeMembership?.permissions.includes('counts.finalize') ||
        activeMembership?.permissions.includes('reports.view')
    ) {
        mainNavItems.push({
            title: 'Stock counts',
            href: StockCountController.index(),
            icon: ClipboardCheck,
        });
    }

    if (
        activeMembership?.permissions.includes('waste.record') ||
        activeMembership?.permissions.includes('reports.view')
    ) {
        mainNavItems.push({
            title: 'Waste',
            href: WasteController.index(),
            icon: Trash2,
        });
    }

    if (
        activeMembership?.permissions.includes('transfers.create') ||
        activeMembership?.permissions.includes('transfers.ship') ||
        activeMembership?.permissions.includes('transfers.receive') ||
        activeMembership?.permissions.includes('reports.view')
    ) {
        mainNavItems.push({
            title: 'Stock transfers',
            href: StockTransferController.index(),
            icon: ArrowLeftRight,
        });
    }

    if (activeMembership?.permissions.includes('recipes.view')) {
        mainNavItems.push({
            title: 'Recipes',
            href: RecipeController.index(),
            icon: NotebookText,
        });
    }

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={dashboard()} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={mainNavItems} />
            </SidebarContent>

            <SidebarFooter>
                <NavFooter items={footerNavItems} className="mt-auto" />
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
