import { Link, usePage } from '@inertiajs/react';
import {
    ArrowLeftRight,
    BookOpen,
    Boxes,
    ClipboardCheck,
    ClipboardList,
    FolderGit2,
    LayoutGrid,
    PackageCheck,
    Trash2,
    Truck,
} from 'lucide-react';
import InventoryItemController from '@/actions/App/Http/Controllers/Inventory/InventoryItemController';
import StockCountController from '@/actions/App/Http/Controllers/Inventory/StockCountController';
import StockTransferController from '@/actions/App/Http/Controllers/Inventory/StockTransferController';
import WasteController from '@/actions/App/Http/Controllers/Inventory/WasteController';
import GoodsReceiptController from '@/actions/App/Http/Controllers/Purchasing/GoodsReceiptController';
import PurchaseOrderController from '@/actions/App/Http/Controllers/Purchasing/PurchaseOrderController';
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
