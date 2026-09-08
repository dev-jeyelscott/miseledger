import { Head } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { PageHeader } from '@/components/page-header';
import AppLayout from '@/layouts/app-layout';
import { index } from '@/routes/user-guide';

export default function UserGuideIndex() {
    return (
        <>
            <Head title="User Guide" />

            <div className="flex flex-1 flex-col gap-6 p-4 sm:p-6">
                <PageHeader
                    title="User Guide"
                    description="Learn how to use MiseLedger features."
                />

                <section className="rounded-lg border border-border bg-card p-4 sm:p-5">
                    <p className="text-sm text-muted-foreground">
                        Guides for MiseLedger features will be added here.
                    </p>
                </section>
            </div>
        </>
    );
}

UserGuideIndex.layout = (page: ReactNode) => (
    <AppLayout
        breadcrumbs={[
            {
                title: 'User Guide',
                href: index(),
            },
        ]}
    >
        {page}
    </AppLayout>
);
