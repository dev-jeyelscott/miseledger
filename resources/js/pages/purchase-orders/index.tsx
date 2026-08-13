import { Head, Link } from '@inertiajs/react';
import PurchaseOrderController from '@/actions/App/Http/Controllers/Purchasing/PurchaseOrderController';
import { Button } from '@/components/ui/button';
import { dashboard } from '@/routes';

type PurchaseOrderRow = {
    id: number;
    number: string;
    status: string;
    supplierName: string;
    locationName: string;
    orderDate: string;
    expectedDeliveryDate: string | null;
    total: string;
};

type Props = {
    purchaseOrders: PurchaseOrderRow[];
    currency: string;
    canManage: boolean;
};

export default function PurchaseOrderIndex({
    purchaseOrders,
    currency,
    canManage,
}: Props) {
    return (
        <>
            <Head title="Purchase orders" />

            <div className="flex flex-1 flex-col gap-6 p-4">
                <div className="flex items-center justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-semibold">
                            Purchase orders
                        </h1>
                        <p className="mt-1 text-sm text-muted-foreground">
                            Order stock from configured suppliers.
                        </p>
                    </div>

                    {canManage && (
                        <Button asChild>
                            <Link href={PurchaseOrderController.create()}>
                                Create purchase order
                            </Link>
                        </Button>
                    )}
                </div>

                <div className="overflow-x-auto rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                    <table className="w-full text-sm">
                        <thead className="border-b bg-muted/40 text-left">
                            <tr>
                                <th className="p-3">PO</th>
                                <th className="p-3">Supplier</th>
                                <th className="p-3">Location</th>
                                <th className="p-3">Order date</th>
                                <th className="p-3">Status</th>
                                <th className="p-3 text-right">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            {purchaseOrders.map((purchaseOrder) => (
                                <tr
                                    key={purchaseOrder.id}
                                    className="border-b last:border-b-0"
                                >
                                    <td className="p-3 font-medium">
                                        <Link
                                            className="underline-offset-4 hover:underline"
                                            href={PurchaseOrderController.edit(
                                                purchaseOrder.id,
                                            )}
                                        >
                                            {purchaseOrder.number}
                                        </Link>
                                    </td>
                                    <td className="p-3">
                                        {purchaseOrder.supplierName}
                                    </td>
                                    <td className="p-3">
                                        {purchaseOrder.locationName}
                                    </td>
                                    <td className="p-3">
                                        {purchaseOrder.orderDate}
                                    </td>
                                    <td className="p-3 capitalize">
                                        {purchaseOrder.status.replaceAll(
                                            '_',
                                            ' ',
                                        )}
                                    </td>
                                    <td className="p-3 text-right tabular-nums">
                                        {currency} {purchaseOrder.total}
                                    </td>
                                </tr>
                            ))}

                            {purchaseOrders.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={6}
                                        className="p-8 text-center text-muted-foreground"
                                    >
                                        No purchase orders yet.
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

PurchaseOrderIndex.layout = {
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: dashboard(),
        },
        {
            title: 'Purchase orders',
            href: PurchaseOrderController.index(),
        },
    ],
};
