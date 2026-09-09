import { AiAssistantDrawer } from '@/components/ai-assistant-drawer';
import { AppContent } from '@/components/app-content';
import { AppShell } from '@/components/app-shell';
import { AppSidebar } from '@/components/app-sidebar';
import { AppSidebarHeader } from '@/components/app-sidebar-header';
import { SubscriptionNotice } from '@/components/subscription-notice';
import type { AppLayoutProps } from '@/types';

/** Renders the authenticated sidebar shell and its single global AI Assistant entry point. */
export default function AppSidebarLayout({
    children,
    breadcrumbs = [],
}: AppLayoutProps) {
    return (
        <AppShell variant="sidebar">
            <AppSidebar />
            <AppContent variant="sidebar" className="overflow-x-clip">
                <AppSidebarHeader breadcrumbs={breadcrumbs} />
                <SubscriptionNotice />
                {children}
                <div className="fixed right-[max(1rem,env(safe-area-inset-right))] bottom-[max(1rem,env(safe-area-inset-bottom))] z-40">
                    <AiAssistantDrawer />
                </div>
            </AppContent>
        </AppShell>
    );
}
