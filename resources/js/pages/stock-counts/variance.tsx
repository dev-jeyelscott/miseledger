import { Form, Head, Link } from '@inertiajs/react';
import StockCountController from '@/actions/App/Http/Controllers/Inventory/StockCountController';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { dashboard } from '@/routes';

type VarianceRow = {
    countId: number;
    countNumber: string;
    countedAt: string | null;
    locationName: string;
    storageLocationName: string;
    itemName: string;
    itemSku: string;
    expectedBaseQuantity: string;
    countedQuantity: string;
    countUnitSymbol: string;
    countedBaseQuantity: string;
    baseUnitSymbol: string;
    varianceBaseQuantity: string;
    varianceUnitCost: string | null;
    varianceTotalCost: string | null;
    movementId: number | null;
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

export default function StockCountVariance({
    rows,
    locationOptions,
    filters,
    currency,
    canViewCosts,
}: Props) {
    return (
        <>
            <Head title="Count variance report" />

            <div className="flex flex-1 flex-col gap-6 p-4">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h1 className="text-2xl font-semibold">
                            Count variance report
                        </h1>
                        <p className="mt-1 text-sm text-muted-foreground">
                            Finalized physical-count variance snapshots.
                        </p>
                    </div>

                    <Button variant="outline" asChild>
                        <Link href={StockCountController.index()}>
                            Stock counts
                        </Link>
                    </Button>
                </div>

                <Form action={StockCountController.variance().url} method="get">
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
                                        href={StockCountController.variance()}
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
                                <th className="px-4 py-3">Count</th>
                                <th className="px-4 py-3">Location</th>
                                <th className="px-4 py-3">Item</th>
                                <th className="px-4 py-3">Expected</th>
                                <th className="px-4 py-3">Counted</th>
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

                                <th className="px-4 py-3">Movement</th>
                            </tr>
                        </thead>

                        <tbody>
                            {rows.length === 0 ? (
                                <tr>
                                    <td
                                        colSpan={canViewCosts ? 9 : 7}
                                        className="px-4 py-8 text-center text-muted-foreground"
                                    >
                                        No finalized count variances match the
                                        selected filters.
                                    </td>
                                </tr>
                            ) : (
                                rows.map((row) => (
                                    <tr
                                        key={`${row.countId}-${row.itemSku}`}
                                        className="border-b last:border-b-0"
                                    >
                                        <td className="px-4 py-3">
                                            <Link
                                                href={StockCountController.edit(
                                                    row.countId,
                                                )}
                                                className="font-medium hover:underline"
                                            >
                                                {row.countNumber}
                                            </Link>

                                            <div className="text-xs text-muted-foreground">
                                                {formatDate(row.countedAt)}
                                            </div>
                                        </td>

                                        <td className="px-4 py-3">
                                            <div>{row.locationName}</div>
                                            <div className="text-xs text-muted-foreground">
                                                {row.storageLocationName}
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
                                            {formatDecimal(
                                                row.expectedBaseQuantity,
                                            )}{' '}
                                            {row.baseUnitSymbol}
                                        </td>

                                        <td className="px-4 py-3">
                                            {formatDecimal(
                                                row.countedBaseQuantity,
                                            )}{' '}
                                            {row.baseUnitSymbol}
                                            <div className="text-xs text-muted-foreground">
                                                Entered:{' '}
                                                {formatDecimal(
                                                    row.countedQuantity,
                                                )}{' '}
                                                {row.countUnitSymbol}
                                            </div>
                                        </td>

                                        <td className="px-4 py-3">
                                            {formatDecimal(
                                                row.varianceBaseQuantity,
                                            )}{' '}
                                            {row.baseUnitSymbol}
                                        </td>

                                        {canViewCosts && (
                                            <>
                                                <td className="px-4 py-3 text-right">
                                                    {row.varianceUnitCost ===
                                                    null
                                                        ? '—'
                                                        : `${currency} ${formatDecimal(
                                                              row.varianceUnitCost,
                                                          )}`}
                                                </td>

                                                <td className="px-4 py-3 text-right">
                                                    {row.varianceTotalCost ===
                                                    null
                                                        ? '—'
                                                        : `${currency} ${formatDecimal(
                                                              row.varianceTotalCost,
                                                          )}`}
                                                </td>
                                            </>
                                        )}

                                        <td className="px-4 py-3">
                                            {row.movementId === null
                                                ? 'No movement'
                                                : `#${row.movementId}`}
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

StockCountVariance.layout = {
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: dashboard(),
        },
        {
            title: 'Stock counts',
            href: StockCountController.index(),
        },
        {
            title: 'Variance report',
            href: StockCountController.variance(),
        },
    ],
};
