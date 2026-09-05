import { Form, Head, Link } from '@inertiajs/react';
import {
    Building2,
    CheckCircle2,
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
import { EmptyState } from '@/components/empty-state';
import { FilterToolbar } from '@/components/filter-toolbar';
import { PageHeader } from '@/components/page-header';
import { PaginationControls } from '@/components/pagination-controls';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Field } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';
import { useGuardedDialog } from '@/hooks/use-guarded-dialog';
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

type CreateSupplierDialogProps = {
    trigger: React.ReactNode;
};

type EditSupplierDialogProps = {
    supplier: Supplier;
    trigger: React.ReactNode;
};

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

/** Create a supplier master record without leaving the directory context. */
function CreateSupplierDialog({ trigger }: CreateSupplierDialogProps) {
    const dialog = useGuardedDialog(
        'Discard the supplier details you entered?',
    );

    return (
        <Dialog open={dialog.open} onOpenChange={dialog.onOpenChange}>
            <DialogTrigger asChild>{trigger}</DialogTrigger>

            <DialogContent className="max-h-[calc(100vh-2rem)] overflow-y-auto sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle>New supplier</DialogTitle>
                    <DialogDescription>
                        Add a vendor to the active organization.
                    </DialogDescription>
                </DialogHeader>

                <div onChange={dialog.markDirty}>
                    <Form
                        {...SupplierController.store.form()}
                        errorBag="createSupplier"
                        className="space-y-5"
                        resetOnSuccess
                        onSuccess={dialog.closeAfterSuccess}
                    >
                        {({ processing, errors }) => (
                            <>
                                <input type="hidden" name="_modal" value="1" />
                                <input type="hidden" name="active" value="1" />

                                <div className="grid gap-5 sm:grid-cols-2">
                                    <Field
                                        id="create-supplier-name"
                                        label="Name"
                                        error={errors.name}
                                    >
                                        <Input
                                            name="name"
                                            required
                                            autoFocus
                                            placeholder="Metro Food Supply"
                                        />
                                    </Field>

                                    <Field
                                        id="create-supplier-code"
                                        label="Code"
                                        error={errors.code}
                                    >
                                        <Input
                                            name="code"
                                            required
                                            placeholder="METRO"
                                        />
                                    </Field>

                                    <Field
                                        id="create-supplier-contact-name"
                                        label="Contact name"
                                        error={errors.contact_name}
                                    >
                                        <Input name="contact_name" />
                                    </Field>

                                    <Field
                                        id="create-supplier-email"
                                        label="Email"
                                        error={errors.email}
                                    >
                                        <Input name="email" type="email" />
                                    </Field>

                                    <Field
                                        id="create-supplier-phone"
                                        label="Phone"
                                        error={errors.phone}
                                    >
                                        <Input name="phone" />
                                    </Field>

                                    <Field
                                        id="create-supplier-payment-terms"
                                        label="Payment terms"
                                        error={errors.payment_terms}
                                    >
                                        <Input
                                            name="payment_terms"
                                            placeholder="Net 30"
                                        />
                                    </Field>

                                    <Field
                                        id="create-supplier-lead-time-days"
                                        label="Lead time (days)"
                                        error={errors.lead_time_days}
                                    >
                                        <Input
                                            name="lead_time_days"
                                            type="number"
                                            min="0"
                                            step="1"
                                        />
                                    </Field>
                                </div>

                                <div className="flex flex-wrap justify-end gap-2">
                                    <Button
                                        type="button"
                                        variant="outline"
                                        disabled={processing}
                                        onClick={() =>
                                            dialog.onOpenChange(false)
                                        }
                                    >
                                        Cancel
                                    </Button>

                                    <Button type="submit" disabled={processing}>
                                        {processing
                                            ? 'Creating…'
                                            : 'Create supplier'}
                                    </Button>
                                </div>
                            </>
                        )}
                    </Form>
                </div>
            </DialogContent>
        </Dialog>
    );
}

/** Edit supplier master data without leaving the directory context. */
function EditSupplierDialog({ supplier, trigger }: EditSupplierDialogProps) {
    const dialog = useGuardedDialog(
        'Discard the supplier changes you entered?',
    );

    return (
        <Dialog open={dialog.open} onOpenChange={dialog.onOpenChange}>
            <DialogTrigger asChild>{trigger}</DialogTrigger>

            <DialogContent className="max-h-[calc(100vh-2rem)] overflow-y-auto sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle>Edit supplier</DialogTitle>
                    <DialogDescription>
                        Update {supplier.name} supplier master data.
                    </DialogDescription>
                </DialogHeader>

                <div onChange={dialog.markDirty}>
                    <Form
                        {...SupplierController.update.form(supplier.id)}
                        errorBag={`editSupplier${supplier.id}`}
                        className="space-y-5"
                        onSuccess={dialog.closeAfterSuccess}
                    >
                        {({ processing, errors }) => (
                            <>
                                <input type="hidden" name="_modal" value="1" />

                                <div className="grid gap-5 sm:grid-cols-2">
                                    <Field
                                        id={`edit-supplier-name-${supplier.id}`}
                                        label="Name"
                                        error={errors.name}
                                    >
                                        <Input
                                            name="name"
                                            required
                                            autoFocus
                                            defaultValue={supplier.name}
                                        />
                                    </Field>

                                    <Field
                                        id={`edit-supplier-code-${supplier.id}`}
                                        label="Code"
                                        error={errors.code}
                                    >
                                        <Input
                                            name="code"
                                            required
                                            defaultValue={supplier.code}
                                        />
                                    </Field>

                                    <Field
                                        id={`edit-supplier-contact-name-${supplier.id}`}
                                        label="Contact name"
                                        error={errors.contact_name}
                                    >
                                        <Input
                                            name="contact_name"
                                            defaultValue={
                                                supplier.contactName ?? ''
                                            }
                                        />
                                    </Field>

                                    <Field
                                        id={`edit-supplier-email-${supplier.id}`}
                                        label="Email"
                                        error={errors.email}
                                    >
                                        <Input
                                            name="email"
                                            type="email"
                                            defaultValue={supplier.email ?? ''}
                                        />
                                    </Field>

                                    <Field
                                        id={`edit-supplier-phone-${supplier.id}`}
                                        label="Phone"
                                        error={errors.phone}
                                    >
                                        <Input
                                            name="phone"
                                            defaultValue={supplier.phone ?? ''}
                                        />
                                    </Field>

                                    <Field
                                        id={`edit-supplier-payment-terms-${supplier.id}`}
                                        label="Payment terms"
                                        error={errors.payment_terms}
                                    >
                                        <Input
                                            name="payment_terms"
                                            defaultValue={
                                                supplier.paymentTerms ?? ''
                                            }
                                            placeholder="Net 30"
                                        />
                                    </Field>

                                    <Field
                                        id={`edit-supplier-lead-time-days-${supplier.id}`}
                                        label="Lead time (days)"
                                        error={errors.lead_time_days}
                                    >
                                        <Input
                                            name="lead_time_days"
                                            type="number"
                                            min="0"
                                            step="1"
                                            defaultValue={
                                                supplier.leadTimeDays ?? ''
                                            }
                                        />
                                    </Field>

                                    <Field
                                        id={`edit-supplier-active-${supplier.id}`}
                                        label="Status"
                                        error={errors.active}
                                    >
                                        <NativeSelect
                                            name="active"
                                            defaultValue={
                                                supplier.active ? '1' : '0'
                                            }
                                        >
                                            <option value="1">Active</option>
                                            <option value="0">Inactive</option>
                                        </NativeSelect>
                                    </Field>
                                </div>

                                <div className="flex flex-wrap justify-end gap-2">
                                    <Button
                                        type="button"
                                        variant="outline"
                                        disabled={processing}
                                        onClick={() =>
                                            dialog.onOpenChange(false)
                                        }
                                    >
                                        Cancel
                                    </Button>

                                    <Button type="submit" disabled={processing}>
                                        {processing
                                            ? 'Saving…'
                                            : 'Save supplier'}
                                    </Button>
                                </div>
                            </>
                        )}
                    </Form>
                </div>
            </DialogContent>
        </Dialog>
    );
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
                <PageHeader
                    title="Suppliers"
                    description="Manage vendors, purchase packs, and supplier pricing."
                    actions={
                        canManage && (
                            <CreateSupplierDialog
                                trigger={
                                    <Button type="button">
                                        <Plus
                                            className="size-4"
                                            aria-hidden="true"
                                        />
                                        New supplier
                                    </Button>
                                }
                            />
                        )
                    }
                />

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
                        <FilterToolbar>
                            <div className="grid gap-4 lg:grid-cols-2 xl:grid-cols-[minmax(280px,1.6fr)_minmax(150px,0.7fr)_minmax(190px,0.8fr)_110px_auto]">
                                <Field id="supplier-search" label="Search">
                                    <div className="relative">
                                        <Search
                                            className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                                            aria-hidden="true"
                                        />

                                        <Input
                                            type="search"
                                            name="search"
                                            defaultValue={filters.search ?? ''}
                                            placeholder="Search name, code, contact, email, or phone"
                                            className="pl-9"
                                            autoComplete="off"
                                        />
                                    </div>
                                </Field>

                                <Field id="supplier-status" label="Status">
                                    <NativeSelect
                                        name="status"
                                        defaultValue={filters.status ?? ''}
                                    >
                                        <option value="">All statuses</option>
                                        <option value="active">Active</option>
                                        <option value="inactive">
                                            Inactive
                                        </option>
                                    </NativeSelect>
                                </Field>

                                <Field id="supplier-sort" label="Sort by">
                                    <NativeSelect
                                        name="sort"
                                        defaultValue={filters.sort}
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
                                    </NativeSelect>
                                </Field>

                                <Field id="supplier-per-page" label="Rows">
                                    <NativeSelect
                                        name="per_page"
                                        defaultValue={filters.perPage.toString()}
                                    >
                                        <option value="10">10</option>
                                        <option value="25">25</option>
                                        <option value="50">50</option>
                                    </NativeSelect>
                                </Field>

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
                        </FilterToolbar>
                    )}
                </Form>

                <section
                    className="overflow-hidden rounded-xl border border-border bg-card shadow-sm"
                    aria-labelledby="supplier-results-title"
                >
                    <div className="flex min-h-14 flex-wrap items-center justify-between gap-3 border-b border-border px-4 py-3">
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

                    {suppliers.length === 0 ? (
                        <EmptyState
                            className="px-6 py-14"
                            icon={Search}
                            title={
                                hasFilters
                                    ? 'No suppliers match the current filters'
                                    : 'No suppliers have been created'
                            }
                            description={
                                hasFilters
                                    ? 'Adjust or clear the filters to view available suppliers.'
                                    : 'Create a supplier to begin configuring purchase packs and pricing.'
                            }
                        />
                    ) : (
                        <div
                            className="divide-y divide-border md:hidden"
                            data-testid="mobile-suppliers"
                        >
                            {suppliers.map((supplier) => (
                                <article
                                    key={supplier.id}
                                    className="space-y-3 p-4"
                                >
                                    <div className="flex items-start justify-between gap-3">
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
                                                {supplier.code} ·{' '}
                                                {supplierMetadata(supplier)}
                                            </p>
                                        </div>
                                        <StatusBadge
                                            label={
                                                supplier.active
                                                    ? 'Active'
                                                    : 'Inactive'
                                            }
                                            variant={
                                                supplier.active
                                                    ? 'success'
                                                    : 'neutral'
                                            }
                                        />
                                    </div>

                                    <dl className="grid grid-cols-2 gap-x-4 gap-y-3 text-sm">
                                        <div>
                                            <dt className="text-xs text-muted-foreground">
                                                Contact
                                            </dt>
                                            <dd className="mt-1">
                                                {supplier.contactName ?? '—'}
                                            </dd>
                                        </div>
                                        <div>
                                            <dt className="text-xs text-muted-foreground">
                                                Items
                                            </dt>
                                            <dd className="mt-1 tabular-nums">
                                                {supplier.itemCount.toLocaleString()}
                                            </dd>
                                        </div>
                                        <div>
                                            <dt className="text-xs text-muted-foreground">
                                                Last PO
                                            </dt>
                                            <dd className="mt-1">
                                                {supplier.lastPurchaseOrderNumber ??
                                                    '—'}
                                            </dd>
                                        </div>
                                    </dl>

                                    {canManage ? (
                                        <EditSupplierDialog
                                            supplier={supplier}
                                            trigger={
                                                <Button
                                                    variant="outline"
                                                    size="sm"
                                                    className="w-full"
                                                >
                                                    Edit
                                                </Button>
                                            }
                                        />
                                    ) : (
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            className="w-full"
                                            asChild
                                        >
                                            <Link
                                                href={SupplierController.edit(
                                                    supplier.id,
                                                )}
                                            >
                                                View
                                            </Link>
                                        </Button>
                                    )}
                                </article>
                            ))}
                        </div>
                    )}

                    <div className="hidden overflow-x-auto md:block">
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
                                {suppliers.map((supplier) => (
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
                                                    {supplierMetadata(supplier)}
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

                                                {supplier.email !== null && (
                                                    <p className="mt-0.5 truncate text-xs text-muted-foreground">
                                                        {supplier.email}
                                                    </p>
                                                )}

                                                {supplier.phone !== null && (
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
                                            <StatusBadge
                                                label={
                                                    supplier.active
                                                        ? 'Active'
                                                        : 'Inactive'
                                                }
                                                variant={
                                                    supplier.active
                                                        ? 'success'
                                                        : 'neutral'
                                                }
                                            />
                                        </td>

                                        <td className="px-4 py-3 text-right">
                                            {canManage ? (
                                                <EditSupplierDialog
                                                    supplier={supplier}
                                                    trigger={
                                                        <button
                                                            type="button"
                                                            className="text-sm font-medium text-muted-foreground hover:text-foreground hover:underline focus-visible:rounded-sm focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                                        >
                                                            Edit
                                                        </button>
                                                    }
                                                />
                                            ) : (
                                                <Link
                                                    href={SupplierController.edit(
                                                        supplier.id,
                                                    )}
                                                    className="text-sm font-medium text-muted-foreground hover:text-foreground hover:underline focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                                >
                                                    View
                                                </Link>
                                            )}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>

                    <PaginationControls
                        currentPage={pagination.currentPage}
                        lastPage={pagination.lastPage}
                        from={pagination.from}
                        to={pagination.to}
                        total={pagination.total}
                        previousPageUrl={pagination.previousPageUrl}
                        nextPageUrl={pagination.nextPageUrl}
                        preserveScroll
                        preserveState
                        itemLabel="suppliers"
                    />
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
