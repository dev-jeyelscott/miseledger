import { Head } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { PageHeader } from '@/components/page-header';
import AppLayout from '@/layouts/app-layout';
import { index } from '@/routes/user-guide';

type Props = {
    module: string;
};

function formatModuleTitle(module: string): string {
    return module
        .split('-')
        .map((part) => part.charAt(0).toUpperCase() + part.slice(1))
        .join(' ');
}

export default function UserGuideShow({ module }: Props) {
    const title = formatModuleTitle(module);

    return (
        <>
            <Head title={`${title} | User Guide`} />

            <div className="flex flex-1 flex-col gap-6 p-4 sm:p-6">
                <PageHeader
                    title={title}
                    description="This guide is being prepared."
                />

                <section className="rounded-lg border border-border bg-card p-4 sm:p-5">
                    <p className="text-sm text-muted-foreground">
                        Check back soon for step-by-step help with this feature.
                    </p>
                </section>
            </div>
        </>
    );
}

UserGuideShow.layout = (page: ReactNode) => (
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
