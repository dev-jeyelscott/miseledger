import { Form, Head, Link } from '@inertiajs/react';
import {
    ArrowRight,
    CheckCircle2,
    CircleAlert,
    CircleMinus,
    Filter,
    PackageCheck,
    RotateCcw,
    Scale,
    TrendingDown,
    TrendingUp,
} from 'lucide-react';

import StockTransferController from '@/actions/App/Http/Controllers/Inventory/StockTransferController';
import { DashboardMetricCard } from '@/components/dashboard/dashboard-metric-card';
import { EmptyState } from '@/components/empty-state';
import { FilterToolbar } from '@/components/filter-toolbar';
import { PageHeader } from '@/components/page-header';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { Field } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';
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
    timezone: string;
    canViewCosts: boolean;
};

type VarianceKind = 'shortage' | 'overage' | 'exact' | 'unavailable';

/** Format an authoritative decimal string without converting it through JavaScript floating point. */
function formatDecimal(value: string): string {
    const [rawInteger, rawDecimal = ''] = value.trim().split('.');
    const negative = rawInteger.startsWith('-');
    const integerDigits = negative ? rawInteger.slice(1) : rawInteger;
    const groupedInteger = integerDigits.replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    const decimal = rawDecimal.replace(/0+$/, '');

    return `${negative ? '-' : ''}${groupedInteger}${
        decimal === '' ? '' : `.${decimal}`
    }`;
}

/** Format one workflow timestamp in the active organization's timezone. */
function formatOrganizationDate(
    value: string | null,
    timezone: string,
): string {
    if (value === null) {
        return 'Not available';
    }

    return new Intl.DateTimeFormat('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
        timeZone: timezone,
        timeZoneName: 'short',
    }).format(new Date(value));
}

/** Compare a decimal string with zero without losing fixed-precision accuracy. */
function classifyVariance(value: string | null): VarianceKind {
    if (value === null) {
        return 'unavailable';
    }

    const normalized = value.trim();

    if (normalized.startsWith('-') && /[1-9]/.test(normalized)) {
        return 'shortage';
    }

    if (!normalized.startsWith('-') && /[1-9]/.test(normalized)) {
        return 'overage';
    }

    return 'exact';
}

/** Render a fixed-precision quantity together with its base unit. */
function formatQuantity(value: string | null, baseUnitSymbol: string): string {
    return value === null
        ? 'Not available'
        : `${formatDecimal(value)} ${baseUnitSymbol}`;
}

/** Render one discrepancy with semantic text, icon, and shared status styling. */
function VarianceDisplay({
    value,
    baseUnitSymbol,
}: {
    value: string | null;
    baseUnitSymbol: string;
}) {
    const kind = classifyVariance(value);

    if (kind === 'shortage') {
        return (
            <div className="flex flex-col items-start gap-1">
                <span className="font-semibold text-destructive tabular-nums">
                    {formatQuantity(value, baseUnitSymbol)}
                </span>
                <StatusBadge
                    label="Shortage"
                    variant="danger"
                    aria-label="Shortage variance"
                />
            </div>
        );
    }

    if (kind === 'overage') {
        return (
            <div className="flex flex-col items-start gap-1">
                <span className="font-semibold text-info-foreground tabular-nums">
                    +{formatQuantity(value, baseUnitSymbol)}
                </span>
                <StatusBadge
                    label="Overage"
                    variant="info"
                    aria-label="Overage variance"
                />
            </div>
        );
    }

    if (kind === 'exact') {
        return (
            <div className="flex flex-col items-start gap-1">
                <span className="font-semibold tabular-nums">
                    {formatQuantity(value, baseUnitSymbol)}
                </span>
                <StatusBadge
                    label="Exact match"
                    variant="success"
                    aria-label="Exact match variance"
                />
            </div>
        );
    }

    return (
        <div className="flex flex-col items-start gap-1">
            <span className="text-muted-foreground">Not available</span>
            <StatusBadge label="Unavailable" variant="neutral" />
        </div>
    );
}

/** Render source and destination as one directional transfer path. */
function TransferRoute({ row }: { row: VarianceRow }) {
    return (
        <div className="flex min-w-0 items-center gap-2">
            <div className="min-w-0">
                <div className="font-medium">{row.fromLocationName}</div>
                <div className="truncate text-xs text-muted-foreground">
                    {row.fromStorageLocationName}
                </div>
            </div>

            <ArrowRight
                className="size-4 shrink-0 text-muted-foreground"
                aria-hidden="true"
            />

            <div className="min-w-0">
                <div className="font-medium">{row.toLocationName}</div>
                <div className="truncate text-xs text-muted-foreground">
                    {row.toStorageLocationName}
                </div>
            </div>
        </div>
    );
}

/** Render immutable stock-ledger references produced by transfer shipment and receipt. */
function MovementReferences({ row }: { row: VarianceRow }) {
    return (
        <div className="space-y-1 text-xs">
            <div>
                <span className="text-muted-foreground">
                    Transfer out movement
                </span>{' '}
                <span className="font-mono">
                    {row.outboundMovementId === null
                        ? 'Not available'
                        : `#${row.outboundMovementId}`}
                </span>
            </div>

            <div>
                <span className="text-muted-foreground">
                    Transfer in movement
                </span>{' '}
                <span className="font-mono">
                    {row.inboundMovementId === null
                        ? 'Not available'
                        : `#${row.inboundMovementId}`}
                </span>
            </div>
        </div>
    );
}

/** Render the server-authoritative Stock Transfer discrepancy analysis report. */
export default function StockTransferVariance({
    rows,
    locationOptions,
    filters,
    currency,
    timezone,
    canViewCosts,
}: Props) {
    const hasFilters =
        filters.locationId !== null ||
        filters.from !== null ||
        filters.to !== null;

    const shortageCount = rows.filter(
        (row) => classifyVariance(row.varianceBaseQuantity) === 'shortage',
    ).length;
    const overageCount = rows.filter(
        (row) => classifyVariance(row.varianceBaseQuantity) === 'overage',
    ).length;
    const exactMatchCount = rows.filter(
        (row) => classifyVariance(row.varianceBaseQuantity) === 'exact',
    ).length;

    const selectedLocation =
        filters.locationId === null
            ? null
            : locationOptions.find(
                  (location) => location.id === filters.locationId,
              );

    const activeFilters = [
        selectedLocation ? `Location: ${selectedLocation.name}` : null,
        filters.from ? `From: ${filters.from}` : null,
        filters.to ? `To: ${filters.to}` : null,
    ].filter((filter): filter is string => filter !== null);

    return (
        <>
            <Head title="Transfer discrepancy analysis" />

            <div className="flex flex-1 flex-col gap-6 p-4">
                <PageHeader
                    title="Transfer discrepancy analysis"
                    description="Reconcile quantities shipped from the source against quantities received at the destination."
                    actions={
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
                    }
                />

                <Form
                    action={StockTransferController.variance().url}
                    method="get"
                    options={{ replace: true }}
                >
                    {({ errors, processing }) => (
                        <FilterToolbar>
                            <div className="grid gap-4 md:grid-cols-3">
                                <Field
                                    id="location_id"
                                    label="Location"
                                    error={errors.location_id}
                                    helper="Match transfers where this location is either the source or destination."
                                >
                                    <NativeSelect
                                        name="location_id"
                                        defaultValue={
                                            filters.locationId?.toString() ?? ''
                                        }
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
                                    </NativeSelect>
                                </Field>

                                <Field
                                    id="from"
                                    label="From"
                                    error={errors.from}
                                >
                                    <Input
                                        name="from"
                                        type="date"
                                        defaultValue={filters.from ?? ''}
                                    />
                                </Field>

                                <Field id="to" label="To" error={errors.to}>
                                    <Input
                                        name="to"
                                        type="date"
                                        defaultValue={filters.to ?? ''}
                                    />
                                </Field>
                            </div>

                            <div className="mt-4 flex flex-wrap items-center justify-between gap-3 border-t border-border pt-4">
                                <div className="flex min-w-0 flex-wrap items-center gap-2">
                                    <span className="text-xs font-medium text-muted-foreground">
                                        Active filters
                                    </span>

                                    {activeFilters.length === 0 ? (
                                        <span className="text-xs text-muted-foreground">
                                            None
                                        </span>
                                    ) : (
                                        activeFilters.map((filter) => (
                                            <StatusBadge
                                                key={filter}
                                                label={filter}
                                                variant="neutral"
                                            />
                                        ))
                                    )}
                                </div>

                                <div className="flex w-full gap-2 sm:w-auto">
                                    <Button
                                        type="submit"
                                        disabled={processing}
                                        className="flex-1 sm:flex-none"
                                    >
                                        <Filter
                                            className="size-4"
                                            aria-hidden="true"
                                        />
                                        {processing
                                            ? 'Applying…'
                                            : 'Apply filters'}
                                    </Button>

                                    <Button
                                        variant="outline"
                                        className="flex-1 sm:flex-none"
                                        asChild
                                    >
                                        <Link
                                            href={StockTransferController.variance()}
                                            replace
                                        >
                                            <RotateCcw
                                                className="size-4"
                                                aria-hidden="true"
                                            />
                                            Clear filters
                                        </Link>
                                    </Button>
                                </div>
                            </div>

                            {Object.keys(errors).length > 0 ? (
                                <div
                                    role="alert"
                                    className="mt-4 rounded-md border border-destructive/30 bg-destructive/10 px-3 py-2 text-sm text-destructive"
                                >
                                    One or more report filters are invalid.
                                    Review the described fields or clear the
                                    filters and try again.
                                </div>
                            ) : null}
                        </FilterToolbar>
                    )}
                </Form>

                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <DashboardMetricCard
                        title="Analyzed lines"
                        value={rows.length.toLocaleString()}
                        icon={Scale}
                    />
                    <DashboardMetricCard
                        title="Shortages"
                        value={shortageCount.toLocaleString()}
                        icon={TrendingDown}
                    />
                    <DashboardMetricCard
                        title="Overages"
                        value={overageCount.toLocaleString()}
                        icon={TrendingUp}
                    />
                    <DashboardMetricCard
                        title="Exact matches"
                        value={exactMatchCount.toLocaleString()}
                        icon={CheckCircle2}
                    />
                </div>

                <section
                    aria-labelledby="reconciliation-heading"
                    className="rounded-xl border border-border bg-card text-card-foreground"
                >
                    <div className="border-b border-border px-4 py-4">
                        <h2
                            id="reconciliation-heading"
                            className="font-semibold"
                        >
                            Transfer reconciliation
                        </h2>
                        <p className="mt-1 text-sm text-muted-foreground">
                            Received transfers and their immutable shipment,
                            receipt, and stock movement evidence.
                        </p>
                    </div>

                    {rows.length === 0 ? (
                        <EmptyState
                            className="px-6 py-12"
                            icon={hasFilters ? CircleMinus : PackageCheck}
                            title={
                                hasFilters
                                    ? 'No transfers match these filters'
                                    : 'No received transfers to analyze'
                            }
                            description={
                                hasFilters
                                    ? 'No received transfer lines match the selected location and date range.'
                                    : 'Transfer discrepancy evidence will appear after stock transfers have been received.'
                            }
                            action={
                                hasFilters ? (
                                    <Button variant="outline" asChild>
                                        <Link
                                            href={StockTransferController.variance()}
                                            replace
                                        >
                                            <RotateCcw
                                                className="size-4"
                                                aria-hidden="true"
                                            />
                                            Clear filters
                                        </Link>
                                    </Button>
                                ) : undefined
                            }
                        />
                    ) : (
                        <>
                            <div
                                className="grid gap-3 p-4 md:hidden"
                                data-testid="mobile-transfer-variance"
                            >
                                {rows.map((row) => (
                                    <article
                                        key={`${row.transferId}-${row.itemSku}`}
                                        className="rounded-xl border border-border bg-background p-4"
                                    >
                                        <div className="flex items-start justify-between gap-3">
                                            <div>
                                                <Link
                                                    href={StockTransferController.edit(
                                                        row.transferId,
                                                    )}
                                                    className="font-semibold hover:underline focus-visible:rounded-sm focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
                                                >
                                                    {row.transferNumber}
                                                </Link>
                                                <div className="mt-1 text-xs text-muted-foreground">
                                                    Received{' '}
                                                    {formatOrganizationDate(
                                                        row.receivedAt,
                                                        timezone,
                                                    )}
                                                </div>
                                            </div>

                                            <VarianceDisplay
                                                value={row.varianceBaseQuantity}
                                                baseUnitSymbol={
                                                    row.baseUnitSymbol
                                                }
                                            />
                                        </div>

                                        <div className="mt-4 border-t border-border pt-4">
                                            <div className="mb-2 text-xs font-medium text-muted-foreground">
                                                Source to destination
                                            </div>
                                            <TransferRoute row={row} />
                                        </div>

                                        <dl className="mt-4 grid grid-cols-2 gap-3 border-t border-border pt-4 text-sm">
                                            <div>
                                                <dt className="text-xs text-muted-foreground">
                                                    Item
                                                </dt>
                                                <dd className="mt-1 font-medium">
                                                    {row.itemName}
                                                </dd>
                                                <dd className="font-mono text-xs text-muted-foreground">
                                                    {row.itemSku}
                                                </dd>
                                            </div>

                                            <div>
                                                <dt className="text-xs text-muted-foreground">
                                                    Shipped
                                                </dt>
                                                <dd className="mt-1 tabular-nums">
                                                    {formatQuantity(
                                                        row.shippedBaseQuantity,
                                                        row.baseUnitSymbol,
                                                    )}
                                                </dd>
                                            </div>

                                            <div>
                                                <dt className="text-xs text-muted-foreground">
                                                    Received
                                                </dt>
                                                <dd className="mt-1 tabular-nums">
                                                    {formatQuantity(
                                                        row.receivedBaseQuantity,
                                                        row.baseUnitSymbol,
                                                    )}
                                                </dd>
                                            </div>

                                            {canViewCosts ? (
                                                <div>
                                                    <dt className="text-xs text-muted-foreground">
                                                        Variance value
                                                    </dt>
                                                    <dd className="mt-1 tabular-nums">
                                                        {row.varianceValue ===
                                                        null
                                                            ? 'Not available'
                                                            : `${currency} ${formatDecimal(
                                                                  row.varianceValue,
                                                              )}`}
                                                    </dd>
                                                </div>
                                            ) : null}
                                        </dl>

                                        {canViewCosts ? (
                                            <div className="mt-3 text-xs">
                                                <span className="text-muted-foreground">
                                                    Unit cost
                                                </span>{' '}
                                                <span className="tabular-nums">
                                                    {row.unitCost === null
                                                        ? 'Not available'
                                                        : `${currency} ${formatDecimal(
                                                              row.unitCost,
                                                          )}`}
                                                </span>
                                            </div>
                                        ) : null}

                                        <div className="mt-4 border-t border-border pt-4">
                                            <div className="mb-2 flex items-center gap-2 text-xs font-medium text-muted-foreground">
                                                <CircleAlert
                                                    className="size-4"
                                                    aria-hidden="true"
                                                />
                                                Stock movement references
                                            </div>
                                            <MovementReferences row={row} />
                                        </div>
                                    </article>
                                ))}
                            </div>

                            <div
                                className="hidden overflow-x-auto md:block"
                                data-testid="desktop-transfer-variance"
                            >
                                <table className="w-full min-w-[72rem] text-sm">
                                    <thead className="border-b border-border bg-muted/50 text-left text-xs text-muted-foreground">
                                        <tr>
                                            <th
                                                scope="col"
                                                className="px-4 py-3 font-medium"
                                            >
                                                Transfer
                                            </th>
                                            <th
                                                scope="col"
                                                className="px-4 py-3 font-medium"
                                            >
                                                Source → Destination
                                            </th>
                                            <th
                                                scope="col"
                                                className="px-4 py-3 font-medium"
                                            >
                                                Item
                                            </th>
                                            <th
                                                scope="col"
                                                className="px-4 py-3 text-right font-medium"
                                            >
                                                Shipped
                                            </th>
                                            <th
                                                scope="col"
                                                className="px-4 py-3 text-right font-medium"
                                            >
                                                Received
                                            </th>
                                            <th
                                                scope="col"
                                                className="px-4 py-3 font-medium"
                                            >
                                                Variance
                                            </th>

                                            {canViewCosts ? (
                                                <>
                                                    <th
                                                        scope="col"
                                                        className="px-4 py-3 text-right font-medium"
                                                    >
                                                        Unit cost
                                                    </th>
                                                    <th
                                                        scope="col"
                                                        className="px-4 py-3 text-right font-medium"
                                                    >
                                                        Variance value
                                                    </th>
                                                </>
                                            ) : null}

                                            <th
                                                scope="col"
                                                className="px-4 py-3 font-medium"
                                            >
                                                Movement references
                                            </th>
                                        </tr>
                                    </thead>

                                    <tbody>
                                        {rows.map((row) => (
                                            <tr
                                                key={`${row.transferId}-${row.itemSku}`}
                                                className="border-b border-border last:border-b-0 hover:bg-muted/30"
                                            >
                                                <td className="px-4 py-3 align-top">
                                                    <Link
                                                        href={StockTransferController.edit(
                                                            row.transferId,
                                                        )}
                                                        className="font-medium hover:underline focus-visible:rounded-sm focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
                                                    >
                                                        {row.transferNumber}
                                                    </Link>
                                                    <div className="mt-1 text-xs whitespace-nowrap text-muted-foreground">
                                                        {formatOrganizationDate(
                                                            row.receivedAt,
                                                            timezone,
                                                        )}
                                                    </div>
                                                </td>

                                                <td className="px-4 py-3 align-top">
                                                    <TransferRoute row={row} />
                                                </td>

                                                <td className="px-4 py-3 align-top">
                                                    <div className="font-medium">
                                                        {row.itemName}
                                                    </div>
                                                    <div className="font-mono text-xs text-muted-foreground">
                                                        {row.itemSku}
                                                    </div>
                                                </td>

                                                <td className="px-4 py-3 text-right align-top tabular-nums">
                                                    {formatQuantity(
                                                        row.shippedBaseQuantity,
                                                        row.baseUnitSymbol,
                                                    )}
                                                </td>

                                                <td className="px-4 py-3 text-right align-top tabular-nums">
                                                    {formatQuantity(
                                                        row.receivedBaseQuantity,
                                                        row.baseUnitSymbol,
                                                    )}
                                                </td>

                                                <td className="px-4 py-3 align-top">
                                                    <VarianceDisplay
                                                        value={
                                                            row.varianceBaseQuantity
                                                        }
                                                        baseUnitSymbol={
                                                            row.baseUnitSymbol
                                                        }
                                                    />
                                                </td>

                                                {canViewCosts ? (
                                                    <>
                                                        <td className="px-4 py-3 text-right align-top tabular-nums">
                                                            {row.unitCost ===
                                                            null
                                                                ? 'Not available'
                                                                : `${currency} ${formatDecimal(
                                                                      row.unitCost,
                                                                  )}`}
                                                        </td>

                                                        <td className="px-4 py-3 text-right align-top tabular-nums">
                                                            {row.varianceValue ===
                                                            null
                                                                ? 'Not available'
                                                                : `${currency} ${formatDecimal(
                                                                      row.varianceValue,
                                                                  )}`}
                                                        </td>
                                                    </>
                                                ) : null}

                                                <td className="px-4 py-3 align-top">
                                                    <MovementReferences
                                                        row={row}
                                                    />
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </>
                    )}
                </section>
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
