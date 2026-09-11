import { Head } from '@inertiajs/react';
import { ShieldCheck } from 'lucide-react';

import { PageHeader } from '@/components/page-header';

/** Render the initial platform console without inventing unimplemented platform metrics. */
export default function PlatformDashboard() {
    return (
        <>
            <Head title="Platform Console" />

            <div className="flex flex-1 flex-col gap-6 p-4 sm:p-6">
                <PageHeader
                    title="Platform Console"
                    description="Platform-level administration for explicitly granted MiseLedger users."
                />

                <section
                    aria-labelledby="platform-boundary-heading"
                    className="max-w-3xl rounded-xl border border-border bg-card p-5"
                >
                    <div className="flex items-start gap-3">
                        <div className="flex size-10 shrink-0 items-center justify-center rounded-lg bg-muted text-muted-foreground">
                            <ShieldCheck
                                className="size-5"
                                aria-hidden="true"
                            />
                        </div>

                        <div className="min-w-0">
                            <h2
                                id="platform-boundary-heading"
                                className="text-sm font-semibold"
                            >
                                Platform security boundary
                            </h2>
                            <p className="mt-1 text-sm leading-6 text-muted-foreground">
                                Phase 1 establishes platform-level access
                                independently from organization roles,
                                memberships, subscriptions, and tenant
                                entitlements. Platform management workflows are
                                intentionally not included yet.
                            </p>
                        </div>
                    </div>
                </section>
            </div>
        </>
    );
}
