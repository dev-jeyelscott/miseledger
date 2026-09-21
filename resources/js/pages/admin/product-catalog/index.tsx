import { Head, Link } from '@inertiajs/react';
import { Package } from 'lucide-react';

import PlatformProductCatalogController from '@/actions/App/Http/Controllers/Platform/PlatformProductCatalogController';
import { EmptyState } from '@/components/empty-state';
import { PageHeader } from '@/components/page-header';
import { StatusBadge } from '@/components/status-badge';

type PlanSummary = {
    planCode: string;
    name: string;
    tier: number;
    hasCurrentVersion: boolean;
    currentVersionNumber: number | null;
    draftCount: number;
    supersededCount: number;
    subscriberCount: number;
};

type Props = {
    plans: PlanSummary[];
};

/** Present the recognized plan's lifecycle state without inferring status. */
function PlanStatusBadge({ plan }: { plan: PlanSummary }) {
    if (plan.hasCurrentVersion) {
        return <StatusBadge label="Published" variant="success" />;
    }

    if (plan.draftCount > 0) {
        return <StatusBadge label="Draft only" variant="warning" />;
    }

    return <StatusBadge label="No versions" variant="neutral" />;
}

/** Render every recognized plan code with its current/draft/superseded summary. */
export default function PlatformProductCatalogIndex({ plans }: Props) {
    return (
        <>
            <Head title="Product Catalog" />

            <div className="flex flex-1 flex-col gap-6 p-4 sm:p-6">
                <PageHeader
                    title="Product Catalog"
                    description="Recognized commercial plan codes and their version lifecycle. Publishing never reprices or moves existing subscribers."
                />

                <section
                    aria-label="Recognized plans"
                    className="overflow-hidden rounded-xl border border-border bg-card"
                >
                    {plans.length === 0 ? (
                        <EmptyState
                            className="px-4 py-12"
                            icon={Package}
                            title="No recognized plans"
                            description="No plan codes are configured in the commercial catalog."
                        />
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full min-w-[720px] text-sm">
                                <caption className="sr-only">
                                    Recognized commercial plan codes
                                </caption>
                                <thead className="border-b border-border bg-muted/30 text-left text-xs text-muted-foreground">
                                    <tr>
                                        <th
                                            scope="col"
                                            className="px-4 py-3 font-medium"
                                        >
                                            Plan
                                        </th>
                                        <th
                                            scope="col"
                                            className="px-4 py-3 font-medium"
                                        >
                                            Status
                                        </th>
                                        <th
                                            scope="col"
                                            className="px-4 py-3 font-medium"
                                        >
                                            Current version
                                        </th>
                                        <th
                                            scope="col"
                                            className="px-4 py-3 font-medium"
                                        >
                                            Drafts
                                        </th>
                                        <th
                                            scope="col"
                                            className="px-4 py-3 font-medium"
                                        >
                                            Superseded
                                        </th>
                                        <th
                                            scope="col"
                                            className="px-4 py-3 text-right font-medium"
                                        >
                                            Subscribers
                                        </th>
                                    </tr>
                                </thead>

                                <tbody className="divide-y divide-border">
                                    {plans.map((plan) => (
                                        <tr
                                            key={plan.planCode}
                                            className="hover:bg-muted/30"
                                        >
                                            <td className="px-4 py-3">
                                                <Link
                                                    href={PlatformProductCatalogController.show(
                                                        plan.planCode,
                                                    )}
                                                    className="rounded-sm font-medium hover:underline focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                                >
                                                    {plan.name}
                                                </Link>
                                                <p className="mt-0.5 font-mono text-xs text-muted-foreground">
                                                    {plan.planCode}
                                                </p>
                                            </td>

                                            <td className="px-4 py-3">
                                                <PlanStatusBadge plan={plan} />
                                            </td>

                                            <td className="px-4 py-3 tabular-nums">
                                                {plan.currentVersionNumber ??
                                                    'None'}
                                            </td>

                                            <td className="px-4 py-3 tabular-nums">
                                                {plan.draftCount}
                                            </td>

                                            <td className="px-4 py-3 tabular-nums">
                                                {plan.supersededCount}
                                            </td>

                                            <td className="px-4 py-3 text-right tabular-nums">
                                                {plan.subscriberCount.toLocaleString()}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </section>
            </div>
        </>
    );
}
