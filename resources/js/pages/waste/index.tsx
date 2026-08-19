import { Form, Head, Link } from '@inertiajs/react';
import {
    CheckCircle2,
    ChevronLeft,
    ChevronRight,
    CircleDollarSign,
    ClipboardList,
    Download,
    Filter,
    Info,
    Package,
    Plus,
    RotateCcw,
    Search,
    Star,
    Tags,
} from 'lucide-react';
import type { ReactNode } from 'react';
import { useState } from 'react';

import WasteController from '@/actions/App/Http/Controllers/Inventory/WasteController';
import WasteReasonController from '@/actions/App/Http/Controllers/Inventory/WasteReasonController';
import { DashboardMetricCard } from '@/components/dashboard/dashboard-metric-card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { dashboard } from '@/routes';

type Option = {
    id: number;
    name: string;
};

type StorageOption = Option & {
    locationId: number;
};

type ItemOption = Option & {
    sku: string;
    baseUnitSymbol: string;
    validUnitIds: number[];
};

type ReportItemOption = Option & {
    sku: string;
};

type UnitOption = Option & {
    symbol: string;
};

type WasteReason = Option & {
    active: boolean;
};

type RecordForm = {
    operationId: string;
    defaultOccurredAt: string;
    locationOptions: Option[];
    storageLocationOptions: StorageOption[];
    inventoryItemOptions: ItemOption[];
    unitOptions: UnitOption[];
    reasonOptions: Option[];
};

type ReportOptions = {
    locations: Option[];
    inventoryCategories: Option[];
    inventoryItems: ReportItemOption[];
    wasteReasons: Option[];
};

type QuantityTotal = {
    baseUnitId: number;
    quantity: string;
    unitSymbol: string;
};

type WasteReport = {
    summary: {
        recordCount: number;
        quantityTotals: QuantityTotal[];
        totalCost: string | null;
    };
    byReason: {
        reasonId: number;
        reasonName: string;
        recordCount: number;
        quantityTotals: QuantityTotal[];
        totalCost: string | null;
    }[];
    byEmployee: {
        employeeId: number | null;
        employeeName: string;
        recordCount: number;
        quantityTotals: QuantityTotal[];
        totalCost: string | null;
    }[];
    byItem: {
        itemId: number;
        itemName: string;
        itemSku: string;
        baseUnitId: number;
        baseUnitSymbol: string;
        recordCount: number;
        totalQuantity: string;
        totalCost: string | null;
    }[];
    byLocation: {
        locationId: number;
        locationName: string;
        recordCount: number;
        quantityTotals: QuantityTotal[];
        totalCost: string | null;
    }[];
};

type WasteRow = {
    recordId: number;
    occurredAt: string;
    locationName: string;
    storageLocationName: string;
    itemName: string;
    itemSku: string;
    reasonName: string;
    quantity: string;
    unitSymbol: string;
    baseQuantity: string;
    baseUnitSymbol: string;
    unitCost: string | null;
    totalCost: string | null;
    recordedBy: string | null;
    notes: string | null;
    movementId: number | null;
};

type PaginatedWasteRows = {
    current_page: number;
    data: WasteRow[];
    from: number | null;
    last_page: number;
    next_page_url: string | null;
    prev_page_url: string | null;
    to: number | null;
    total: number;
};

type Props = {
    rows: PaginatedWasteRows | null;
    report: WasteReport | null;
    filters: {
        locationId: number | null;
        inventoryCategoryId: number | null;
        inventoryItemId: number | null;
        wasteReasonId: number | null;
        from: string | null;
        to: string | null;
    };
    currency: string;
    canRecord: boolean;
    canManageReasons: boolean;
    canViewReport: boolean;
    canViewCosts: boolean;
    wasteReasons: WasteReason[];
    recordForm: RecordForm | null;
    reportOptions: ReportOptions | null;
};

const selectClassName =
    'h-9 w-full rounded-md border border-input bg-background px-3 text-sm shadow-xs outline-none transition-[color,box-shadow] focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 aria-invalid:border-destructive aria-invalid:ring-[3px] aria-invalid:ring-destructive/20 disabled:cursor-not-allowed disabled:opacity-50';

const textareaClassName =
    'min-h-24 w-full resize-y rounded-md border border-input bg-background px-3 py-2 text-sm shadow-xs outline-none transition-[color,box-shadow] placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 aria-invalid:border-destructive aria-invalid:ring-[3px] aria-invalid:ring-destructive/20 disabled:cursor-not-allowed disabled:opacity-50';

/**
 * Format persisted decimal strings without converting inventory values to
 * floating-point numbers.
 */
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

/**
 * Format one persisted monetary value while preserving the server-supplied
 * decimal representation.
 */
function formatCurrency(value: string, currency: string): string {
    return `${currency} ${formatDecimal(value)}`;
}

/**
 * Preserve the current browser-local rendering used by the existing Waste
 * evidence table.
 */
function formatDate(value: string): string {
    return new Date(value).toLocaleString();
}

/**
 * Keep CSV export filters synchronized with the currently applied Waste
 * report filters.
 */
function buildExportUrl(filters: Props['filters']): string {
    const params = new URLSearchParams();

    if (filters.locationId !== null) {
        params.set('location_id', filters.locationId.toString());
    }

    if (filters.inventoryCategoryId !== null) {
        params.set(
            'inventory_category_id',
            filters.inventoryCategoryId.toString(),
        );
    }

    if (filters.inventoryItemId !== null) {
        params.set('inventory_item_id', filters.inventoryItemId.toString());
    }

    if (filters.wasteReasonId !== null) {
        params.set('waste_reason_id', filters.wasteReasonId.toString());
    }

    if (filters.from !== null) {
        params.set('from', filters.from);
    }

    if (filters.to !== null) {
        params.set('to', filters.to);
    }

    const baseUrl = WasteController.export().url;
    const query = params.toString();

    return query === '' ? baseUrl : `${baseUrl}?${query}`;
}

/**
 * Render required field labels with a visible and screen-reader-friendly
 * required indicator.
 */
function RequiredLabel({
    htmlFor,
    children,
}: {
    htmlFor: string;
    children: string;
}) {
    return (
        <Label htmlFor={htmlFor}>
            {children}
            <span className="ml-0.5 text-destructive" aria-hidden="true">
                *
            </span>
            <span className="sr-only"> required</span>
        </Label>
    );
}

/**
 * Render validation feedback consistently below operational form fields.
 */
function ErrorText({ message }: { message?: string }) {
    return message ? (
        <p className="text-sm text-destructive" role="alert">
            {message}
        </p>
    ) : null;
}

/**
 * Render quantity aggregates without combining unlike inventory base units.
 */
function QuantityTotals({
    totals,
    align = 'left',
}: {
    totals: QuantityTotal[];
    align?: 'left' | 'right';
}) {
    if (totals.length === 0) {
        return <span>—</span>;
    }

    return (
        <div
            className={
                align === 'right'
                    ? 'grid justify-items-end gap-0.5 text-right tabular-nums'
                    : 'grid gap-0.5 tabular-nums'
            }
        >
            {totals.map((total) => (
                <div key={total.baseUnitId} className="whitespace-nowrap">
                    {formatDecimal(total.quantity)} {total.unitSymbol}
                </div>
            ))}
        </div>
    );
}

/**
 * Provide one compact, reusable container for each Waste aggregate table.
 */
function BreakdownCard({
    id,
    title,
    description,
    children,
}: {
    id: string;
    title: string;
    description: string;
    children: ReactNode;
}) {
    return (
        <section
            className="overflow-hidden rounded-xl border border-sidebar-border/70 bg-card shadow-sm dark:border-sidebar-border"
            aria-labelledby={id}
        >
            <div className="border-b border-sidebar-border/70 px-4 py-3 dark:border-sidebar-border">
                <h3 id={id} className="text-sm font-semibold">
                    {title}
                </h3>
                <p className="mt-0.5 text-xs text-muted-foreground">
                    {description}
                </p>
            </div>

            <div className="overflow-x-auto">{children}</div>
        </section>
    );
}

/**
 * Render the Waste operational workspace and immutable reporting evidence.
 */
export default function WasteIndex({
    rows,
    report,
    filters,
    currency,
    canRecord,
    canManageReasons,
    canViewReport,
    canViewCosts,
    wasteReasons,
    recordForm,
    reportOptions,
}: Props) {
    const [recordLocationId, setRecordLocationId] = useState('');
    const [recordStorageLocationId, setRecordStorageLocationId] = useState('');
    const [recordInventoryItemId, setRecordInventoryItemId] = useState('');
    const [recordUnitId, setRecordUnitId] = useState('');

    const reportRows = rows?.data ?? [];
    const showRecordForm = canRecord && recordForm !== null;
    const exportUrl = buildExportUrl(filters);

    const selectedStorageLocationOptions =
        recordForm?.storageLocationOptions.filter(
            (storageLocation) =>
                storageLocation.locationId.toString() === recordLocationId,
        ) ?? [];

    const selectedInventoryItem = recordForm?.inventoryItemOptions.find(
        (item) => item.id.toString() === recordInventoryItemId,
    );

    const selectedUnitOptions =
        recordForm?.unitOptions.filter((unit) =>
            selectedInventoryItem?.validUnitIds.includes(unit.id),
        ) ?? [];

    const topReason =
        report?.byReason.reduce<WasteReport['byReason'][number] | null>(
            (current, row) =>
                current === null || row.recordCount > current.recordCount
                    ? row
                    : current,
            null,
        ) ?? null;

    const topReasonPercentage =
        topReason !== null && report !== null && report.summary.recordCount > 0
            ? Math.round(
                  (topReason.recordCount / report.summary.recordCount) * 100,
              )
            : null;

    const handleRecordLocationChange = (value: string) => {
        setRecordLocationId(value);
        setRecordStorageLocationId('');
    };

    const handleRecordInventoryItemChange = (value: string) => {
        setRecordInventoryItemId(value);
        setRecordUnitId('');
    };

    return (
        <>
            <Head title="Waste" />

            <div className="flex flex-1 flex-col gap-6 p-4 sm:p-6">
                <div>
                    <h1 className="text-2xl font-semibold tracking-tight">
                        Waste
                    </h1>
                    <p className="mt-1 text-sm text-muted-foreground">
                        Record known operational stock loss separately from
                        physical-count variance.
                    </p>
                </div>

                {(showRecordForm || canManageReasons) && (
                    <div
                        className={
                            showRecordForm
                                ? 'grid gap-4 xl:grid-cols-[minmax(0,2fr)_minmax(20rem,1fr)]'
                                : 'grid gap-4'
                        }
                    >
                        {showRecordForm && (
                            <section
                                className="overflow-hidden rounded-xl border border-sidebar-border/70 bg-card shadow-sm dark:border-sidebar-border"
                                aria-labelledby="record-waste-title"
                            >
                                <div className="flex items-start gap-3 border-b border-sidebar-border/70 px-5 py-4 dark:border-sidebar-border">
                                    <div className="flex size-9 shrink-0 items-center justify-center rounded-lg bg-muted">
                                        <ClipboardList
                                            className="size-4 text-muted-foreground"
                                            aria-hidden="true"
                                        />
                                    </div>

                                    <div>
                                        <h2
                                            id="record-waste-title"
                                            className="font-semibold"
                                        >
                                            Record waste
                                        </h2>
                                        <p className="mt-0.5 text-xs text-muted-foreground">
                                            Recording waste immediately
                                            decreases stock.
                                        </p>
                                    </div>
                                </div>

                                <Form
                                    action={WasteController.store().url}
                                    method="post"
                                >
                                    {({ errors, processing }) => (
                                        <div className="grid gap-5 p-5">
                                            <input
                                                type="hidden"
                                                name="operation_id"
                                                value={recordForm.operationId}
                                            />

                                            <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                                                <div className="grid gap-2">
                                                    <RequiredLabel htmlFor="location_id">
                                                        Location
                                                    </RequiredLabel>

                                                    <select
                                                        id="location_id"
                                                        name="location_id"
                                                        value={recordLocationId}
                                                        onChange={(event) =>
                                                            handleRecordLocationChange(
                                                                event.target
                                                                    .value,
                                                            )
                                                        }
                                                        aria-invalid={
                                                            errors.location_id
                                                                ? true
                                                                : undefined
                                                        }
                                                        className={
                                                            selectClassName
                                                        }
                                                        required
                                                    >
                                                        <option value="">
                                                            Select location
                                                        </option>

                                                        {recordForm.locationOptions.map(
                                                            (option) => (
                                                                <option
                                                                    key={
                                                                        option.id
                                                                    }
                                                                    value={
                                                                        option.id
                                                                    }
                                                                >
                                                                    {
                                                                        option.name
                                                                    }
                                                                </option>
                                                            ),
                                                        )}
                                                    </select>

                                                    <ErrorText
                                                        message={
                                                            errors.location_id
                                                        }
                                                    />
                                                </div>

                                                <div className="grid gap-2">
                                                    <RequiredLabel htmlFor="storage_location_id">
                                                        Storage location
                                                    </RequiredLabel>

                                                    <select
                                                        id="storage_location_id"
                                                        name="storage_location_id"
                                                        value={
                                                            recordStorageLocationId
                                                        }
                                                        onChange={(event) =>
                                                            setRecordStorageLocationId(
                                                                event.target
                                                                    .value,
                                                            )
                                                        }
                                                        disabled={
                                                            recordLocationId ===
                                                            ''
                                                        }
                                                        aria-invalid={
                                                            errors.storage_location_id
                                                                ? true
                                                                : undefined
                                                        }
                                                        className={
                                                            selectClassName
                                                        }
                                                        required
                                                    >
                                                        <option value="">
                                                            {recordLocationId ===
                                                            ''
                                                                ? 'Select location first'
                                                                : 'Select storage location'}
                                                        </option>

                                                        {selectedStorageLocationOptions.map(
                                                            (option) => (
                                                                <option
                                                                    key={
                                                                        option.id
                                                                    }
                                                                    value={
                                                                        option.id
                                                                    }
                                                                >
                                                                    {
                                                                        option.name
                                                                    }
                                                                </option>
                                                            ),
                                                        )}
                                                    </select>

                                                    <ErrorText
                                                        message={
                                                            errors.storage_location_id
                                                        }
                                                    />
                                                </div>

                                                <div className="grid gap-2">
                                                    <RequiredLabel htmlFor="inventory_item_id">
                                                        Inventory item
                                                    </RequiredLabel>

                                                    <select
                                                        id="inventory_item_id"
                                                        name="inventory_item_id"
                                                        value={
                                                            recordInventoryItemId
                                                        }
                                                        onChange={(event) =>
                                                            handleRecordInventoryItemChange(
                                                                event.target
                                                                    .value,
                                                            )
                                                        }
                                                        aria-invalid={
                                                            errors.inventory_item_id
                                                                ? true
                                                                : undefined
                                                        }
                                                        className={
                                                            selectClassName
                                                        }
                                                        required
                                                    >
                                                        <option value="">
                                                            Select item
                                                        </option>

                                                        {recordForm.inventoryItemOptions.map(
                                                            (option) => (
                                                                <option
                                                                    key={
                                                                        option.id
                                                                    }
                                                                    value={
                                                                        option.id
                                                                    }
                                                                >
                                                                    {
                                                                        option.name
                                                                    }{' '}
                                                                    (
                                                                    {option.sku}
                                                                    )
                                                                </option>
                                                            ),
                                                        )}
                                                    </select>

                                                    <ErrorText
                                                        message={
                                                            errors.inventory_item_id
                                                        }
                                                    />
                                                </div>

                                                <div className="grid gap-2">
                                                    <RequiredLabel htmlFor="unit_id">
                                                        Unit
                                                    </RequiredLabel>

                                                    <select
                                                        id="unit_id"
                                                        name="unit_id"
                                                        value={recordUnitId}
                                                        onChange={(event) =>
                                                            setRecordUnitId(
                                                                event.target
                                                                    .value,
                                                            )
                                                        }
                                                        disabled={
                                                            recordInventoryItemId ===
                                                            ''
                                                        }
                                                        aria-invalid={
                                                            (errors.unit_id ??
                                                            errors.unit)
                                                                ? true
                                                                : undefined
                                                        }
                                                        className={
                                                            selectClassName
                                                        }
                                                        required
                                                    >
                                                        <option value="">
                                                            {recordInventoryItemId ===
                                                            ''
                                                                ? 'Select item first'
                                                                : 'Select unit'}
                                                        </option>

                                                        {selectedUnitOptions.map(
                                                            (option) => (
                                                                <option
                                                                    key={
                                                                        option.id
                                                                    }
                                                                    value={
                                                                        option.id
                                                                    }
                                                                >
                                                                    {
                                                                        option.name
                                                                    }{' '}
                                                                    (
                                                                    {
                                                                        option.symbol
                                                                    }
                                                                    )
                                                                </option>
                                                            ),
                                                        )}
                                                    </select>

                                                    <ErrorText
                                                        message={
                                                            errors.unit_id ??
                                                            errors.unit
                                                        }
                                                    />
                                                </div>

                                                <div className="grid gap-2">
                                                    <RequiredLabel htmlFor="waste_reason_id">
                                                        Reason
                                                    </RequiredLabel>

                                                    <select
                                                        id="waste_reason_id"
                                                        name="waste_reason_id"
                                                        aria-invalid={
                                                            errors.waste_reason_id
                                                                ? true
                                                                : undefined
                                                        }
                                                        className={
                                                            selectClassName
                                                        }
                                                        required
                                                    >
                                                        <option value="">
                                                            Select reason
                                                        </option>

                                                        {recordForm.reasonOptions.map(
                                                            (option) => (
                                                                <option
                                                                    key={
                                                                        option.id
                                                                    }
                                                                    value={
                                                                        option.id
                                                                    }
                                                                >
                                                                    {
                                                                        option.name
                                                                    }
                                                                </option>
                                                            ),
                                                        )}
                                                    </select>

                                                    <ErrorText
                                                        message={
                                                            errors.waste_reason_id
                                                        }
                                                    />
                                                </div>

                                                <div className="grid gap-2">
                                                    <RequiredLabel htmlFor="quantity">
                                                        Quantity
                                                    </RequiredLabel>

                                                    <Input
                                                        id="quantity"
                                                        name="quantity"
                                                        type="number"
                                                        inputMode="decimal"
                                                        min="0.000001"
                                                        step="0.000001"
                                                        placeholder="e.g. 2.5"
                                                        aria-invalid={
                                                            errors.quantity
                                                                ? true
                                                                : undefined
                                                        }
                                                        required
                                                    />

                                                    <ErrorText
                                                        message={
                                                            errors.quantity
                                                        }
                                                    />
                                                </div>

                                                <div className="grid gap-2">
                                                    <RequiredLabel htmlFor="occurred_at">
                                                        Occurred at
                                                    </RequiredLabel>

                                                    <Input
                                                        id="occurred_at"
                                                        name="occurred_at"
                                                        type="datetime-local"
                                                        defaultValue={
                                                            recordForm.defaultOccurredAt
                                                        }
                                                        aria-invalid={
                                                            errors.occurred_at
                                                                ? true
                                                                : undefined
                                                        }
                                                        required
                                                    />

                                                    <ErrorText
                                                        message={
                                                            errors.occurred_at
                                                        }
                                                    />
                                                </div>
                                            </div>

                                            <div className="grid gap-2">
                                                <Label htmlFor="notes">
                                                    Notes
                                                </Label>

                                                <textarea
                                                    id="notes"
                                                    name="notes"
                                                    rows={3}
                                                    maxLength={2000}
                                                    placeholder="Add any additional details (optional)"
                                                    aria-invalid={
                                                        errors.notes
                                                            ? true
                                                            : undefined
                                                    }
                                                    className={
                                                        textareaClassName
                                                    }
                                                />

                                                <div className="flex flex-wrap items-start justify-between gap-2">
                                                    <ErrorText
                                                        message={errors.notes}
                                                    />

                                                    <span className="ml-auto text-xs text-muted-foreground">
                                                        Up to 2,000 characters
                                                    </span>
                                                </div>

                                                <ErrorText
                                                    message={
                                                        errors.operation_id
                                                    }
                                                />
                                            </div>

                                            {recordForm.reasonOptions.length ===
                                                0 && (
                                                <div
                                                    className="rounded-md border border-destructive/30 bg-destructive/10 px-3 py-2 text-sm text-destructive"
                                                    role="alert"
                                                >
                                                    Create an active waste
                                                    reason before recording
                                                    waste.
                                                </div>
                                            )}

                                            <div>
                                                <Button
                                                    type="submit"
                                                    disabled={
                                                        processing ||
                                                        recordForm.reasonOptions
                                                            .length === 0
                                                    }
                                                >
                                                    <ClipboardList
                                                        className="size-4"
                                                        aria-hidden="true"
                                                    />
                                                    {processing
                                                        ? 'Recording…'
                                                        : 'Record waste'}
                                                </Button>
                                            </div>
                                        </div>
                                    )}
                                </Form>
                            </section>
                        )}

                        <div className="grid content-start gap-4">
                            {canManageReasons && (
                                <section
                                    className="overflow-hidden rounded-xl border border-sidebar-border/70 bg-card shadow-sm dark:border-sidebar-border"
                                    aria-labelledby="waste-reasons-title"
                                >
                                    <div className="border-b border-sidebar-border/70 px-4 py-3 dark:border-sidebar-border">
                                        <h2
                                            id="waste-reasons-title"
                                            className="font-semibold"
                                        >
                                            Waste reasons
                                        </h2>
                                        <p className="mt-0.5 text-xs text-muted-foreground">
                                            Manage suggested reasons used when
                                            recording waste.
                                        </p>
                                    </div>

                                    <div className="p-4">
                                        <Form
                                            action={
                                                WasteReasonController.store()
                                                    .url
                                            }
                                            method="post"
                                        >
                                            {({ errors, processing }) => (
                                                <div className="grid gap-2">
                                                    <div className="flex items-start gap-2">
                                                        <div className="min-w-0 flex-1">
                                                            <Label
                                                                htmlFor="new_waste_reason_name"
                                                                className="sr-only"
                                                            >
                                                                New waste reason
                                                            </Label>

                                                            <Input
                                                                id="new_waste_reason_name"
                                                                name="name"
                                                                placeholder="New reason (e.g. Spoilage)"
                                                                maxLength={100}
                                                                aria-invalid={
                                                                    errors.name
                                                                        ? true
                                                                        : undefined
                                                                }
                                                                required
                                                            />
                                                        </div>

                                                        <Button
                                                            type="submit"
                                                            disabled={
                                                                processing
                                                            }
                                                        >
                                                            <Plus
                                                                className="size-4"
                                                                aria-hidden="true"
                                                            />
                                                            {processing
                                                                ? 'Adding…'
                                                                : 'Add reason'}
                                                        </Button>
                                                    </div>

                                                    <ErrorText
                                                        message={errors.name}
                                                    />
                                                </div>
                                            )}
                                        </Form>
                                    </div>

                                    <div className="overflow-x-auto border-t border-sidebar-border/70 dark:border-sidebar-border">
                                        <table className="w-full min-w-[440px] text-sm">
                                            <caption className="sr-only">
                                                Configured waste reasons and
                                                their active status.
                                            </caption>

                                            <thead className="border-b bg-muted/40 text-left">
                                                <tr>
                                                    <th
                                                        scope="col"
                                                        className="px-4 py-2.5 font-medium text-muted-foreground"
                                                    >
                                                        Reason
                                                    </th>
                                                    <th
                                                        scope="col"
                                                        className="px-4 py-2.5 font-medium text-muted-foreground"
                                                    >
                                                        Status
                                                    </th>
                                                    <th
                                                        scope="col"
                                                        className="px-4 py-2.5 text-right font-medium text-muted-foreground"
                                                    >
                                                        Actions
                                                    </th>
                                                </tr>
                                            </thead>

                                            <tbody>
                                                {wasteReasons.length === 0 ? (
                                                    <tr>
                                                        <td
                                                            colSpan={3}
                                                            className="px-4 py-8 text-center text-muted-foreground"
                                                        >
                                                            No waste reasons
                                                            configured.
                                                        </td>
                                                    </tr>
                                                ) : (
                                                    wasteReasons.map(
                                                        (reason) => (
                                                            <tr
                                                                key={reason.id}
                                                                className="border-b border-sidebar-border/70 last:border-b-0 dark:border-sidebar-border"
                                                            >
                                                                <td className="px-4 py-2.5 font-medium">
                                                                    {
                                                                        reason.name
                                                                    }
                                                                </td>

                                                                <td className="px-4 py-2.5">
                                                                    {reason.active ? (
                                                                        <Badge
                                                                            variant="outline"
                                                                            className="border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-300"
                                                                        >
                                                                            <CheckCircle2 aria-hidden="true" />
                                                                            Active
                                                                        </Badge>
                                                                    ) : (
                                                                        <Badge
                                                                            variant="secondary"
                                                                            className="text-muted-foreground"
                                                                        >
                                                                            Inactive
                                                                        </Badge>
                                                                    )}
                                                                </td>

                                                                <td className="px-4 py-2 text-right">
                                                                    <Form
                                                                        action={
                                                                            WasteReasonController.update(
                                                                                reason.id,
                                                                            )
                                                                                .url
                                                                        }
                                                                        method="put"
                                                                    >
                                                                        {({
                                                                            processing,
                                                                        }) => (
                                                                            <>
                                                                                <input
                                                                                    type="hidden"
                                                                                    name="active"
                                                                                    value={
                                                                                        reason.active
                                                                                            ? '0'
                                                                                            : '1'
                                                                                    }
                                                                                />

                                                                                <Button
                                                                                    type="submit"
                                                                                    variant="ghost"
                                                                                    size="sm"
                                                                                    disabled={
                                                                                        processing
                                                                                    }
                                                                                >
                                                                                    {reason.active
                                                                                        ? 'Deactivate'
                                                                                        : 'Activate'}
                                                                                </Button>
                                                                            </>
                                                                        )}
                                                                    </Form>
                                                                </td>
                                                            </tr>
                                                        ),
                                                    )
                                                )}
                                            </tbody>
                                        </table>
                                    </div>
                                </section>
                            )}

                            {showRecordForm && (
                                <aside
                                    className="rounded-xl border border-blue-200 bg-blue-50/50 p-4 shadow-sm dark:border-blue-900 dark:bg-blue-950/20"
                                    aria-labelledby="inventory-impact-title"
                                >
                                    <div className="flex items-start gap-3">
                                        <Info
                                            className="mt-0.5 size-4 shrink-0 text-blue-600 dark:text-blue-400"
                                            aria-hidden="true"
                                        />

                                        <div>
                                            <h2
                                                id="inventory-impact-title"
                                                className="text-sm font-semibold"
                                            >
                                                Inventory impact
                                            </h2>

                                            <ul className="mt-2 grid gap-2 text-xs text-muted-foreground">
                                                <li className="flex items-start gap-2">
                                                    <CheckCircle2
                                                        className="mt-0.5 size-3.5 shrink-0 text-emerald-600 dark:text-emerald-400"
                                                        aria-hidden="true"
                                                    />
                                                    Waste decreases stock on
                                                    hand immediately.
                                                </li>

                                                <li className="flex items-start gap-2">
                                                    <CheckCircle2
                                                        className="mt-0.5 size-3.5 shrink-0 text-emerald-600 dark:text-emerald-400"
                                                        aria-hidden="true"
                                                    />
                                                    Waste evidence and its stock
                                                    movement remain traceable.
                                                </li>

                                                <li className="flex items-start gap-2">
                                                    <CheckCircle2
                                                        className="mt-0.5 size-3.5 shrink-0 text-emerald-600 dark:text-emerald-400"
                                                        aria-hidden="true"
                                                    />
                                                    Waste contributes to reports
                                                    and current inventory value.
                                                </li>
                                            </ul>
                                        </div>
                                    </div>
                                </aside>
                            )}
                        </div>
                    </div>
                )}

                {canViewReport && reportOptions !== null && report !== null && (
                    <section
                        className="grid gap-4"
                        aria-labelledby="waste-report-title"
                    >
                        <div>
                            <h2
                                id="waste-report-title"
                                className="text-lg font-semibold"
                            >
                                Waste report
                            </h2>

                            <p className="mt-0.5 text-sm text-muted-foreground">
                                Summarize and analyze finalized waste by period,
                                location, category, item, reason, and employee.
                            </p>
                        </div>

                        <Form action={WasteController.index().url} method="get">
                            {({ errors, processing }) => (
                                <div className="overflow-hidden rounded-xl border border-sidebar-border/70 bg-card shadow-sm dark:border-sidebar-border">
                                    <div className="flex items-center gap-2 border-b border-sidebar-border/70 px-4 py-3 dark:border-sidebar-border">
                                        <Filter
                                            className="size-4 text-muted-foreground"
                                            aria-hidden="true"
                                        />
                                        <span className="text-sm font-semibold">
                                            Report filters
                                        </span>
                                    </div>

                                    <div className="grid gap-4 p-4 md:grid-cols-2 xl:grid-cols-7">
                                        <div className="grid gap-2">
                                            <Label htmlFor="report_location_id">
                                                Location
                                            </Label>

                                            <select
                                                id="report_location_id"
                                                name="location_id"
                                                defaultValue={
                                                    filters.locationId?.toString() ??
                                                    ''
                                                }
                                                aria-invalid={
                                                    errors.location_id
                                                        ? true
                                                        : undefined
                                                }
                                                className={selectClassName}
                                            >
                                                <option value="">
                                                    All locations
                                                </option>

                                                {reportOptions.locations.map(
                                                    (option) => (
                                                        <option
                                                            key={option.id}
                                                            value={option.id}
                                                        >
                                                            {option.name}
                                                        </option>
                                                    ),
                                                )}
                                            </select>
                                        </div>

                                        <div className="grid gap-2">
                                            <Label htmlFor="report_inventory_category_id">
                                                Category
                                            </Label>

                                            <select
                                                id="report_inventory_category_id"
                                                name="inventory_category_id"
                                                defaultValue={
                                                    filters.inventoryCategoryId?.toString() ??
                                                    ''
                                                }
                                                aria-invalid={
                                                    errors.inventory_category_id
                                                        ? true
                                                        : undefined
                                                }
                                                className={selectClassName}
                                            >
                                                <option value="">
                                                    All categories
                                                </option>

                                                {reportOptions.inventoryCategories.map(
                                                    (option) => (
                                                        <option
                                                            key={option.id}
                                                            value={option.id}
                                                        >
                                                            {option.name}
                                                        </option>
                                                    ),
                                                )}
                                            </select>
                                        </div>

                                        <div className="grid gap-2">
                                            <Label htmlFor="report_inventory_item_id">
                                                Item
                                            </Label>

                                            <select
                                                id="report_inventory_item_id"
                                                name="inventory_item_id"
                                                defaultValue={
                                                    filters.inventoryItemId?.toString() ??
                                                    ''
                                                }
                                                aria-invalid={
                                                    errors.inventory_item_id
                                                        ? true
                                                        : undefined
                                                }
                                                className={selectClassName}
                                            >
                                                <option value="">
                                                    All items
                                                </option>

                                                {reportOptions.inventoryItems.map(
                                                    (option) => (
                                                        <option
                                                            key={option.id}
                                                            value={option.id}
                                                        >
                                                            {option.name}
                                                        </option>
                                                    ),
                                                )}
                                            </select>
                                        </div>

                                        <div className="grid gap-2">
                                            <Label htmlFor="report_waste_reason_id">
                                                Reason
                                            </Label>

                                            <select
                                                id="report_waste_reason_id"
                                                name="waste_reason_id"
                                                defaultValue={
                                                    filters.wasteReasonId?.toString() ??
                                                    ''
                                                }
                                                aria-invalid={
                                                    errors.waste_reason_id
                                                        ? true
                                                        : undefined
                                                }
                                                className={selectClassName}
                                            >
                                                <option value="">
                                                    All reasons
                                                </option>

                                                {reportOptions.wasteReasons.map(
                                                    (option) => (
                                                        <option
                                                            key={option.id}
                                                            value={option.id}
                                                        >
                                                            {option.name}
                                                        </option>
                                                    ),
                                                )}
                                            </select>
                                        </div>

                                        <div className="grid gap-2">
                                            <Label htmlFor="report_from">
                                                From
                                            </Label>

                                            <Input
                                                id="report_from"
                                                name="from"
                                                type="date"
                                                defaultValue={
                                                    filters.from ?? ''
                                                }
                                                aria-invalid={
                                                    errors.from
                                                        ? true
                                                        : undefined
                                                }
                                            />
                                        </div>

                                        <div className="grid gap-2">
                                            <Label htmlFor="report_to">
                                                To
                                            </Label>

                                            <Input
                                                id="report_to"
                                                name="to"
                                                type="date"
                                                defaultValue={filters.to ?? ''}
                                                aria-invalid={
                                                    errors.to ? true : undefined
                                                }
                                            />
                                        </div>

                                        <div className="flex flex-wrap items-end gap-2 md:col-span-2 xl:col-span-1">
                                            <Button
                                                type="submit"
                                                disabled={processing}
                                                className="min-w-20 flex-1 xl:flex-none"
                                            >
                                                <Filter
                                                    className="size-4"
                                                    aria-hidden="true"
                                                />
                                                {processing
                                                    ? 'Applying…'
                                                    : 'Apply'}
                                            </Button>

                                            <Button
                                                variant="outline"
                                                className="flex-1 xl:flex-none"
                                                asChild
                                            >
                                                <Link
                                                    href={WasteController.index()}
                                                >
                                                    <RotateCcw
                                                        className="size-4"
                                                        aria-hidden="true"
                                                    />
                                                    Clear
                                                </Link>
                                            </Button>

                                            <Button
                                                variant="outline"
                                                className="flex-1 xl:flex-none"
                                                asChild
                                            >
                                                <a href={exportUrl}>
                                                    <Download
                                                        className="size-4"
                                                        aria-hidden="true"
                                                    />
                                                    Export CSV
                                                </a>
                                            </Button>
                                        </div>

                                        {Object.keys(errors).length > 0 && (
                                            <div
                                                role="alert"
                                                className="rounded-md border border-destructive/30 bg-destructive/10 px-3 py-2 text-sm text-destructive md:col-span-2 xl:col-span-7"
                                            >
                                                One or more waste report filters
                                                are invalid. Review the values
                                                or clear the filters and try
                                                again.
                                            </div>
                                        )}
                                    </div>
                                </div>
                            )}
                        </Form>

                        <div
                            className={
                                canViewCosts
                                    ? 'grid gap-4 sm:grid-cols-2 xl:grid-cols-4'
                                    : 'grid gap-4 sm:grid-cols-2 xl:grid-cols-3'
                            }
                        >
                            <DashboardMetricCard
                                title="Waste records"
                                value={report.summary.recordCount.toLocaleString()}
                                description="Finalized waste entries matching the current filters"
                                icon={ClipboardList}
                                tone="blue"
                            />

                            <DashboardMetricCard
                                title="Waste quantity"
                                value={
                                    <QuantityTotals
                                        totals={report.summary.quantityTotals}
                                    />
                                }
                                description="Base-unit totals remain separated by UOM"
                                icon={Package}
                                tone="violet"
                            />

                            {canViewCosts && (
                                <DashboardMetricCard
                                    title="Waste value"
                                    value={
                                        report.summary.totalCost === null
                                            ? '—'
                                            : formatCurrency(
                                                  report.summary.totalCost,
                                                  currency,
                                              )
                                    }
                                    description="Snapshotted cost of filtered finalized waste"
                                    icon={CircleDollarSign}
                                    tone="emerald"
                                />
                            )}

                            <DashboardMetricCard
                                title="Top reason"
                                value={topReason?.reasonName ?? '—'}
                                description={
                                    topReason === null
                                        ? 'No matching waste records'
                                        : `${topReason.recordCount.toLocaleString()} ${
                                              topReason.recordCount === 1
                                                  ? 'record'
                                                  : 'records'
                                          }${
                                              topReasonPercentage === null
                                                  ? ''
                                                  : ` (${topReasonPercentage}%)`
                                          }`
                                }
                                icon={Star}
                                tone="amber"
                            />
                        </div>

                        <div className="grid gap-4 xl:grid-cols-2">
                            <BreakdownCard
                                id="waste-by-reason-title"
                                title="Waste by reason"
                                description="Quantity and value grouped by retained waste reason."
                            >
                                <table className="w-full min-w-[520px] text-sm">
                                    <caption className="sr-only">
                                        Waste grouped by reason.
                                    </caption>

                                    <thead className="border-b bg-muted/40 text-left">
                                        <tr>
                                            <th
                                                scope="col"
                                                className="px-4 py-2.5 font-medium text-muted-foreground"
                                            >
                                                Reason
                                            </th>
                                            <th
                                                scope="col"
                                                className="px-4 py-2.5 text-right font-medium text-muted-foreground"
                                            >
                                                Records
                                            </th>
                                            <th
                                                scope="col"
                                                className="px-4 py-2.5 text-right font-medium text-muted-foreground"
                                            >
                                                Quantity
                                            </th>

                                            {canViewCosts && (
                                                <th
                                                    scope="col"
                                                    className="px-4 py-2.5 text-right font-medium text-muted-foreground"
                                                >
                                                    Waste value
                                                </th>
                                            )}
                                        </tr>
                                    </thead>

                                    <tbody>
                                        {report.byReason.length === 0 ? (
                                            <tr>
                                                <td
                                                    colSpan={
                                                        canViewCosts ? 4 : 3
                                                    }
                                                    className="px-6 py-10 text-center text-muted-foreground"
                                                >
                                                    No waste records match the
                                                    selected filters.
                                                </td>
                                            </tr>
                                        ) : (
                                            report.byReason.map((row) => (
                                                <tr
                                                    key={row.reasonId}
                                                    className="border-b border-sidebar-border/70 last:border-b-0 hover:bg-muted/30 dark:border-sidebar-border"
                                                >
                                                    <td className="px-4 py-2.5 font-medium">
                                                        {row.reasonName}
                                                    </td>

                                                    <td className="px-4 py-2.5 text-right tabular-nums">
                                                        {row.recordCount.toLocaleString()}
                                                    </td>

                                                    <td className="px-4 py-2.5 text-right">
                                                        <QuantityTotals
                                                            totals={
                                                                row.quantityTotals
                                                            }
                                                            align="right"
                                                        />
                                                    </td>

                                                    {canViewCosts && (
                                                        <td className="px-4 py-2.5 text-right font-medium whitespace-nowrap tabular-nums">
                                                            {row.totalCost ===
                                                            null
                                                                ? '—'
                                                                : formatCurrency(
                                                                      row.totalCost,
                                                                      currency,
                                                                  )}
                                                        </td>
                                                    )}
                                                </tr>
                                            ))
                                        )}
                                    </tbody>

                                    {report.byReason.length > 0 && (
                                        <tfoot>
                                            <tr className="border-t bg-muted/30 font-medium">
                                                <td className="px-4 py-2.5">
                                                    Total
                                                </td>

                                                <td className="px-4 py-2.5 text-right tabular-nums">
                                                    {report.summary.recordCount.toLocaleString()}
                                                </td>

                                                <td className="px-4 py-2.5 text-right font-semibold">
                                                    <QuantityTotals
                                                        totals={
                                                            report.summary
                                                                .quantityTotals
                                                        }
                                                        align="right"
                                                    />
                                                </td>

                                                {canViewCosts && (
                                                    <td className="px-4 py-2.5 text-right font-semibold whitespace-nowrap tabular-nums">
                                                        {report.summary
                                                            .totalCost === null
                                                            ? '—'
                                                            : formatCurrency(
                                                                  report.summary
                                                                      .totalCost,
                                                                  currency,
                                                              )}
                                                    </td>
                                                )}
                                            </tr>
                                        </tfoot>
                                    )}
                                </table>
                            </BreakdownCard>

                            <BreakdownCard
                                id="waste-by-employee-title"
                                title="Waste by employee"
                                description="Quantity and value grouped by the user who recorded the waste."
                            >
                                <table className="w-full min-w-[520px] text-sm">
                                    <caption className="sr-only">
                                        Waste grouped by employee.
                                    </caption>

                                    <thead className="border-b bg-muted/40 text-left">
                                        <tr>
                                            <th
                                                scope="col"
                                                className="px-4 py-2.5 font-medium text-muted-foreground"
                                            >
                                                Employee
                                            </th>
                                            <th
                                                scope="col"
                                                className="px-4 py-2.5 text-right font-medium text-muted-foreground"
                                            >
                                                Records
                                            </th>
                                            <th
                                                scope="col"
                                                className="px-4 py-2.5 text-right font-medium text-muted-foreground"
                                            >
                                                Quantity
                                            </th>

                                            {canViewCosts && (
                                                <th
                                                    scope="col"
                                                    className="px-4 py-2.5 text-right font-medium text-muted-foreground"
                                                >
                                                    Waste value
                                                </th>
                                            )}
                                        </tr>
                                    </thead>

                                    <tbody>
                                        {report.byEmployee.length === 0 ? (
                                            <tr>
                                                <td
                                                    colSpan={
                                                        canViewCosts ? 4 : 3
                                                    }
                                                    className="px-6 py-10 text-center text-muted-foreground"
                                                >
                                                    No waste records match the
                                                    selected filters.
                                                </td>
                                            </tr>
                                        ) : (
                                            report.byEmployee.map((row) => (
                                                <tr
                                                    key={
                                                        row.employeeId ??
                                                        'unknown'
                                                    }
                                                    className="border-b border-sidebar-border/70 last:border-b-0 hover:bg-muted/30 dark:border-sidebar-border"
                                                >
                                                    <td className="px-4 py-2.5 font-medium">
                                                        {row.employeeName}
                                                    </td>

                                                    <td className="px-4 py-2.5 text-right tabular-nums">
                                                        {row.recordCount.toLocaleString()}
                                                    </td>

                                                    <td className="px-4 py-2.5 text-right">
                                                        <QuantityTotals
                                                            totals={
                                                                row.quantityTotals
                                                            }
                                                            align="right"
                                                        />
                                                    </td>

                                                    {canViewCosts && (
                                                        <td className="px-4 py-2.5 text-right font-medium whitespace-nowrap tabular-nums">
                                                            {row.totalCost ===
                                                            null
                                                                ? '—'
                                                                : formatCurrency(
                                                                      row.totalCost,
                                                                      currency,
                                                                  )}
                                                        </td>
                                                    )}
                                                </tr>
                                            ))
                                        )}
                                    </tbody>

                                    {report.byEmployee.length > 0 && (
                                        <tfoot>
                                            <tr className="border-t bg-muted/30 font-medium">
                                                <td className="px-4 py-2.5">
                                                    Total
                                                </td>

                                                <td className="px-4 py-2.5 text-right tabular-nums">
                                                    {report.summary.recordCount.toLocaleString()}
                                                </td>

                                                <td className="px-4 py-2.5 text-right font-semibold">
                                                    <QuantityTotals
                                                        totals={
                                                            report.summary
                                                                .quantityTotals
                                                        }
                                                        align="right"
                                                    />
                                                </td>

                                                {canViewCosts && (
                                                    <td className="px-4 py-2.5 text-right font-semibold whitespace-nowrap tabular-nums">
                                                        {report.summary
                                                            .totalCost === null
                                                            ? '—'
                                                            : formatCurrency(
                                                                  report.summary
                                                                      .totalCost,
                                                                  currency,
                                                              )}
                                                    </td>
                                                )}
                                            </tr>
                                        </tfoot>
                                    )}
                                </table>
                            </BreakdownCard>

                            <BreakdownCard
                                id="waste-by-item-title"
                                title="Waste by item"
                                description="Quantity and value grouped by inventory item."
                            >
                                <table className="w-full min-w-[520px] text-sm">
                                    <caption className="sr-only">
                                        Waste grouped by inventory item.
                                    </caption>

                                    <thead className="border-b bg-muted/40 text-left">
                                        <tr>
                                            <th
                                                scope="col"
                                                className="px-4 py-2.5 font-medium text-muted-foreground"
                                            >
                                                Item
                                            </th>
                                            <th
                                                scope="col"
                                                className="px-4 py-2.5 text-right font-medium text-muted-foreground"
                                            >
                                                Records
                                            </th>
                                            <th
                                                scope="col"
                                                className="px-4 py-2.5 text-right font-medium text-muted-foreground"
                                            >
                                                Quantity
                                            </th>

                                            {canViewCosts && (
                                                <th
                                                    scope="col"
                                                    className="px-4 py-2.5 text-right font-medium text-muted-foreground"
                                                >
                                                    Waste value
                                                </th>
                                            )}
                                        </tr>
                                    </thead>

                                    <tbody>
                                        {report.byItem.length === 0 ? (
                                            <tr>
                                                <td
                                                    colSpan={
                                                        canViewCosts ? 4 : 3
                                                    }
                                                    className="px-6 py-10 text-center text-muted-foreground"
                                                >
                                                    No waste records match the
                                                    selected filters.
                                                </td>
                                            </tr>
                                        ) : (
                                            report.byItem.map((row) => (
                                                <tr
                                                    key={row.itemId}
                                                    className="border-b border-sidebar-border/70 last:border-b-0 hover:bg-muted/30 dark:border-sidebar-border"
                                                >
                                                    <td className="px-4 py-2.5">
                                                        <div className="font-medium">
                                                            {row.itemName}
                                                        </div>
                                                        <div className="mt-0.5 text-xs text-muted-foreground">
                                                            {row.itemSku}
                                                        </div>
                                                    </td>

                                                    <td className="px-4 py-2.5 text-right tabular-nums">
                                                        {row.recordCount.toLocaleString()}
                                                    </td>

                                                    <td className="px-4 py-2.5 text-right font-medium whitespace-nowrap tabular-nums">
                                                        {formatDecimal(
                                                            row.totalQuantity,
                                                        )}{' '}
                                                        <span className="font-normal text-muted-foreground">
                                                            {row.baseUnitSymbol}
                                                        </span>
                                                    </td>

                                                    {canViewCosts && (
                                                        <td className="px-4 py-2.5 text-right font-medium whitespace-nowrap tabular-nums">
                                                            {row.totalCost ===
                                                            null
                                                                ? '—'
                                                                : formatCurrency(
                                                                      row.totalCost,
                                                                      currency,
                                                                  )}
                                                        </td>
                                                    )}
                                                </tr>
                                            ))
                                        )}
                                    </tbody>

                                    {report.byItem.length > 0 && (
                                        <tfoot>
                                            <tr className="border-t bg-muted/30 font-medium">
                                                <td className="px-4 py-2.5">
                                                    Total
                                                </td>

                                                <td className="px-4 py-2.5 text-right tabular-nums">
                                                    {report.summary.recordCount.toLocaleString()}
                                                </td>

                                                <td className="px-4 py-2.5 text-right font-semibold">
                                                    <QuantityTotals
                                                        totals={
                                                            report.summary
                                                                .quantityTotals
                                                        }
                                                        align="right"
                                                    />
                                                </td>

                                                {canViewCosts && (
                                                    <td className="px-4 py-2.5 text-right font-semibold whitespace-nowrap tabular-nums">
                                                        {report.summary
                                                            .totalCost === null
                                                            ? '—'
                                                            : formatCurrency(
                                                                  report.summary
                                                                      .totalCost,
                                                                  currency,
                                                              )}
                                                    </td>
                                                )}
                                            </tr>
                                        </tfoot>
                                    )}
                                </table>
                            </BreakdownCard>

                            <BreakdownCard
                                id="waste-by-location-title"
                                title="Waste by location"
                                description="Quantity and value grouped by restaurant location."
                            >
                                <table className="w-full min-w-[520px] text-sm">
                                    <caption className="sr-only">
                                        Waste grouped by location.
                                    </caption>

                                    <thead className="border-b bg-muted/40 text-left">
                                        <tr>
                                            <th
                                                scope="col"
                                                className="px-4 py-2.5 font-medium text-muted-foreground"
                                            >
                                                Location
                                            </th>
                                            <th
                                                scope="col"
                                                className="px-4 py-2.5 text-right font-medium text-muted-foreground"
                                            >
                                                Records
                                            </th>
                                            <th
                                                scope="col"
                                                className="px-4 py-2.5 text-right font-medium text-muted-foreground"
                                            >
                                                Quantity
                                            </th>

                                            {canViewCosts && (
                                                <th
                                                    scope="col"
                                                    className="px-4 py-2.5 text-right font-medium text-muted-foreground"
                                                >
                                                    Waste value
                                                </th>
                                            )}
                                        </tr>
                                    </thead>

                                    <tbody>
                                        {report.byLocation.length === 0 ? (
                                            <tr>
                                                <td
                                                    colSpan={
                                                        canViewCosts ? 4 : 3
                                                    }
                                                    className="px-6 py-10 text-center text-muted-foreground"
                                                >
                                                    No waste records match the
                                                    selected filters.
                                                </td>
                                            </tr>
                                        ) : (
                                            report.byLocation.map((row) => (
                                                <tr
                                                    key={row.locationId}
                                                    className="border-b border-sidebar-border/70 last:border-b-0 hover:bg-muted/30 dark:border-sidebar-border"
                                                >
                                                    <td className="px-4 py-2.5 font-medium">
                                                        {row.locationName}
                                                    </td>

                                                    <td className="px-4 py-2.5 text-right tabular-nums">
                                                        {row.recordCount.toLocaleString()}
                                                    </td>

                                                    <td className="px-4 py-2.5 text-right">
                                                        <QuantityTotals
                                                            totals={
                                                                row.quantityTotals
                                                            }
                                                            align="right"
                                                        />
                                                    </td>

                                                    {canViewCosts && (
                                                        <td className="px-4 py-2.5 text-right font-medium whitespace-nowrap tabular-nums">
                                                            {row.totalCost ===
                                                            null
                                                                ? '—'
                                                                : formatCurrency(
                                                                      row.totalCost,
                                                                      currency,
                                                                  )}
                                                        </td>
                                                    )}
                                                </tr>
                                            ))
                                        )}
                                    </tbody>

                                    {report.byLocation.length > 0 && (
                                        <tfoot>
                                            <tr className="border-t bg-muted/30 font-medium">
                                                <td className="px-4 py-2.5">
                                                    Total
                                                </td>

                                                <td className="px-4 py-2.5 text-right tabular-nums">
                                                    {report.summary.recordCount.toLocaleString()}
                                                </td>

                                                <td className="px-4 py-2.5 text-right font-semibold">
                                                    <QuantityTotals
                                                        totals={
                                                            report.summary
                                                                .quantityTotals
                                                        }
                                                        align="right"
                                                    />
                                                </td>

                                                {canViewCosts && (
                                                    <td className="px-4 py-2.5 text-right font-semibold whitespace-nowrap tabular-nums">
                                                        {report.summary
                                                            .totalCost === null
                                                            ? '—'
                                                            : formatCurrency(
                                                                  report.summary
                                                                      .totalCost,
                                                                  currency,
                                                              )}
                                                    </td>
                                                )}
                                            </tr>
                                        </tfoot>
                                    )}
                                </table>
                            </BreakdownCard>
                        </div>

                        <section
                            className="overflow-hidden rounded-xl border border-sidebar-border/70 bg-card shadow-sm dark:border-sidebar-border"
                            aria-labelledby="waste-evidence-title"
                        >
                            <div className="flex min-h-14 flex-wrap items-center justify-between gap-3 border-b border-sidebar-border/70 px-4 py-3 dark:border-sidebar-border">
                                <div>
                                    <h3
                                        id="waste-evidence-title"
                                        className="text-sm font-semibold"
                                    >
                                        Waste evidence
                                    </h3>
                                    <p className="mt-0.5 text-xs text-muted-foreground">
                                        Detailed immutable records behind the
                                        selected report totals.
                                    </p>
                                </div>

                                <div className="flex items-center gap-2 text-xs text-muted-foreground">
                                    <Tags
                                        className="size-3.5"
                                        aria-hidden="true"
                                    />
                                    {rows?.total.toLocaleString() ?? '0'}{' '}
                                    records
                                </div>
                            </div>

                            <div className="overflow-x-auto">
                                <table className="w-full min-w-[1120px] text-sm">
                                    <caption className="sr-only">
                                        Immutable waste evidence showing source,
                                        occurrence time, inventory context,
                                        quantities, recorder, and authorized
                                        cost information.
                                    </caption>

                                    <thead className="border-b bg-muted/40 text-left">
                                        <tr>
                                            <th
                                                scope="col"
                                                className="px-4 py-3 font-medium text-muted-foreground"
                                            >
                                                Source
                                            </th>
                                            <th
                                                scope="col"
                                                className="px-4 py-3 font-medium text-muted-foreground"
                                            >
                                                Occurred
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
                                                Item
                                            </th>
                                            <th
                                                scope="col"
                                                className="px-4 py-3 font-medium text-muted-foreground"
                                            >
                                                Reason
                                            </th>
                                            <th
                                                scope="col"
                                                className="px-4 py-3 text-right font-medium text-muted-foreground"
                                            >
                                                Entered quantity
                                            </th>
                                            <th
                                                scope="col"
                                                className="px-4 py-3 text-right font-medium text-muted-foreground"
                                            >
                                                Base quantity
                                            </th>
                                            <th
                                                scope="col"
                                                className="px-4 py-3 font-medium text-muted-foreground"
                                            >
                                                Recorded by
                                            </th>

                                            {canViewCosts && (
                                                <>
                                                    <th
                                                        scope="col"
                                                        className="px-4 py-3 text-right font-medium text-muted-foreground"
                                                    >
                                                        Unit cost
                                                    </th>
                                                    <th
                                                        scope="col"
                                                        className="px-4 py-3 text-right font-medium text-muted-foreground"
                                                    >
                                                        Waste value
                                                    </th>
                                                </>
                                            )}
                                        </tr>
                                    </thead>

                                    <tbody>
                                        {reportRows.length === 0 ? (
                                            <tr>
                                                <td
                                                    colSpan={
                                                        canViewCosts ? 10 : 8
                                                    }
                                                    className="px-6 py-14 text-center"
                                                >
                                                    <div className="mx-auto flex size-10 items-center justify-center rounded-full bg-muted">
                                                        <Search
                                                            className="size-5 text-muted-foreground"
                                                            aria-hidden="true"
                                                        />
                                                    </div>

                                                    <p className="mt-3 font-medium">
                                                        No waste evidence found
                                                    </p>

                                                    <p className="mt-1 text-sm text-muted-foreground">
                                                        Adjust or clear the
                                                        report filters to view
                                                        finalized waste records.
                                                    </p>
                                                </td>
                                            </tr>
                                        ) : (
                                            reportRows.map((row) => (
                                                <tr
                                                    key={row.recordId}
                                                    className="border-b border-sidebar-border/70 align-top transition-colors last:border-b-0 hover:bg-muted/30 dark:border-sidebar-border"
                                                >
                                                    <td className="px-4 py-3">
                                                        <div className="font-medium whitespace-nowrap">
                                                            Waste #
                                                            {row.recordId}
                                                        </div>
                                                        <div className="mt-0.5 text-xs whitespace-nowrap text-muted-foreground">
                                                            Movement{' '}
                                                            {row.movementId ===
                                                            null
                                                                ? '—'
                                                                : `#${row.movementId}`}
                                                        </div>
                                                    </td>

                                                    <td className="px-4 py-3 whitespace-nowrap">
                                                        {formatDate(
                                                            row.occurredAt,
                                                        )}
                                                    </td>

                                                    <td className="px-4 py-3">
                                                        <div className="font-medium whitespace-nowrap">
                                                            {row.locationName}
                                                        </div>
                                                        <div className="mt-0.5 text-xs whitespace-nowrap text-muted-foreground">
                                                            {
                                                                row.storageLocationName
                                                            }
                                                        </div>
                                                    </td>

                                                    <td className="px-4 py-3">
                                                        <div className="font-medium whitespace-nowrap">
                                                            {row.itemName}
                                                        </div>
                                                        <div className="mt-0.5 text-xs whitespace-nowrap text-muted-foreground">
                                                            {row.itemSku}
                                                        </div>
                                                    </td>

                                                    <td className="px-4 py-3 whitespace-nowrap">
                                                        {row.reasonName}
                                                    </td>

                                                    <td className="px-4 py-3 text-right font-medium whitespace-nowrap tabular-nums">
                                                        {formatDecimal(
                                                            row.quantity,
                                                        )}{' '}
                                                        <span className="font-normal text-muted-foreground">
                                                            {row.unitSymbol}
                                                        </span>
                                                    </td>

                                                    <td className="px-4 py-3 text-right font-medium whitespace-nowrap tabular-nums">
                                                        {formatDecimal(
                                                            row.baseQuantity,
                                                        )}{' '}
                                                        <span className="font-normal text-muted-foreground">
                                                            {row.baseUnitSymbol}
                                                        </span>
                                                    </td>

                                                    <td className="px-4 py-3">
                                                        <div className="font-medium whitespace-nowrap">
                                                            {row.recordedBy ??
                                                                '—'}
                                                        </div>

                                                        {row.notes && (
                                                            <div className="mt-0.5 max-w-64 text-xs leading-5 text-muted-foreground">
                                                                {row.notes}
                                                            </div>
                                                        )}
                                                    </td>

                                                    {canViewCosts && (
                                                        <>
                                                            <td className="px-4 py-3 text-right whitespace-nowrap tabular-nums">
                                                                {row.unitCost ===
                                                                null
                                                                    ? '—'
                                                                    : formatCurrency(
                                                                          row.unitCost,
                                                                          currency,
                                                                      )}
                                                            </td>

                                                            <td className="px-4 py-3 text-right font-semibold whitespace-nowrap tabular-nums">
                                                                {row.totalCost ===
                                                                null
                                                                    ? '—'
                                                                    : formatCurrency(
                                                                          row.totalCost,
                                                                          currency,
                                                                      )}
                                                            </td>
                                                        </>
                                                    )}
                                                </tr>
                                            ))
                                        )}
                                    </tbody>
                                </table>
                            </div>

                            {rows !== null && rows.total > 0 && (
                                <div className="flex flex-col gap-3 border-t border-sidebar-border/70 px-4 py-3 sm:flex-row sm:items-center sm:justify-between dark:border-sidebar-border">
                                    <p className="text-sm text-muted-foreground">
                                        Showing {rows.from ?? 0} to{' '}
                                        {rows.to ?? 0} of{' '}
                                        {rows.total.toLocaleString()} waste
                                        records.
                                    </p>

                                    {rows.last_page > 1 && (
                                        <nav
                                            className="flex items-center gap-2"
                                            aria-label="Waste evidence pagination"
                                        >
                                            {rows.prev_page_url !== null ? (
                                                <Button
                                                    variant="outline"
                                                    size="icon"
                                                    asChild
                                                >
                                                    <Link
                                                        href={
                                                            rows.prev_page_url
                                                        }
                                                        preserveScroll
                                                        preserveState
                                                        aria-label="Previous page"
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
                                                    size="icon"
                                                    aria-label="Previous page"
                                                    disabled
                                                >
                                                    <ChevronLeft
                                                        className="size-4"
                                                        aria-hidden="true"
                                                    />
                                                </Button>
                                            )}

                                            <span
                                                className="flex size-9 items-center justify-center rounded-md bg-primary text-sm font-medium text-primary-foreground tabular-nums"
                                                aria-current="page"
                                                aria-label={`Page ${rows.current_page} of ${rows.last_page}`}
                                            >
                                                {rows.current_page}
                                            </span>

                                            {rows.next_page_url !== null ? (
                                                <Button
                                                    variant="outline"
                                                    size="icon"
                                                    asChild
                                                >
                                                    <Link
                                                        href={
                                                            rows.next_page_url
                                                        }
                                                        preserveScroll
                                                        preserveState
                                                        aria-label="Next page"
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
                                                    size="icon"
                                                    aria-label="Next page"
                                                    disabled
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
                            )}
                        </section>
                    </section>
                )}
            </div>
        </>
    );
}

WasteIndex.layout = {
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: dashboard(),
        },
        {
            title: 'Waste',
            href: WasteController.index(),
        },
    ],
};
