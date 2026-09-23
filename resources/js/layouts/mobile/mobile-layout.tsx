import { Link, router, usePage } from '@inertiajs/react';
import {
    Boxes,
    Home,
    ListChecks,
    MoreHorizontal,
    ScanLine,
} from 'lucide-react';
import { useEffect } from 'react';
import type { ComponentType, ReactNode } from 'react';

import { InstallHint } from '@/components/mobile/install-hint';
import { cn } from '@/lib/utils';
import { registerMobileServiceWorker } from '@/pwa/register-mobile-service-worker';
import mobile from '@/routes/mobile';
import type {
    MobileActiveLocation,
    MobileOrganizationSummary,
} from '@/types/mobile';

type MobileTab = {
    href: string;
    icon: ComponentType<{ className?: string }>;
    label: string;
    /** Every other tab's page ships in a later spec; they route to Home for now. */
    pathPrefix: string;
};

const tabs: MobileTab[] = [
    {
        href: mobile.home.url(),
        icon: Home,
        label: 'Home',
        pathPrefix: '/mobile',
    },
    {
        href: mobile.scan.index.url(),
        icon: ScanLine,
        label: 'Scan',
        pathPrefix: '/mobile/scan',
    },
    {
        href: mobile.home.url(),
        icon: ListChecks,
        label: 'Tasks',
        pathPrefix: '/mobile/tasks',
    },
    {
        href: mobile.home.url(),
        icon: Boxes,
        label: 'Stock',
        pathPrefix: '/mobile/stock',
    },
    {
        href: mobile.home.url(),
        icon: MoreHorizontal,
        label: 'More',
        pathPrefix: '/mobile/more',
    },
];

type MobileLayoutProps = {
    activeLocation?: MobileActiveLocation;
    children: ReactNode;
    organization?: MobileOrganizationSummary | null;
};

/** Bottom-nav mobile PWA shell: top bar with org/location, tabs, safe-area padding. */
export default function MobileLayout({
    activeLocation = null,
    children,
    organization = null,
}: MobileLayoutProps) {
    const { url } = usePage();
    const currentPath = url.split('?')[0];

    useEffect(() => {
        registerMobileServiceWorker();
    }, []);

    return (
        <div className="flex min-h-dvh flex-col bg-background text-foreground">
            <header className="flex items-center justify-between border-b px-4 py-3">
                <div className="min-w-0">
                    <p className="truncate text-sm font-semibold">
                        {organization?.name ?? 'MiseLedger'}
                    </p>
                    <button
                        type="button"
                        onClick={() =>
                            router.visit(mobile.location.index.url())
                        }
                        className="truncate text-xs text-muted-foreground underline-offset-2 hover:underline"
                    >
                        {activeLocation
                            ? activeLocation.name
                            : 'Select a location'}
                    </button>
                </div>
            </header>

            <main
                className="flex-1 overflow-y-auto px-4 py-4"
                style={{
                    paddingBottom: 'calc(4.5rem + env(safe-area-inset-bottom))',
                }}
            >
                {children}
            </main>

            <InstallHint />

            <nav
                aria-label="Primary"
                className="fixed inset-x-0 bottom-0 z-40 border-t bg-background"
                style={{ paddingBottom: 'env(safe-area-inset-bottom)' }}
            >
                <ul className="grid grid-cols-5">
                    {tabs.map((tab) => {
                        const Icon = tab.icon;
                        const isActive =
                            tab.pathPrefix === '/mobile'
                                ? currentPath === '/mobile'
                                : currentPath.startsWith(tab.pathPrefix);

                        return (
                            <li key={tab.label}>
                                <Link
                                    href={tab.href}
                                    className={cn(
                                        'flex min-h-[44px] flex-col items-center justify-center gap-1 py-2 text-xs',
                                        isActive
                                            ? 'text-primary'
                                            : 'text-muted-foreground',
                                    )}
                                >
                                    <Icon
                                        className="size-5"
                                        aria-hidden="true"
                                    />
                                    <span>{tab.label}</span>
                                </Link>
                            </li>
                        );
                    })}
                </ul>
            </nav>
        </div>
    );
}
