import { Link } from '@inertiajs/react';
import {
    Activity,
    AlertTriangle,
    Building2,
    CreditCard,
    FileText,
    HeartPulse,
    LayoutDashboard,
    Package,
    Users,
} from 'lucide-react';

import PlatformAlertController from '@/actions/App/Http/Controllers/Platform/PlatformAlertController';
import PlatformBillingController from '@/actions/App/Http/Controllers/Platform/PlatformBillingController';
import PlatformContentController from '@/actions/App/Http/Controllers/Platform/PlatformContentController';
import PlatformHealthController from '@/actions/App/Http/Controllers/Platform/PlatformHealthController';
import PlatformObservabilityController from '@/actions/App/Http/Controllers/Platform/PlatformObservabilityController';
import PlatformOrganizationController from '@/actions/App/Http/Controllers/Platform/PlatformOrganizationController';
import PlatformProductCatalogController from '@/actions/App/Http/Controllers/Platform/PlatformProductCatalogController';
import PlatformUserController from '@/actions/App/Http/Controllers/Platform/PlatformUserController';
import { AppContent } from '@/components/app-content';
import AppLogo from '@/components/app-logo';
import { AppShell } from '@/components/app-shell';
import { AppSidebarHeader } from '@/components/app-sidebar-header';
import { NavMain } from '@/components/nav-main';
import type { NavGroup } from '@/components/nav-main';
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
import { dashboard as platformDashboard } from '@/routes/admin';
import type { AppLayoutProps } from '@/types';

const platformNavigation: NavGroup[] = [
    {
        title: 'Platform',
        items: [
            {
                title: 'Dashboard',
                href: platformDashboard(),
                icon: LayoutDashboard,
            },
            {
                title: 'Users',
                href: PlatformUserController.index(),
                icon: Users,
            },
            {
                title: 'Organizations',
                href: PlatformOrganizationController.index(),
                icon: Building2,
            },
            {
                title: 'Billing & Revenue',
                href: PlatformBillingController.index(),
                icon: CreditCard,
            },
            {
                title: 'Product Catalog',
                href: PlatformProductCatalogController.index(),
                icon: Package,
            },
            {
                title: 'Content',
                href: PlatformContentController.index(),
                icon: FileText,
            },
            {
                title: 'Observability',
                href: PlatformObservabilityController.index(),
                icon: Activity,
            },
            {
                title: 'Health',
                href: PlatformHealthController.index(),
                icon: HeartPulse,
            },
            {
                title: 'Alerts',
                href: PlatformAlertController.index(),
                icon: AlertTriangle,
            },
        ],
    },
];

/** Render the platform administration shell without tenant-specific application chrome. */
export default function PlatformLayout({
    children,
    breadcrumbs = [],
}: AppLayoutProps) {
    return (
        <AppShell variant="sidebar">
            <Sidebar collapsible="icon" variant="inset">
                <SidebarHeader className="gap-1">
                    <SidebarMenu>
                        <SidebarMenuItem>
                            <SidebarMenuButton size="lg" asChild>
                                <Link href={platformDashboard()} prefetch>
                                    <AppLogo />
                                </Link>
                            </SidebarMenuButton>
                        </SidebarMenuItem>
                    </SidebarMenu>
                </SidebarHeader>

                <SidebarContent className="gap-0">
                    <NavMain groups={platformNavigation} />
                </SidebarContent>

                <SidebarFooter>
                    <NavUser />
                </SidebarFooter>
            </Sidebar>

            <AppContent variant="sidebar" className="overflow-x-clip">
                <AppSidebarHeader breadcrumbs={breadcrumbs} />
                {children}
            </AppContent>
        </AppShell>
    );
}
