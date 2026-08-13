import { Head, Link } from '@inertiajs/react';
import GoodsReceiptController from '@/actions/App/Http/Controllers/Purchasing/GoodsReceiptController';
import { dashboard } from '@/routes';

type ReceiptRow = {
    id: number;
    number: string;
    status: string;
    purchaseOrderNumber: string;
    supplierName: string;
    locationName: string;
    receivedAt: string | null;
    receivedBy: string | null;
};

type Props = {
    receipts: ReceiptRow[];
};

export default function GoodsReceiptIndex({ receipts }: Props) {
    return (
        <>
            <Head title="Receiving" />

            <div className="flex flex-1 flex-col gap-6 p-4">
                <div>
                    <h1 className="text-2xl font-semibold">Receiving</h1>
                    <p className="mt-1 text-sm text-muted-foreground">
                        Draft and finalized goods receipt history.
                    </p>
                </div>

                <div className="overflow-x-auto rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                    <table className="w-full text-sm">
                        <thead className="border-b bg-muted/40 text-left">
                            <tr>
                                <th className="p-3">Receipt</th>
                                <th className="p-3">PO</th>
                                <th className="p-3">Supplier</th>
                                <th className="p-3">Location</th>
                                <th className="p-3">Status</th>
                                <th className="p-3">Received by</th>
                            </tr>
                        </thead>
                        <tbody>
                            {receipts.map((receipt) => (
                                <tr
                                    key={receipt.id}
                                    className="border-b last:border-b-0"
                                >
                                    <td className="p-3 font-medium">
                                        <Link
                                            href={GoodsReceiptController.edit(
                                                receipt.id,
                                            )}
                                            className="underline-offset-4 hover:underline"
                                        >
                                            {receipt.number}
                                        </Link>
                                    </td>
                                    <td className="p-3">
                                        {receipt.purchaseOrderNumber}
                                    </td>
                                    <td className="p-3">
                                        {receipt.supplierName}
                                    </td>
                                    <td className="p-3">
                                        {receipt.locationName}
                                    </td>
                                    <td className="p-3 capitalize">
                                        {receipt.status}
                                    </td>
                                    <td className="p-3">
                                        {receipt.receivedBy ?? '—'}
                                    </td>
                                </tr>
                            ))}

                            {receipts.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={6}
                                        className="p-8 text-center text-muted-foreground"
                                    >
                                        No goods receipts yet.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
            </div>
        </>
    );
}

GoodsReceiptIndex.layout = {
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: dashboard(),
        },
        {
            title: 'Receiving',
            href: GoodsReceiptController.index(),
        },
    ],
};
