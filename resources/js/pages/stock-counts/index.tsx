import { Head, Link } from '@inertiajs/react';
import StockCountController from '@/actions/App/Http/Controllers/Inventory/StockCountController';
import { Button } from '@/components/ui/button';
import { dashboard } from '@/routes';

type StockCountSummary = {
    id: number;
    number: string;
    status: string;
    locationName: string;
    storageLocationName: string;
    countedAt: string | null;
    finalizedAt: string | null;
};

type Props = {
    counts: StockCountSummary[];
    canCreate: boolean;
    canViewReport: boolean;
};

const formatDate = (value: string | null): string =>
    value === null ? '—' : new Date(value).toLocaleString();

export default function StockCountIndex({
    counts,
    canCreate,
    canViewReport,
}: Props) {
    return (
        <>
            <Head title="Stock counts" />

            <div className="flex flex-1 flex-col gap-6 p-4">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h1 className="text-2xl font-semibold">Stock counts</h1>
                        <p className="mt-1 text-sm text-muted-foreground">
                            Reconcile physical stock with the inventory ledger.
                        </p>
                    </div>

                    <div className="flex gap-2">
                        {canViewReport && (
                            <Button variant="outline" asChild>
                                <Link href={StockCountController.variance()}>
                                    Variance report
                                </Link>
                            </Button>
                        )}

                        {canCreate && (
                            <Button asChild>
                                <Link href={StockCountController.create()}>
                                    New stock count
                                </Link>
                            </Button>
                        )}
                    </div>
                </div>

                <div className="overflow-x-auto rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                    <table className="w-full text-sm">
                        <thead className="border-b text-left">
                            <tr>
                                <th className="px-4 py-3">Number</th>
                                <th className="px-4 py-3">Location</th>
                                <th className="px-4 py-3">Storage</th>
                                <th className="px-4 py-3">Status</th>
                                <th className="px-4 py-3">Counted</th>
                                <th className="px-4 py-3">Finalized</th>
                            </tr>
                        </thead>
                        <tbody>
                            {counts.length === 0 ? (
                                <tr>
                                    <td
                                        colSpan={6}
                                        className="px-4 py-8 text-center text-muted-foreground"
                                    >
                                        No stock counts yet.
                                    </td>
                                </tr>
                            ) : (
                                counts.map((count) => (
                                    <tr
                                        key={count.id}
                                        className="border-b last:border-b-0"
                                    >
                                        <td className="px-4 py-3 font-medium">
                                            <Link
                                                href={StockCountController.edit(
                                                    count.id,
                                                )}
                                                className="hover:underline"
                                            >
                                                {count.number}
                                            </Link>
                                        </td>
                                        <td className="px-4 py-3">
                                            {count.locationName}
                                        </td>
                                        <td className="px-4 py-3">
                                            {count.storageLocationName}
                                        </td>
                                        <td className="px-4 py-3 capitalize">
                                            {count.status}
                                        </td>
                                        <td className="px-4 py-3">
                                            {formatDate(count.countedAt)}
                                        </td>
                                        <td className="px-4 py-3">
                                            {formatDate(count.finalizedAt)}
                                        </td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>
                </div>
            </div>
        </>
    );
}

StockCountIndex.layout = {
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: dashboard(),
        },
        {
            title: 'Stock counts',
            href: StockCountController.index(),
        },
    ],
};
