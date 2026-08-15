import { Head, Link } from '@inertiajs/react';
import StockTransferController from '@/actions/App/Http/Controllers/Inventory/StockTransferController';
import { Button } from '@/components/ui/button';
import { dashboard } from '@/routes';

type TransferSummary = {
    id: number;
    number: string;
    status: string;
    fromLocationName: string;
    fromStorageLocationName: string;
    toLocationName: string;
    toStorageLocationName: string;
    requestedAt: string | null;
    shippedAt: string | null;
    receivedAt: string | null;
};

type Props = {
    transfers: TransferSummary[];
    canCreate: boolean;
    canViewReport: boolean;
};

const formatDate = (value: string | null): string =>
    value === null ? '—' : new Date(value).toLocaleString();

export default function StockTransferIndex({
    transfers,
    canCreate,
    canViewReport,
}: Props) {
    return (
        <>
            <Head title="Stock transfers" />

            <div className="flex flex-1 flex-col gap-6 p-4">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h1 className="text-2xl font-semibold">
                            Stock transfers
                        </h1>
                        <p className="mt-1 text-sm text-muted-foreground">
                            Move inventory safely between storage locations.
                        </p>
                    </div>

                    <div className="flex gap-2">
                        {canViewReport && (
                            <Button variant="outline" asChild>
                                <Link href={StockTransferController.variance()}>
                                    Variance report
                                </Link>
                            </Button>
                        )}

                        {canCreate && (
                            <Button asChild>
                                <Link href={StockTransferController.create()}>
                                    New transfer
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
                                <th className="px-4 py-3">Source</th>
                                <th className="px-4 py-3">Destination</th>
                                <th className="px-4 py-3">Status</th>
                                <th className="px-4 py-3">Requested</th>
                                <th className="px-4 py-3">Shipped</th>
                                <th className="px-4 py-3">Received</th>
                            </tr>
                        </thead>

                        <tbody>
                            {transfers.length === 0 ? (
                                <tr>
                                    <td
                                        colSpan={7}
                                        className="px-4 py-8 text-center text-muted-foreground"
                                    >
                                        No stock transfers yet.
                                    </td>
                                </tr>
                            ) : (
                                transfers.map((transfer) => (
                                    <tr
                                        key={transfer.id}
                                        className="border-b last:border-b-0"
                                    >
                                        <td className="px-4 py-3 font-medium">
                                            <Link
                                                href={StockTransferController.edit(
                                                    transfer.id,
                                                )}
                                                className="hover:underline"
                                            >
                                                {transfer.number}
                                            </Link>
                                        </td>

                                        <td className="px-4 py-3">
                                            <div>
                                                {transfer.fromLocationName}
                                            </div>
                                            <div className="text-xs text-muted-foreground">
                                                {
                                                    transfer.fromStorageLocationName
                                                }
                                            </div>
                                        </td>

                                        <td className="px-4 py-3">
                                            <div>{transfer.toLocationName}</div>
                                            <div className="text-xs text-muted-foreground">
                                                {transfer.toStorageLocationName}
                                            </div>
                                        </td>

                                        <td className="px-4 py-3 capitalize">
                                            {transfer.status}
                                        </td>

                                        <td className="px-4 py-3">
                                            {formatDate(transfer.requestedAt)}
                                        </td>

                                        <td className="px-4 py-3">
                                            {formatDate(transfer.shippedAt)}
                                        </td>

                                        <td className="px-4 py-3">
                                            {formatDate(transfer.receivedAt)}
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

StockTransferIndex.layout = {
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: dashboard(),
        },
        {
            title: 'Stock transfers',
            href: StockTransferController.index(),
        },
    ],
};
