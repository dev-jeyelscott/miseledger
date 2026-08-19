import { Form, Head, Link } from '@inertiajs/react';
import StockTransferController from '@/actions/App/Http/Controllers/Inventory/StockTransferController';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { navigateToPreviousPage } from '@/lib/navigation-history';
import { dashboard } from '@/routes';

type VarianceRow = {
    transferId: number;
    transferNumber: string;
    receivedAt: string | null;
    fromLocationName: string;
    fromStorageLocationName: string;
    toLocationName: string;
    toStorageLocationName: string;
    itemName: string;
    itemSku: string;
    shippedBaseQuantity: string | null;
    receivedBaseQuantity: string | null;
    varianceBaseQuantity: string | null;
    baseUnitSymbol: string;
    unitCost: string | null;
    varianceValue: string | null;
    outboundMovementId: number | null;
    inboundMovementId: number | null;
};

type LocationOption = {
    id: number;
    name: string;
};

type Props = {
    rows: VarianceRow[];
    locationOptions: LocationOption[];
    filters: {
        locationId: number | null;
        from: string | null;
        to: string | null;
    };
    currency: string;
    canViewCosts: boolean;
};

const formatDecimal = (value: string): string => {
    const [rawInteger, rawDecimal = ''] = value.trim().split('.');
    const negative = rawInteger.startsWith('-');
    const integerDigits = negative ? rawInteger.slice(1) : rawInteger;
    const groupedInteger = integerDigits.replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    const decimal = rawDecimal.replace(/0+$/, '');

    return `${negative ? '-' : ''}${groupedInteger}${
        decimal === '' ? '' : `.${decimal}`
    }`;
};

const formatDate = (value: string | null): string =>
    value === null ? '—' : new Date(value).toLocaleString();

export default function StockTransferVariance({
    rows,
    locationOptions,
    filters,
    currency,
    canViewCosts,
}: Props) {
    return (
        <>
            <Head title="Transfer variance report" />

            <div className="flex flex-1 flex-col gap-6 p-4">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h1 className="text-2xl font-semibold">
                            Transfer variance report
                        </h1>
                        <p className="mt-1 text-sm text-muted-foreground">
                            Compare shipped and actually received stock.
                        </p>
                    </div>

                    <Button
                        type="button"
                        variant="outline"
                        onClick={() =>
                            navigateToPreviousPage(
                                StockTransferController.index().url,
                            )
                        }
                    >
                        Back
                    </Button>
                </div>

                <Form
                    action={StockTransferController.variance().url}
                    method="get"
                    options={{ replace: true }}
                >
                    {({ processing }) => (
                        <div className="grid gap-4 rounded-xl border border-sidebar-border/70 p-5 md:grid-cols-[2fr_1fr_1fr_auto_auto] dark:border-sidebar-border">
                            <div className="grid gap-2">
                                <Label>Location</Label>
                                <select
                                    name="location_id"
                                    defaultValue={
                                        filters.locationId?.toString() ?? ''
                                    }
                                    className="h-9 rounded-md border bg-background px-3 text-sm"
                                >
                                    <option value="">All locations</option>

                                    {locationOptions.map((location) => (
                                        <option
                                            key={location.id}
                                            value={location.id}
                                        >
                                            {location.name}
                                        </option>
                                    ))}
                                </select>
                            </div>

                            <div className="grid gap-2">
                                <Label>From</Label>
                                <Input
                                    name="from"
                                    type="date"
                                    defaultValue={filters.from ?? ''}
                                />
                            </div>

                            <div className="grid gap-2">
                                <Label>To</Label>
                                <Input
                                    name="to"
                                    type="date"
                                    defaultValue={filters.to ?? ''}
                                />
                            </div>

                            <div className="flex items-end">
                                <Button type="submit" disabled={processing}>
                                    Apply
                                </Button>
                            </div>

                            <div className="flex items-end">
                                <Button variant="outline" asChild>
                                    <Link
                                        href={StockTransferController.variance()}
                                        replace
                                    >
                                        Clear
                                    </Link>
                                </Button>
                            </div>
                        </div>
                    )}
                </Form>

                <div className="overflow-x-auto rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                    <table className="w-full text-sm">
                        <thead className="border-b text-left">
                            <tr>
                                <th className="px-4 py-3">Transfer</th>
                                <th className="px-4 py-3">Source</th>
                                <th className="px-4 py-3">Destination</th>
                                <th className="px-4 py-3">Item</th>
                                <th className="px-4 py-3">Shipped</th>
                                <th className="px-4 py-3">Received</th>
                                <th className="px-4 py-3">Variance</th>

                                {canViewCosts && (
                                    <>
                                        <th className="px-4 py-3 text-right">
                                            Unit cost
                                        </th>
                                        <th className="px-4 py-3 text-right">
                                            Variance value
                                        </th>
                                    </>
                                )}

                                <th className="px-4 py-3">Movements</th>
                            </tr>
                        </thead>

                        <tbody>
                            {rows.length === 0 ? (
                                <tr>
                                    <td
                                        colSpan={canViewCosts ? 10 : 8}
                                        className="px-4 py-8 text-center text-muted-foreground"
                                    >
                                        No received transfer variances match the
                                        selected filters.
                                    </td>
                                </tr>
                            ) : (
                                rows.map((row) => (
                                    <tr
                                        key={`${row.transferId}-${row.itemSku}`}
                                        className="border-b last:border-b-0"
                                    >
                                        <td className="px-4 py-3">
                                            <Link
                                                href={StockTransferController.edit(
                                                    row.transferId,
                                                )}
                                                className="font-medium hover:underline"
                                            >
                                                {row.transferNumber}
                                            </Link>

                                            <div className="text-xs text-muted-foreground">
                                                {formatDate(row.receivedAt)}
                                            </div>
                                        </td>

                                        <td className="px-4 py-3">
                                            <div>{row.fromLocationName}</div>
                                            <div className="text-xs text-muted-foreground">
                                                {row.fromStorageLocationName}
                                            </div>
                                        </td>

                                        <td className="px-4 py-3">
                                            <div>{row.toLocationName}</div>
                                            <div className="text-xs text-muted-foreground">
                                                {row.toStorageLocationName}
                                            </div>
                                        </td>

                                        <td className="px-4 py-3">
                                            <div className="font-medium">
                                                {row.itemName}
                                            </div>
                                            <div className="text-xs text-muted-foreground">
                                                {row.itemSku}
                                            </div>
                                        </td>

                                        <td className="px-4 py-3">
                                            {row.shippedBaseQuantity === null
                                                ? '—'
                                                : `${formatDecimal(
                                                      row.shippedBaseQuantity,
                                                  )} ${row.baseUnitSymbol}`}
                                        </td>

                                        <td className="px-4 py-3">
                                            {row.receivedBaseQuantity === null
                                                ? '—'
                                                : `${formatDecimal(
                                                      row.receivedBaseQuantity,
                                                  )} ${row.baseUnitSymbol}`}
                                        </td>

                                        <td className="px-4 py-3">
                                            {row.varianceBaseQuantity === null
                                                ? '—'
                                                : `${formatDecimal(
                                                      row.varianceBaseQuantity,
                                                  )} ${row.baseUnitSymbol}`}
                                        </td>

                                        {canViewCosts && (
                                            <>
                                                <td className="px-4 py-3 text-right">
                                                    {row.unitCost === null
                                                        ? '—'
                                                        : `${currency} ${formatDecimal(
                                                              row.unitCost,
                                                          )}`}
                                                </td>

                                                <td className="px-4 py-3 text-right">
                                                    {row.varianceValue === null
                                                        ? '—'
                                                        : `${currency} ${formatDecimal(
                                                              row.varianceValue,
                                                          )}`}
                                                </td>
                                            </>
                                        )}

                                        <td className="px-4 py-3">
                                            <div>
                                                OUT:{' '}
                                                {row.outboundMovementId === null
                                                    ? '—'
                                                    : `#${row.outboundMovementId}`}
                                            </div>
                                            <div>
                                                IN:{' '}
                                                {row.inboundMovementId === null
                                                    ? '—'
                                                    : `#${row.inboundMovementId}`}
                                            </div>
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

StockTransferVariance.layout = {
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: dashboard(),
        },
        {
            title: 'Stock transfers',
            href: StockTransferController.index(),
        },
        {
            title: 'Variance report',
            href: StockTransferController.variance(),
        },
    ],
};
