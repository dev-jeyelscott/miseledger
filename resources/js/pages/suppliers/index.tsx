import { Form, Head, Link } from '@inertiajs/react';
import {
    Building2,
    CheckCircle2,
    ChevronLeft,
    ChevronRight,
    ClipboardList,
    DollarSign,
    Filter,
    Package,
    Plus,
    RotateCcw,
    Search,
} from 'lucide-react';

import SupplierController from '@/actions/App/Http/Controllers/Suppliers/SupplierController';
import { DashboardMetricCard } from '@/components/dashboard/dashboard-metric-card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { dashboard } from '@/routes';

type SupplierStatus = 'active' | 'inactive';

type SupplierSort =
    'name_asc' | 'name_desc' | 'code_asc' | 'code_desc' | 'items_desc';

type Supplier = {
    id: number;
    name: string;
    code: string;
    contactName: string | null;
    email: string | null;
    phone: string | null;
    paymentTerms: string | null;
    leadTimeDays: number | null;
    itemCount: number;
    active: boolean;
    lastPurchaseOrderNumber: string | null;
    lastPurchaseOrderDate: string | null;
};

type Summary = {
    totalSuppliers: number;
    activeSuppliers: number;
    linkedItems: number;
    openPurchaseOrders: number;
    purchaseValueYtd: string | null;
};

type PaginationPage = {
    page: number;
    url: string;
    active: boolean;
};

type Pagination = {
    currentPage: number;
    lastPage: number;
    perPage: number;
    from: number | null;
    to: number | null;
    total: number;
    previousPageUrl: string | null;
    nextPageUrl: string | null;
    pages: PaginationPage[];
};

type Props = {
    suppliers: Supplier[];
    summary: Summary;
    pagination: Pagination;
    filters: {
        search: string | null;
        status: SupplierStatus | null;
        sort: SupplierSort;
        perPage: number;
    };
    currency: string;
    canViewCosts: boolean;
    canManage: boolean;
};

const selectClassName =
    'h-9 w-full rounded-md border border-input bg-background px-3 text-sm shadow-xs outline-none transition-[color,box-shadow] focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:cursor-not-allowed disabled:opacity-50';

/** Format a persisted currency string without converting it to floating point. */
function formatCurrency(value: string, currency: string): string {
    const [rawInteger, rawDecimal = ''] = value.trim().split('.');
    const negative = rawInteger.startsWith('-');
    const integerDigits = negative ? rawInteger.slice(1) : rawInteger;
    const groupedInteger = integerDigits.replace(/\B(?=(\d{3})+(?!\d))/g, ',');

    const decimal =
        rawDecimal.length >= 2 ? rawDecimal : rawDecimal.padEnd(2, '0');

    return `${currency} ${negative ? '-' : ''}${groupedInteger}.${decimal}`;
}

/** Format one persisted date-only value without introducing UTC date shifting. */
function formatDate(value: string): string {
    const [year, month, day] = value.split('-').map(Number);

    if (
        !Number.isInteger(year) ||
        !Number.isInteger(month) ||
        !Number.isInteger(day)
    ) {
        return value;
    }

    return new Intl.DateTimeFormat(undefined, {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    }).format(new Date(year, month - 1, day));
}

/** Build compact supported purchasing metadata for a supplier. */
function supplierMetadata(supplier: Supplier): string {
    const metadata = [
        supplier.paymentTerms,
        supplier.leadTimeDays !== null
            ? `${supplier.leadTimeDays} day lead time`
            : null,
    ].filter((value): value is string => value !== null);

    return metadata.length > 0 ? metadata.join(' · ') : 'No purchasing terms';
}

/** Return accessible status styling while retaining a visible text label. */
function supplierStatusClassName(active: boolean): string {
    return active
        ? 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-300'
        : 'border-border bg-muted/50 text-muted-foreground';
}

export default function SuppliersIndex({
    suppliers,
    summary,
    pagination,
    filters,
    currency,
    canViewCosts,
    canManage,
}: Props) {
    const hasFilters =
        filters.search !== null ||
        filters.status !== null ||
        filters.sort !== 'name_asc';

    const resultDescription =
        pagination.total === summary.totalSuppliers
            ? `${pagination.total.toLocaleString()} ${
                  pagination.total === 1 ? 'supplier' : 'suppliers'
              }`
            : `${pagination.total.toLocaleString()} of ${summary.totalSuppliers.toLocaleString()} suppliers match`;

    return (
        <>
            <Head title="Suppliers" />

            <div className="flex flex-1 flex-col gap-6 p-4 sm:p-6">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">
                            Suppliers
                        </h1>

                        <p className="mt-1 text-sm text-muted-foreground">
                            Manage vendors, purchase packs, and supplier
                            pricing.
                        </p>
                    </div>

                    {canManage && (
                        <Button asChild>
                            <Link href={SupplierController.create()}>
                                <Plus className="size-4" aria-hidden="true" />
                                New supplier
                            </Link>
                        </Button>
                    )}
                </div>

                <div
                    className={
                        canViewCosts
                            ? 'grid gap-4 sm:grid-cols-2 xl:grid-cols-5'
                            : 'grid gap-4 sm:grid-cols-2 xl:grid-cols-4'
                    }
                >
                    <DashboardMetricCard
                        title="Total suppliers"
                        value={summary.totalSuppliers.toLocaleString()}
                        description="All registered supplier records"
                        icon={Building2}
                        tone="blue"
                    />

                    <DashboardMetricCard
                        title="Active suppliers"
                        value={summary.activeSuppliers.toLocaleString()}
                        description="Available for new purchasing"
                        icon={CheckCircle2}
                        tone="emerald"
                    />

                    <DashboardMetricCard
                        title="Linked items"
                        value={summary.linkedItems.toLocaleString()}
                        description="Supplier purchase-pack mappings"
                        icon={Package}
                        tone="violet"
                    />

                    <DashboardMetricCard
                        title="Open purchase orders"
                        value={summary.openPurchaseOrders.toLocaleString()}
                        description="Draft, approved, or partially received"
                        icon={ClipboardList}
                        tone="amber"
                    />

                    {canViewCosts && summary.purchaseValueYtd !== null && (
                        <DashboardMetricCard
                            title="Purchase value (YTD)"
                            value={formatCurrency(
                                summary.purchaseValueYtd,
                                currency,
                            )}
                            description="Approved and received PO value this year"
                            icon={DollarSign}
                            tone="teal"
                        />
                    )}
                </div>

                <Form
                    action={SupplierController.index().url}
                    method="get"
                    options={{
                        preserveState: true,
                        replace: true,
                    }}
                >
                    {({ processing }) => (
                        <div className="grid gap-4 rounded-xl border border-sidebar-border/70 bg-card p-4 shadow-sm lg:grid-cols-2 xl:grid-cols-[minmax(280px,1.6fr)_minmax(150px,0.7fr)_minmax(190px,0.8fr)_110px_auto] dark:border-sidebar-border">
                            <div className="grid gap-2">
                                <Label htmlFor="supplier-search">Search</Label>

                                <div className="relative">
                                    <Search
                                        className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                                        aria-hidden="true"
                                    />

                                    <Input
                                        id="supplier-search"
                                        type="search"
                                        name="search"
                                        defaultValue={filters.search ?? ''}
                                        placeholder="Search name, code, contact, email, or phone"
                                        className="pl-9"
                                        autoComplete="off"
                                    />
                                </div>
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="supplier-status">Status</Label>

                                <select
                                    id="supplier-status"
                                    name="status"
                                    defaultValue={filters.status ?? ''}
                                    className={selectClassName}
                                >
                                    <option value="">All statuses</option>
                                    <option value="active">Active</option>
                                    <option value="inactive">Inactive</option>
                                </select>
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="supplier-sort">Sort by</Label>

                                <select
                                    id="supplier-sort"
                                    name="sort"
                                    defaultValue={filters.sort}
                                    className={selectClassName}
                                >
                                    <option value="name_asc">
                                        Supplier name (A-Z)
                                    </option>
                                    <option value="name_desc">
                                        Supplier name (Z-A)
                                    </option>
                                    <option value="code_asc">
                                        Supplier code (A-Z)
                                    </option>
                                    <option value="code_desc">
                                        Supplier code (Z-A)
                                    </option>
                                    <option value="items_desc">
                                        Most linked items
                                    </option>
                                </select>
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="supplier-per-page">Rows</Label>

                                <select
                                    id="supplier-per-page"
                                    name="per_page"
                                    defaultValue={filters.perPage.toString()}
                                    className={selectClassName}
                                >
                                    <option value="10">10</option>
                                    <option value="25">25</option>
                                    <option value="50">50</option>
                                </select>
                            </div>

                            <div className="flex items-end gap-2 lg:col-span-2 xl:col-span-1">
                                <Button
                                    type="submit"
                                    disabled={processing}
                                    className="min-w-24 flex-1 xl:flex-none"
                                >
                                    <Filter
                                        className="size-4"
                                        aria-hidden="true"
                                    />
                                    {processing ? 'Applying…' : 'Apply'}
                                </Button>

                                <Button
                                    variant="outline"
                                    className="flex-1 xl:flex-none"
                                    asChild
                                >
                                    <Link href={SupplierController.index()}>
                                        <RotateCcw
                                            className="size-4"
                                            aria-hidden="true"
                                        />
                                        Clear
                                    </Link>
                                </Button>
                            </div>
                        </div>
                    )}
                </Form>

                <section
                    className="overflow-hidden rounded-xl border border-sidebar-border/70 bg-card shadow-sm dark:border-sidebar-border"
                    aria-labelledby="supplier-results-title"
                >
                    <div className="flex min-h-14 flex-wrap items-center justify-between gap-3 border-b border-sidebar-border/70 px-4 py-3 dark:border-sidebar-border">
                        <div>
                            <h2
                                id="supplier-results-title"
                                className="text-sm font-semibold"
                            >
                                Supplier directory
                            </h2>

                            <p className="mt-0.5 text-xs text-muted-foreground">
                                {resultDescription}
                                {hasFilters ? ' using the current filters' : ''}
                            </p>
                        </div>
                    </div>

                    <div className="overflow-x-auto">
                        <table className="w-full min-w-[1080px] text-sm">
                            <caption className="sr-only">
                                Organization-scoped suppliers with purchasing
                                and contact context.
                            </caption>

                            <thead className="border-b bg-muted/40 text-left">
                                <tr>
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
                                        Code
                                    </th>

                                    <th
                                        scope="col"
                                        className="px-4 py-3 font-medium text-muted-foreground"
                                    >
                                        Primary contact
                                    </th>

                                    <th
                                        scope="col"
                                        className="px-4 py-3 text-right font-medium text-muted-foreground"
                                    >
                                        Items
                                    </th>

                                    <th
                                        scope="col"
                                        className="px-4 py-3 font-medium text-muted-foreground"
                                    >
                                        Last PO
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
                                        Action
                                    </th>
                                </tr>
                            </thead>

                            <tbody>
                                {suppliers.length === 0 ? (
                                    <tr>
                                        <td
                                            colSpan={7}
                                            className="px-6 py-14 text-center"
                                        >
                                            <div className="mx-auto flex size-10 items-center justify-center rounded-full bg-muted">
                                                <Search
                                                    className="size-5 text-muted-foreground"
                                                    aria-hidden="true"
                                                />
                                            </div>

                                            <p className="mt-3 font-medium">
                                                {hasFilters
                                                    ? 'No suppliers match the current filters'
                                                    : 'No suppliers have been created'}
                                            </p>

                                            <p className="mt-1 text-sm text-muted-foreground">
                                                {hasFilters
                                                    ? 'Adjust or clear the filters to view available suppliers.'
                                                    : 'Create a supplier to begin configuring purchase packs and pricing.'}
                                            </p>
                                        </td>
                                    </tr>
                                ) : (
                                    suppliers.map((supplier) => (
                                        <tr
                                            key={supplier.id}
                                            className="border-b transition-colors last:border-b-0 hover:bg-muted/30"
                                        >
                                            <td className="px-4 py-3">
                                                <div className="min-w-0">
                                                    <Link
                                                        href={SupplierController.edit(
                                                            supplier.id,
                                                        )}
                                                        className="font-medium hover:underline focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                                    >
                                                        {supplier.name}
                                                    </Link>

                                                    <p className="mt-0.5 truncate text-xs text-muted-foreground">
                                                        {supplierMetadata(
                                                            supplier,
                                                        )}
                                                    </p>
                                                </div>
                                            </td>

                                            <td className="px-4 py-3 font-medium whitespace-nowrap">
                                                {supplier.code}
                                            </td>

                                            <td className="px-4 py-3">
                                                <div className="max-w-[280px]">
                                                    <p className="truncate font-medium">
                                                        {supplier.contactName ??
                                                            '—'}
                                                    </p>

                                                    {supplier.email !==
                                                        null && (
                                                        <p className="mt-0.5 truncate text-xs text-muted-foreground">
                                                            {supplier.email}
                                                        </p>
                                                    )}

                                                    {supplier.phone !==
                                                        null && (
                                                        <p className="mt-0.5 truncate text-xs text-muted-foreground">
                                                            {supplier.phone}
                                                        </p>
                                                    )}
                                                </div>
                                            </td>

                                            <td className="px-4 py-3 text-right font-medium tabular-nums">
                                                {supplier.itemCount.toLocaleString()}
                                            </td>

                                            <td className="px-4 py-3">
                                                {supplier.lastPurchaseOrderNumber ===
                                                null ? (
                                                    <span className="text-muted-foreground">
                                                        —
                                                    </span>
                                                ) : (
                                                    <div>
                                                        <p className="font-medium whitespace-nowrap">
                                                            {
                                                                supplier.lastPurchaseOrderNumber
                                                            }
                                                        </p>

                                                        {supplier.lastPurchaseOrderDate !==
                                                            null && (
                                                            <p className="mt-0.5 text-xs whitespace-nowrap text-muted-foreground">
                                                                {formatDate(
                                                                    supplier.lastPurchaseOrderDate,
                                                                )}
                                                            </p>
                                                        )}
                                                    </div>
                                                )}
                                            </td>

                                            <td className="px-4 py-3">
                                                <Badge
                                                    variant="outline"
                                                    className={supplierStatusClassName(
                                                        supplier.active,
                                                    )}
                                                >
                                                    <span
                                                        className={
                                                            supplier.active
                                                                ? 'size-1.5 rounded-full bg-emerald-600 dark:bg-emerald-400'
                                                                : 'size-1.5 rounded-full bg-muted-foreground'
                                                        }
                                                        aria-hidden="true"
                                                    />
                                                    {supplier.active
                                                        ? 'Active'
                                                        : 'Inactive'}
                                                </Badge>
                                            </td>

                                            <td className="px-4 py-3 text-right">
                                                <Link
                                                    href={SupplierController.edit(
                                                        supplier.id,
                                                    )}
                                                    className="text-sm font-medium text-muted-foreground hover:text-foreground hover:underline focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                                >
                                                    {canManage
                                                        ? 'Edit'
                                                        : 'View'}
                                                </Link>
                                            </td>
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </div>

                    <div className="flex min-h-14 flex-wrap items-center justify-between gap-4 border-t border-sidebar-border/70 px-4 py-3 dark:border-sidebar-border">
                        <p className="text-sm text-muted-foreground">
                            {pagination.total === 0
                                ? 'Showing 0 suppliers'
                                : `Showing ${pagination.from ?? 0} to ${
                                      pagination.to ?? 0
                                  } of ${pagination.total.toLocaleString()} suppliers`}
                        </p>

                        {pagination.lastPage > 1 && (
                            <nav
                                className="flex items-center gap-2"
                                aria-label="Supplier pagination"
                            >
                                {pagination.previousPageUrl !== null ? (
                                    <Button
                                        variant="outline"
                                        className="h-9 px-3"
                                        asChild
                                    >
                                        <Link
                                            href={pagination.previousPageUrl}
                                            preserveScroll
                                            aria-label="Previous supplier page"
                                        >
                                            <ChevronLeft
                                                className="size-4"
                                                aria-hidden="true"
                                            />
                                        </Link>
                                    </Button>
                                ) : (
                                    <Button
                                        type="button"
                                        variant="outline"
                                        className="h-9 px-3"
                                        disabled
                                        aria-label="Previous supplier page"
                                    >
                                        <ChevronLeft
                                            className="size-4"
                                            aria-hidden="true"
                                        />
                                    </Button>
                                )}

                                {pagination.pages.map((page) =>
                                    page.active ? (
                                        <span
                                            key={page.page}
                                            className="flex h-9 min-w-9 items-center justify-center rounded-md bg-primary px-3 text-sm font-medium text-primary-foreground"
                                            aria-current="page"
                                        >
                                            {page.page}
                                        </span>
                                    ) : (
                                        <Button
                                            key={page.page}
                                            variant="outline"
                                            className="h-9 min-w-9 px-3"
                                            asChild
                                        >
                                            <Link
                                                href={page.url}
                                                preserveScroll
                                            >
                                                {page.page}
                                            </Link>
                                        </Button>
                                    ),
                                )}

                                {pagination.nextPageUrl !== null ? (
                                    <Button
                                        variant="outline"
                                        className="h-9 px-3"
                                        asChild
                                    >
                                        <Link
                                            href={pagination.nextPageUrl}
                                            preserveScroll
                                            aria-label="Next supplier page"
                                        >
                                            <ChevronRight
                                                className="size-4"
                                                aria-hidden="true"
                                            />
                                        </Link>
                                    </Button>
                                ) : (
                                    <Button
                                        type="button"
                                        variant="outline"
                                        className="h-9 px-3"
                                        disabled
                                        aria-label="Next supplier page"
                                    >
                                        <ChevronRight
                                            className="size-4"
                                            aria-hidden="true"
                                        />
                                    </Button>
                                )}
                            </nav>
                        )}
                    </div>
                </section>
            </div>
        </>
    );
}

SuppliersIndex.layout = {
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: dashboard(),
        },
        {
            title: 'Suppliers',
            href: SupplierController.index(),
        },
    ],
};
