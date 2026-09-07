import { Head, usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { AiAssistant } from '@/components/ai-assistant';
import { PageHeader } from '@/components/page-header';
import AppLayout from '@/layouts/app-layout';
import type {
    AiAssistantData,
    BreadcrumbItem,
    OrganizationContext,
} from '@/types';

export default function AiAssistantPage(props: AiAssistantData) {
    const { organizationContext } = usePage<{
        organizationContext: OrganizationContext;
    }>().props;

    return (
        <>
            <Head title="AI Assistant" />
            <div className="flex flex-1 flex-col gap-6 p-4 sm:p-6">
                <PageHeader
                    title="AI Assistant"
                    description="Ask context-aware questions about your active organization."
                />
                <AiAssistant {...props} access={organizationContext.ai} />
            </div>
        </>
    );
}

AiAssistantPage.layout = (page: ReactNode) => (
    <AppLayout
        breadcrumbs={[
            { title: 'AI Assistant', href: '/ai' } satisfies BreadcrumbItem,
        ]}
    >
        {page}
    </AppLayout>
);
