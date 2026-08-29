import { Form, Head, Link, usePage } from '@inertiajs/react';
import {
    ArrowDown,
    ArrowUp,
    CheckCircle2,
    ClipboardList,
    Download,
    Filter,
    RotateCcw,
} from 'lucide-react';

import StockCountController from '@/actions/App/Http/Controllers/Inventory/StockCountController';
import { DashboardMetricCard } from '@/components/dashboard/dashboard-metric-card';
import { EmptyState } from '@/components/empty-state';
import { FilterToolbar } from '@/components/filter-toolbar';
import { PageHeader } from '@/components/page-header';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { Field } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';
import { dashboard } from '@/routes';
import type { OrganizationContext } from '@/types';

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
    timezone: string;
    canViewCosts: boolean;
};

type VarianceSign = 'negative' | 'positive' | 'zero';

/** Format persisted decimal strings without converting quantities or costs to floats. */
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

/** Format one persisted currency amount without floating-point conversion. */
function formatCurrency(value: string, currency: string): string {
    return `${currency} ${formatDecimal(value)}`;
}

/** Render one operational timestamp in the active organization's timezone. */
function formatOrganizationDate(value: string, timezone: string): string {
    return new Intl.DateTimeFormat('en-US', {
        month: 'numeric',
        day: 'numeric',
        year: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
        timeZone: timezone,
    }).format(new Date(value));
}

/** Classify a persisted decimal variance without JavaScript numeric conversion. */
function varianceSign(value: string): VarianceSign {
    const trimmed = value.trim();
    const negative = trimmed.startsWith('-');
    const unsigned = negative ? trimmed.slice(1) : trimmed;
    const digits = unsigned.replace('.', '');

    if (digits !== '' && /^0+$/.test(digits)) {
        return 'zero';
    }

    return negative ? 'negative' : 'positive';
}

/** Return a visible non-color label for one variance direction. */
function varianceLabel(sign: VarianceSign): string {
    switch (sign) {
        case 'negative':
            return 'Negative variance';
        case 'positive':
            return 'Positive variance';
        case 'zero':
            return 'Zero variance';
    }
}

/** Map variance direction to the shared semantic status treatment. */
function varianceVariant(
    sign: VarianceSign,
): 'info' | 'success' | 'warning' {
    switch (sign) {
        case 'negative':
            return 'warning';
        case 'positive':
            return 'info';
        case 'zero':
            return 'success';
    }
}

/** Keep the authorized CSV export synchronized with every active report filter. */
function buildExportUrl(filters: Props['filters']): string {
    const params = new URLSearchParams();

    if (filters.locationId !== null) {
        params.set('location_id', filters.locationId.toString());
    }

    if (filters.from !== null) {
        params.set('from', filters.from);
    }

    if (filters.to !== null) {
        params.set('to', filters.to);
    }

    const baseUrl = StockCountController.exportVariance().url;
    const query = params.toString();

    return query === '' ? baseUrl : `${baseUrl}?${query}`;
}

/** Summarize filtered report lines by persisted variance direction. */
function summarizeVarianceRows(rows: VarianceRow[]) {
    return rows.reduce(
        (summary, row) => {
            const sign = varianceSign(row.varianceBaseQuantity);

            summary.total += 1;
            summary[sign] += 1;

            return summary;
        },
        {
            total: 0,
            negative: 0,
            positive: 0,
            zero: 0,
        },
    );
}

/** Render variance meaning with both an icon and shared semantic text badge. */
function VarianceIndicator({ value }: { value: string }) {
    const sign = varianceSign(value);
    const Icon =
        sign === 'negative'
            ? ArrowDown
            : sign === 'positive'
              ? ArrowUp
              : CheckCircle2;

    return (
        <span className="inline-flex items-center gap-1.5">
            <Icon className="size-3.5 text-muted-foreground" aria-hidden="true" />
            <StatusBadge
                label={varianceLabel(sign)}
                variant={varianceVariant(sign)}
            />
        </span>
    );
}

/** Render finalized physical-count evidence as a focused variance-analysis report. */
export default function StockCountVariance({
    rows,
    locationOptions,
    filters,
    currency,
    timezone,
    canViewCosts,
}: Props) {
    const { organizationContext } = usePage<{
        organizationContext: OrganizationContext;
    }>().props;
    const canExportReports =
        organizationContext.entitlements?.grants['reports.export'] ?? false;
    const summary = summarizeVarianceRows(rows);
    const exportUrl = buildExportUrl(filters);
    const hasFilters =
        filters.locationId !== null ||
        filters.from !== null ||
        filters.to !== null;

    return (
        <>
            <Head title="Stock count variance" />

            <div className="flex flex-1 flex-col gap-6 p-4 sm:p-6">
                <PageHeader
                    title="Stock count variance"
                    description="Analyze finalized physical-count differences against expected stock without changing the immutable count evidence."
                    actions={
                        <>
                            <Button variant="outline" asChild>
                                <Link href={StockCountController.index()}>
                                    <ClipboardList
                                        className="size-4"
                                        aria-hidden="true"
                                    />
                                    Stock counts
                                </Link>
                            </Button>

                            {canExportReports && (
                                <Button variant="outline" asChild>
                                    <a href={exportUrl}>
                                        <Download
                                            className="size-4"
                                            aria-hidden="true"
                                        />
                                        Export CSV
                                    </a>
                                </Button>
                            )}
                        </>
                    }
                />

                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <DashboardMetricCard
                        title="Report lines"
                        value={summary.total.toLocaleString()}
                        description="Finalized count lines in the current filters"
                        icon={ClipboardList}
                        tone="blue"
                    />

                    <DashboardMetricCard
                        title="Negative variance"
                        value={summary.negative.toLocaleString()}
                        description="Count lines below expected quantity"
                        icon={ArrowDown}
                        tone="amber"
                    />

                    <DashboardMetricCard
                        title="Positive variance"
                        value={summary.positive.toLocaleString()}
                        description="Count lines above expected quantity"
                        icon={ArrowUp}
                        tone="teal"
                    />

                    <DashboardMetricCard
                        title="Zero variance"
                        value={summary.zero.toLocaleString()}
                        description="Count lines matching expected quantity"
                        icon={CheckCircle2}
                        tone="emerald"
                    />
                </div>

                <Form action={StockCountController.variance().url} method="get">
                    {({ errors, processing }) => (
                        <FilterToolbar>
                            <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-[1.35fr_1fr_1fr_auto]">
                                <Field
                                    id="stock-count-variance-location"
                                    label="Location"
                                    error={errors.location_id}
                                >
                                    <NativeSelect
                                        name="location_id"
                                        defaultValue={
                                            filters.locationId?.toString() ?? ''
                                        }
                                        className="motion-reduce:transition-none"
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
                                    id="stock-count-variance-from"
                                    label="Counted from"
                                    error={errors.from}
                                >
                                    <Input
                                        name="from"
                                        type="date"
                                        defaultValue={filters.from ?? ''}
                                    />
                                </Field>

                                <Field
                                    id="stock-count-variance-to"
                                    label="Counted to"
                                    error={errors.to}
                                >
                                    <Input
                                        name="to"
                                        type="date"
                                        defaultValue={filters.to ?? ''}
                                    />
                                </Field>

                                <div className="flex items-end gap-2 md:col-span-2 xl:col-span-1">
                                    <Button
                                        type="submit"
                                        disabled={processing}
                                        className="min-w-28 flex-1 xl:flex-none"
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
                                        type="button"
                                        variant="outline"
                                        className="flex-1 xl:flex-none"
                                        asChild
                                    >
                                        <Link
                                            href={StockCountController.variance()}
                                        >
                                            <RotateCcw
                                                className="size-4"
                                                aria-hidden="true"
                                            />
                                            Reset
                                        </Link>
                                    </Button>
                                </div>
                            </div>

                            {Object.keys(errors).length > 0 && (
                                <div
                                    role="alert"
                                    className="mt-4 rounded-md border border-destructive/30 bg-destructive/10 px-3 py-2 text-sm text-destructive"
                                >
                                    One or more variance filters are invalid.
                                    Review the values or reset the filters and
                                    try again.
                                </div>
                            )}
                        </FilterToolbar>
                    )}
                </Form>

                <section
                    aria-labelledby="stock-count-variance-details-title"
                    className="overflow-hidden rounded-xl border border-border bg-card"
                >
                    <div className="flex min-h-14 flex-wrap items-center justify-between gap-3 border-b border-border px-4 py-3">
                        <div>
                            <h2
                                id="stock-count-variance-details-title"
                                className="text-sm font-semibold"
                            >
                                Variance details
                            </h2>
                            <p className="mt-0.5 text-xs text-muted-foreground">
                                Counted timestamps are shown in {timezone}.
                            </p>
                        </div>

                        <span className="text-xs font-medium text-muted-foreground">
                            {rows.length.toLocaleString()}{' '}
                            {rows.length === 1 ? 'line' : 'lines'}
                        </span>
                    </div>

                    {rows.length === 0 ? (
                        <div className="px-4 py-12">
                            <EmptyState
                                icon={ClipboardList}
                                title={
                                    hasFilters
                                        ? 'No matching count lines'
                                        : 'No finalized count lines'
                                }
                                description={
                                    hasFilters
                                        ? 'No finalized stock-count lines match the selected filters. Adjust or reset the filters.'
                                        : 'Finalized stock-count evidence will appear here once physical counts are completed.'
                                }
                                action={
                                    <Button variant="outline" size="sm" asChild>
                                        <Link
                                            href={
                                                hasFilters
                                                    ? StockCountController.variance()
                                                    : StockCountController.index()
                                            }
                                        >
                                            {hasFilters ? (
                                                <RotateCcw
                                                    className="size-4"
                                                    aria-hidden="true"
                                                />
                                            ) : (
                                                <ClipboardList
                                                    className="size-4"
                                                    aria-hidden="true"
                                                />
                                            )}
                                            {hasFilters
                                                ? 'Reset filters'
                                                : 'View stock counts'}
                                        </Link>
                                    </Button>
                                }
                            />
                        </div>
                    ) : (
                        <>
                            <div className="divide-y divide-border md:hidden">
                                {rows.map((row) => (
                                    <article
                                        key={`${row.countId}-${row.itemSku}`}
                                        className="space-y-4 p-4"
                                        aria-labelledby={`variance-${row.countId}-${row.itemSku}`}
                                    >
                                        <div className="flex items-start justify-between gap-3">
                                            <div className="min-w-0">
                                                <Link
                                                    id={`variance-${row.countId}-${row.itemSku}`}
                                                    href={StockCountController.edit(
                                                        row.countId,
                                                    )}
                                                    className="font-medium text-foreground underline-offset-4 hover:underline focus-visible:rounded-sm focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                                >
                                                    {row.countNumber}
                                                </Link>

                                                <p className="mt-1 text-sm font-medium">
                                                    {row.itemName}
                                                </p>
                                                <p className="text-xs text-muted-foreground">
                                                    <span className="font-mono">
                                                        {row.itemSku}
                                                    </span>
                                                    {' · '}
                                                    {row.locationName} ·{' '}
                                                    {row.storageLocationName}
                                                </p>
                                            </div>

                                            <VarianceIndicator
                                                value={
                                                    row.varianceBaseQuantity
                                                }
                                            />
                                        </div>

                                        <dl className="grid grid-cols-2 gap-x-4 gap-y-3 text-sm">
                                            <div>
                                                <dt className="text-xs text-muted-foreground">
                                                    Expected
                                                </dt>
                                                <dd className="mt-1 tabular-nums">
                                                    {formatDecimal(
                                                        row.expectedBaseQuantity,
                                                    )}{' '}
                                                    {row.baseUnitSymbol}
                                                </dd>
                                            </div>

                                            <div>
                                                <dt className="text-xs text-muted-foreground">
                                                    Counted
                                                </dt>
                                                <dd className="mt-1 tabular-nums">
                                                    {formatDecimal(
                                                        row.countedBaseQuantity,
                                                    )}{' '}
                                                    {row.baseUnitSymbol}
                                                </dd>
                                            </div>

                                            <div>
                                                <dt className="text-xs text-muted-foreground">
                                                    Variance
                                                </dt>
                                                <dd className="mt-1 font-medium tabular-nums">
                                                    {formatDecimal(
                                                        row.varianceBaseQuantity,
                                                    )}{' '}
                                                    {row.baseUnitSymbol}
                                                </dd>
                                            </div>

                                            <div>
                                                <dt className="text-xs text-muted-foreground">
                                                    Entered
                                                </dt>
                                                <dd className="mt-1 tabular-nums">
                                                    {formatDecimal(
                                                        row.countedQuantity,
                                                    )}{' '}
                                                    {row.countUnitSymbol}
                                                </dd>
                                            </div>

                                            <div>
                                                <dt className="text-xs text-muted-foreground">
                                                    Counted at
                                                </dt>
                                                <dd className="mt-1 tabular-nums">
                                                    {row.countedAt === null ? (
                                                        '—'
                                                    ) : (
                                                        <time
                                                            dateTime={
                                                                row.countedAt
                                                            }
                                                        >
                                                            {formatOrganizationDate(
                                                                row.countedAt,
                                                                timezone,
                                                            )}
                                                        </time>
                                                    )}
                                                </dd>
                                            </div>

                                            <div>
                                                <dt className="text-xs text-muted-foreground">
                                                    Movement
                                                </dt>
                                                <dd className="mt-1 tabular-nums">
                                                    {row.movementId === null
                                                        ? 'No movement'
                                                        : `#${row.movementId}`}
                                                </dd>
                                            </div>
                                        </dl>

                                        {canViewCosts && (
                                            <dl className="grid grid-cols-2 gap-4 border-t border-border pt-3 text-sm">
                                                <div>
                                                    <dt className="text-xs text-muted-foreground">
                                                        Unit cost
                                                    </dt>
                                                    <dd className="mt-1 tabular-nums">
                                                        {row.varianceUnitCost ===
                                                        null
                                                            ? '—'
                                                            : formatCurrency(
                                                                  row.varianceUnitCost,
                                                                  currency,
                                                              )}
                                                    </dd>
                                                </div>

                                                <div>
                                                    <dt className="text-xs text-muted-foreground">
                                                        Variance value
                                                    </dt>
                                                    <dd className="mt-1 tabular-nums">
                                                        {row.varianceTotalCost ===
                                                        null
                                                            ? '—'
                                                            : formatCurrency(
                                                                  row.varianceTotalCost,
                                                                  currency,
                                                              )}
                                                    </dd>
                                                </div>
                                            </dl>
                                        )}
                                    </article>
                                ))}
                            </div>

                            <div className="hidden overflow-x-auto md:block">
                                <table className="w-full min-w-[1180px] text-sm">
                                    <caption className="sr-only">
                                        Finalized stock-count variance lines
                                        showing count evidence, quantities,
                                        optional authorized costs, and stock
                                        movement references.
                                    </caption>
                                    <thead className="bg-muted/40 text-left text-xs text-muted-foreground">
                                        <tr className="border-b border-border">
                                            <th
                                                scope="col"
                                                className="px-4 py-3"
                                            >
                                                Count
                                            </th>
                                            <th
                                                scope="col"
                                                className="px-4 py-3"
                                            >
                                                Location
                                            </th>
                                            <th
                                                scope="col"
                                                className="px-4 py-3"
                                            >
                                                Item
                                            </th>
                                            <th
                                                scope="col"
                                                className="px-4 py-3 text-right"
                                            >
                                                Expected
                                            </th>
                                            <th
                                                scope="col"
                                                className="px-4 py-3 text-right"
                                            >
                                                Counted
                                            </th>
                                            <th
                                                scope="col"
                                                className="px-4 py-3 text-right"
                                            >
                                                Variance
                                            </th>

                                            {canViewCosts && (
                                                <>
                                                    <th
                                                        scope="col"
                                                        className="px-4 py-3 text-right"
                                                    >
                                                        Unit cost
                                                    </th>
                                                    <th
                                                        scope="col"
                                                        className="px-4 py-3 text-right"
                                                    >
                                                        Variance value
                                                    </th>
                                                </>
                                            )}

                                            <th
                                                scope="col"
                                                className="px-4 py-3"
                                            >
                                                Movement
                                            </th>
                                        </tr>
                                    </thead>

                                    <tbody>
                                        {rows.map((row) => (
                                            <tr
                                                key={`${row.countId}-${row.itemSku}`}
                                                className="border-b border-border transition-colors last:border-b-0 hover:bg-muted/30 motion-reduce:transition-none"
                                            >
                                                <td className="px-4 py-3">
                                                    <Link
                                                        href={StockCountController.edit(
                                                            row.countId,
                                                        )}
                                                        className="font-medium text-foreground underline-offset-4 hover:underline focus-visible:rounded-sm focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                                    >
                                                        {row.countNumber}
                                                    </Link>

                                                    <div className="mt-1 text-xs text-muted-foreground tabular-nums">
                                                        {row.countedAt ===
                                                        null ? (
                                                            '—'
                                                        ) : (
                                                            <time
                                                                dateTime={
                                                                    row.countedAt
                                                                }
                                                            >
                                                                {formatOrganizationDate(
                                                                    row.countedAt,
                                                                    timezone,
                                                                )}
                                                            </time>
                                                        )}
                                                    </div>
                                                </td>

                                                <td className="px-4 py-3">
                                                    <div>{row.locationName}</div>
                                                    <div className="mt-1 text-xs text-muted-foreground">
                                                        {
                                                            row.storageLocationName
                                                        }
                                                    </div>
                                                </td>

                                                <td className="px-4 py-3">
                                                    <div className="font-medium">
                                                        {row.itemName}
                                                    </div>
                                                    <div className="mt-1 font-mono text-xs text-muted-foreground">
                                                        {row.itemSku}
                                                    </div>
                                                </td>

                                                <td className="px-4 py-3 text-right whitespace-nowrap tabular-nums">
                                                    {formatDecimal(
                                                        row.expectedBaseQuantity,
                                                    )}{' '}
                                                    {row.baseUnitSymbol}
                                                </td>

                                                <td className="px-4 py-3 text-right whitespace-nowrap tabular-nums">
                                                    {formatDecimal(
                                                        row.countedBaseQuantity,
                                                    )}{' '}
                                                    {row.baseUnitSymbol}
                                                    <div className="mt-1 text-xs text-muted-foreground">
                                                        Entered:{' '}
                                                        {formatDecimal(
                                                            row.countedQuantity,
                                                        )}{' '}
                                                        {row.countUnitSymbol}
                                                    </div>
                                                </td>

                                                <td className="px-4 py-3 text-right">
                                                    <div className="flex flex-col items-end gap-1.5">
                                                        <VarianceIndicator
                                                            value={
                                                                row.varianceBaseQuantity
                                                            }
                                                        />
                                                        <span className="font-medium whitespace-nowrap tabular-nums">
                                                            {formatDecimal(
                                                                row.varianceBaseQuantity,
                                                            )}{' '}
                                                            {row.baseUnitSymbol}
                                                        </span>
                                                    </div>
                                                </td>

                                                {canViewCosts && (
                                                    <>
                                                        <td className="px-4 py-3 text-right whitespace-nowrap tabular-nums">
                                                            {row.varianceUnitCost ===
                                                            null
                                                                ? '—'
                                                                : formatCurrency(
                                                                      row.varianceUnitCost,
                                                                      currency,
                                                                  )}
                                                        </td>

                                                        <td className="px-4 py-3 text-right whitespace-nowrap tabular-nums">
                                                            {row.varianceTotalCost ===
                                                            null
                                                                ? '—'
                                                                : formatCurrency(
                                                                      row.varianceTotalCost,
                                                                      currency,
                                                                  )}
                                                        </td>
                                                    </>
                                                )}

                                                <td className="px-4 py-3 whitespace-nowrap tabular-nums">
                                                    {row.movementId === null
                                                        ? 'No movement'
                                                        : `#${row.movementId}`}
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
            title: 'Variance',
            href: StockCountController.variance(),
        },
    ],
};
