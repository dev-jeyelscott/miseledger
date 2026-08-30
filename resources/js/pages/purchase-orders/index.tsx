import { Form, Head, Link } from '@inertiajs/react';
import {
    ClipboardList,
    Clock,
    Coins,
    Filter,
    Package,
    Plus,
    RotateCcw,
    Search,
} from 'lucide-react';

import PurchaseOrderController from '@/actions/App/Http/Controllers/Purchasing/PurchaseOrderController';
import { DashboardMetricCard } from '@/components/dashboard/dashboard-metric-card';
import { EmptyState } from '@/components/empty-state';
import { FilterToolbar } from '@/components/filter-toolbar';
import { PageHeader } from '@/components/page-header';
import { PaginationControls } from '@/components/pagination-controls';
import { StatusBadge } from '@/components/status-badge';
import type { StatusBadgeProps } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { Field } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';
import { dashboard } from '@/routes';

type PurchaseOrderStatus =
    'draft' | 'approved' | 'partially_received' | 'received' | 'cancelled';

type PurchaseOrderRow = {
    id: number;
    number: string;
    status: PurchaseOrderStatus;
    supplierName: string;
    locationName: string;
    orderDate: string;
    expectedDeliveryDate: string | null;
    lineCount: number;
    total: string;
};

type PaginatedPurchaseOrders = {
    current_page: number;
    data: PurchaseOrderRow[];
    from: number | null;
    last_page: number;
    next_page_url: string | null;
    per_page: number;
    prev_page_url: string | null;
    to: number | null;
    total: number;
};

type PurchaseOrderSummary = {
    openCount: number;
    awaitingDeliveryCount: number;
    partiallyReceivedCount: number;
    thisMonthSpend: string | null;
};

type Option = {
    id: number;
    name: string;
};

type Props = {
    purchaseOrders: PaginatedPurchaseOrders;
    summary: PurchaseOrderSummary;
    supplierOptions: Option[];
    locationOptions: Option[];
    filters: {
        search: string | null;
        status: PurchaseOrderStatus | null;
        supplierId: number | null;
        locationId: number | null;
        from: string | null;
        to: string | null;
    };
    currency: string;
    canManage: boolean;
    canViewCosts: boolean;
};

const statusOptions: Array<{
    value: PurchaseOrderStatus;
    label: string;
}> = [
    { value: 'draft', label: 'Draft' },
    { value: 'approved', label: 'Approved' },
    { value: 'partially_received', label: 'Partially received' },
    { value: 'received', label: 'Received' },
    { value: 'cancelled', label: 'Cancelled' },
];

const dateFormatter = new Intl.DateTimeFormat('en-PH', {
    year: 'numeric',
    month: 'short',
    day: '2-digit',
    timeZone: 'UTC',
});

/** Format an authoritative decimal money string without JavaScript floats. */
function formatMoney(value: string): string {
    const [rawInteger, rawDecimal = ''] = value.trim().split('.');
    const negative = rawInteger.startsWith('-');
    const integerDigits = negative ? rawInteger.slice(1) : rawInteger;

    const groupedInteger = integerDigits.replace(/\B(?=(\d{3})+(?!\d))/g, ',');

    const decimal = rawDecimal.padEnd(2, '0').slice(0, 2);

    return `${negative ? '-' : ''}${groupedInteger}.${decimal}`;
}

/** Format a persisted calendar date without browser timezone drift. */
function formatDate(value: string): string {
    return dateFormatter.format(new Date(`${value}T00:00:00Z`));
}

function statusVariant(
    status: PurchaseOrderStatus,
): StatusBadgeProps['variant'] {
    switch (status) {
        case 'approved':
            return 'info';
        case 'partially_received':
            return 'warning';
        case 'received':
            return 'success';
        case 'cancelled':
            return 'danger';
        case 'draft':
        default:
            return 'neutral';
    }
}

/** Convert the persisted status value into its operational label. */
function statusLabel(status: PurchaseOrderStatus): string {
    return (
        statusOptions.find((option) => option.value === status)?.label ?? status
    );
}

export default function PurchaseOrderIndex({
    purchaseOrders,
    summary,
    supplierOptions,
    locationOptions,
    filters,
    currency,
    canManage,
    canViewCosts,
}: Props) {
    const activeFilterCount = [
        filters.search,
        filters.status,
        filters.supplierId,
        filters.locationId,
        filters.from,
        filters.to,
    ].filter((value) => value !== null).length;

    const hasFilters = activeFilterCount > 0;

    return (
        <>
            <Head title="Purchase orders" />

            <div className="flex flex-1 flex-col gap-6 p-4 sm:p-6">
                <PageHeader
                    title="Purchase orders"
                    description="Order stock from configured suppliers and track PO fulfillment."
                    actions={
                        canManage && (
                            <Button asChild>
                                <Link href={PurchaseOrderController.create()}>
                                    <Plus
                                        className="size-4"
                                        aria-hidden="true"
                                    />
                                    Create purchase order
                                </Link>
                            </Button>
                        )
                    }
                />

                <div
                    className={
                        canViewCosts
                            ? 'grid gap-4 sm:grid-cols-2 xl:grid-cols-4'
                            : 'grid gap-4 sm:grid-cols-2 xl:grid-cols-3'
                    }
                >
                    <DashboardMetricCard
                        title="Open POs"
                        value={summary.openCount.toLocaleString()}
                        description="Draft, approved, and partially received orders"
                        icon={ClipboardList}
                        tone="blue"
                    />

                    <DashboardMetricCard
                        title="Awaiting delivery"
                        value={summary.awaitingDeliveryCount.toLocaleString()}
                        description="Approved orders not yet receiving stock"
                        icon={Clock}
                        tone="amber"
                    />

                    <DashboardMetricCard
                        title="Partially received"
                        value={summary.partiallyReceivedCount.toLocaleString()}
                        description="Orders with remaining quantities to receive"
                        icon={Package}
                        tone="teal"
                    />

                    {canViewCosts && summary.thisMonthSpend !== null && (
                        <DashboardMetricCard
                            title="This month spend"
                            value={`${currency} ${formatMoney(
                                summary.thisMonthSpend,
                            )}`}
                            description="Approved, receiving, and received PO value ordered this month"
                            icon={Coins}
                            tone="violet"
                        />
                    )}
                </div>

                <Form action={PurchaseOrderController.index().url} method="get">
                    {({ errors, processing }) => (
                        <FilterToolbar>
                            <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-7">
                                <Field
                                    id="search"
                                    label="Search"
                                    className="md:col-span-2 xl:col-span-2"
                                    error={errors.search}
                                >
                                    <div className="relative">
                                        <Search
                                            className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                                            aria-hidden="true"
                                        />

                                        <Input
                                            name="search"
                                            type="search"
                                            defaultValue={filters.search ?? ''}
                                            placeholder="PO number or supplier"
                                            className="pl-9"
                                            maxLength={120}
                                            autoComplete="off"
                                        />
                                    </div>
                                </Field>

                                <Field
                                    id="status"
                                    label="Status"
                                    error={errors.status}
                                >
                                    <NativeSelect
                                        name="status"
                                        defaultValue={filters.status ?? ''}
                                    >
                                        <option value="">All statuses</option>

                                        {statusOptions.map((status) => (
                                            <option
                                                key={status.value}
                                                value={status.value}
                                            >
                                                {status.label}
                                            </option>
                                        ))}
                                    </NativeSelect>
                                </Field>

                                <Field
                                    id="supplier_id"
                                    label="Supplier"
                                    error={errors.supplier_id}
                                >
                                    <NativeSelect
                                        name="supplier_id"
                                        defaultValue={
                                            filters.supplierId?.toString() ?? ''
                                        }
                                    >
                                        <option value="">All suppliers</option>

                                        {supplierOptions.map((supplier) => (
                                            <option
                                                key={supplier.id}
                                                value={supplier.id}
                                            >
                                                {supplier.name}
                                            </option>
                                        ))}
                                    </NativeSelect>
                                </Field>

                                <Field
                                    id="location_id"
                                    label="Location"
                                    error={errors.location_id}
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
                                    label="Order date from"
                                    error={errors.from}
                                >
                                    <Input
                                        name="from"
                                        type="date"
                                        defaultValue={filters.from ?? ''}
                                    />
                                </Field>

                                <Field
                                    id="to"
                                    label="Order date to"
                                    error={errors.to}
                                >
                                    <Input
                                        name="to"
                                        type="date"
                                        defaultValue={filters.to ?? ''}
                                    />
                                </Field>

                                <div className="flex items-end gap-2 md:col-span-2 xl:col-span-7 xl:justify-end">
                                    <Button
                                        type="submit"
                                        disabled={processing}
                                        className="min-w-24"
                                    >
                                        <Filter
                                            className="size-4"
                                            aria-hidden="true"
                                        />
                                        {processing ? 'Applying…' : 'Apply'}
                                    </Button>

                                    <Button variant="outline" asChild>
                                        <Link
                                            href={PurchaseOrderController.index()}
                                        >
                                            <RotateCcw
                                                className="size-4"
                                                aria-hidden="true"
                                            />
                                            Clear
                                        </Link>
                                    </Button>
                                </div>
                            </div>
                        </FilterToolbar>
                    )}
                </Form>

                <section
                    className="overflow-hidden rounded-xl border border-border bg-card text-card-foreground"
                    aria-labelledby="purchase-order-register-title"
                >
                    <div className="flex min-h-14 flex-col justify-center gap-1 border-b border-border px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <h2
                                id="purchase-order-register-title"
                                className="text-sm font-semibold"
                            >
                                Purchase order register
                            </h2>

                            <p className="mt-0.5 text-xs text-muted-foreground">
                                {purchaseOrders.total.toLocaleString()}{' '}
                                {purchaseOrders.total === 1
                                    ? 'order'
                                    : 'orders'}
                                {hasFilters
                                    ? ` match ${activeFilterCount} active ${
                                          activeFilterCount === 1
                                              ? 'filter'
                                              : 'filters'
                                      }`
                                    : ' in this organization'}
                            </p>
                        </div>
                    </div>

                    {purchaseOrders.data.length === 0 ? (
                        <EmptyState
                            className="px-6 py-14"
                            icon={ClipboardList}
                            title={
                                hasFilters
                                    ? 'No purchase orders found'
                                    : 'No purchase orders yet'
                            }
                            description={
                                hasFilters
                                    ? 'Adjust or clear the filters to view other orders.'
                                    : canManage
                                      ? 'Create a purchase order when stock needs to be ordered.'
                                      : 'Purchase orders will appear here when they are created.'
                            }
                        />
                    ) : (
                        <>
                            <div
                                className="divide-y divide-border md:hidden"
                                data-testid="mobile-purchase-orders"
                            >
                                {purchaseOrders.data.map((purchaseOrder) => (
                                    <article
                                        key={purchaseOrder.id}
                                        className="space-y-3 p-4"
                                        aria-labelledby={`purchase-order-${purchaseOrder.id}`}
                                    >
                                        <div className="flex items-start justify-between gap-3">
                                            <div className="min-w-0">
                                                <Link
                                                    id={`purchase-order-${purchaseOrder.id}`}
                                                    href={PurchaseOrderController.edit(
                                                        purchaseOrder.id,
                                                    )}
                                                    className="font-medium text-foreground underline-offset-4 hover:underline focus-visible:rounded-sm focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                                >
                                                    {purchaseOrder.number}
                                                </Link>
                                                <p className="mt-1 truncate text-sm text-muted-foreground">
                                                    {purchaseOrder.supplierName}{' '}
                                                    ·{' '}
                                                    {purchaseOrder.locationName}
                                                </p>
                                            </div>
                                            <StatusBadge
                                                label={statusLabel(
                                                    purchaseOrder.status,
                                                )}
                                                variant={statusVariant(
                                                    purchaseOrder.status,
                                                )}
                                            />
                                        </div>

                                        <dl className="grid grid-cols-2 gap-x-4 gap-y-3 text-sm">
                                            <div>
                                                <dt className="text-xs text-muted-foreground">
                                                    Order date
                                                </dt>
                                                <dd className="mt-1">
                                                    {formatDate(
                                                        purchaseOrder.orderDate,
                                                    )}
                                                </dd>
                                            </div>
                                            <div>
                                                <dt className="text-xs text-muted-foreground">
                                                    Expected delivery
                                                </dt>
                                                <dd className="mt-1">
                                                    {purchaseOrder.expectedDeliveryDate ===
                                                    null
                                                        ? '—'
                                                        : formatDate(
                                                              purchaseOrder.expectedDeliveryDate,
                                                          )}
                                                </dd>
                                            </div>
                                            <div>
                                                <dt className="text-xs text-muted-foreground">
                                                    Items
                                                </dt>
                                                <dd className="mt-1 tabular-nums">
                                                    {purchaseOrder.lineCount.toLocaleString()}
                                                </dd>
                                            </div>
                                            <div>
                                                <dt className="text-xs text-muted-foreground">
                                                    Total
                                                </dt>
                                                <dd className="mt-1 font-medium tabular-nums">
                                                    {currency}{' '}
                                                    {formatMoney(
                                                        purchaseOrder.total,
                                                    )}
                                                </dd>
                                            </div>
                                        </dl>

                                        <Button
                                            variant="outline"
                                            size="sm"
                                            className="w-full"
                                            asChild
                                        >
                                            <Link
                                                href={PurchaseOrderController.edit(
                                                    purchaseOrder.id,
                                                )}
                                            >
                                                {canManage &&
                                                purchaseOrder.status === 'draft'
                                                    ? 'Edit'
                                                    : 'Open'}
                                            </Link>
                                        </Button>
                                    </article>
                                ))}
                            </div>

                            <div className="hidden overflow-x-auto md:block">
                                <table className="w-full min-w-[1080px] text-sm">
                                    <caption className="sr-only">
                                        Tenant-scoped purchase orders with
                                        supplier, location, delivery, status,
                                        item count, and total.
                                    </caption>

                                    <thead className="border-b bg-muted/40 text-left">
                                        <tr>
                                            <th
                                                scope="col"
                                                className="px-4 py-3 font-medium text-muted-foreground"
                                            >
                                                PO number
                                            </th>

                                            <th
                                                scope="col"
                                                className="px-4 py-3 font-medium text-muted-foreground"
                                            >
                                                Supplier
                                            </th>

                                            <th
                                                scope="col"
                                                className="px-4 py-3 font-medium text-muted-foreground"
                                            >
                                                Location
                                            </th>

                                            <th
                                                scope="col"
                                                className="px-4 py-3 font-medium text-muted-foreground"
                                            >
                                                Order date
                                            </th>

                                            <th
                                                scope="col"
                                                className="px-4 py-3 font-medium text-muted-foreground"
                                            >
                                                Expected delivery
                                            </th>

                                            <th
                                                scope="col"
                                                className="px-4 py-3 font-medium text-muted-foreground"
                                            >
                                                Status
                                            </th>

                                            <th
                                                scope="col"
                                                className="px-4 py-3 text-right font-medium text-muted-foreground"
                                            >
                                                Items
                                            </th>

                                            <th
                                                scope="col"
                                                className="px-4 py-3 text-right font-medium text-muted-foreground"
                                            >
                                                Total
                                            </th>

                                            <th
                                                scope="col"
                                                className="px-4 py-3 text-right font-medium text-muted-foreground"
                                            >
                                                Action
                                            </th>
                                        </tr>
                                    </thead>

                                    <tbody>
                                        {purchaseOrders.data.map(
                                            (purchaseOrder) => (
                                                <tr
                                                    key={purchaseOrder.id}
                                                    className="border-b border-border transition-colors last:border-b-0 hover:bg-muted/30"
                                                >
                                                    <td className="px-4 py-3">
                                                        <Link
                                                            href={PurchaseOrderController.edit(
                                                                purchaseOrder.id,
                                                            )}
                                                            className="font-medium underline-offset-4 hover:underline focus-visible:rounded-sm focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
                                                        >
                                                            {
                                                                purchaseOrder.number
                                                            }
                                                        </Link>
                                                    </td>

                                                    <td className="px-4 py-3 font-medium">
                                                        {
                                                            purchaseOrder.supplierName
                                                        }
                                                    </td>

                                                    <td className="px-4 py-3">
                                                        {
                                                            purchaseOrder.locationName
                                                        }
                                                    </td>

                                                    <td className="px-4 py-3 whitespace-nowrap">
                                                        {formatDate(
                                                            purchaseOrder.orderDate,
                                                        )}
                                                    </td>

                                                    <td className="px-4 py-3 whitespace-nowrap">
                                                        {purchaseOrder.expectedDeliveryDate ===
                                                        null ? (
                                                            <span className="text-muted-foreground">
                                                                —
                                                            </span>
                                                        ) : (
                                                            formatDate(
                                                                purchaseOrder.expectedDeliveryDate,
                                                            )
                                                        )}
                                                    </td>

                                                    <td className="px-4 py-3">
                                                        <StatusBadge
                                                            label={statusLabel(
                                                                purchaseOrder.status,
                                                            )}
                                                            variant={statusVariant(
                                                                purchaseOrder.status,
                                                            )}
                                                        />
                                                    </td>

                                                    <td className="px-4 py-3 text-right tabular-nums">
                                                        {purchaseOrder.lineCount.toLocaleString()}
                                                    </td>

                                                    <td className="px-4 py-3 text-right font-medium whitespace-nowrap tabular-nums">
                                                        {currency}{' '}
                                                        {formatMoney(
                                                            purchaseOrder.total,
                                                        )}
                                                    </td>

                                                    <td className="px-4 py-3 text-right">
                                                        <Button
                                                            variant="ghost"
                                                            size="sm"
                                                            asChild
                                                        >
                                                            <Link
                                                                href={PurchaseOrderController.edit(
                                                                    purchaseOrder.id,
                                                                )}
                                                            >
                                                                {canManage &&
                                                                purchaseOrder.status ===
                                                                    'draft'
                                                                    ? 'Edit'
                                                                    : 'Open'}
                                                            </Link>
                                                        </Button>
                                                    </td>
                                                </tr>
                                            ),
                                        )}
                                    </tbody>
                                </table>
                            </div>
                        </>
                    )}

                    <PaginationControls
                        currentPage={purchaseOrders.current_page}
                        from={purchaseOrders.from}
                        to={purchaseOrders.to}
                        total={purchaseOrders.total}
                        lastPage={purchaseOrders.last_page}
                        previousPageUrl={purchaseOrders.prev_page_url}
                        nextPageUrl={purchaseOrders.next_page_url}
                        itemLabel="purchase orders"
                        preserveScroll
                        preserveState
                    />
                </section>
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
