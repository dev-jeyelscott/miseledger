import { Form, Head, Link, router, usePage } from '@inertiajs/react';
import {
    ChevronDown,
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
    X,
} from 'lucide-react';
import type { ReactNode } from 'react';
import { useEffect, useRef, useState } from 'react';

import WasteController from '@/actions/App/Http/Controllers/Inventory/WasteController';
import WasteReasonController from '@/actions/App/Http/Controllers/Inventory/WasteReasonController';
import { DashboardMetricCard } from '@/components/dashboard/dashboard-metric-card';
import { EmptyState } from '@/components/empty-state';
import { FilterToolbar } from '@/components/filter-toolbar';
import InputError from '@/components/input-error';
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
import type { OrganizationContext } from '@/types';

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
    timezone: string;
    canRecord: boolean;
    canManageReasons: boolean;
    canViewReport: boolean;
    canViewCosts: boolean;
    wasteReasons: WasteReason[];
    recordForm: RecordForm | null;
    reportOptions: ReportOptions | null;
};

type ActiveFilter = {
    key: string;
    label: string;
    value: string;
};

const textareaClassName =
    'border-input bg-background min-h-24 w-full resize-y rounded-md border px-3 py-2 text-sm shadow-xs outline-none transition-[color,box-shadow] placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 aria-invalid:border-destructive aria-invalid:ring-[3px] aria-invalid:ring-destructive/20 disabled:cursor-not-allowed disabled:opacity-50';

/** Format persisted decimals without introducing binary floating-point conversion. */
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

/** Format persisted monetary values without reducing server precision. */
function formatCurrency(value: string, currency: string): string {
    return `${currency} ${formatDecimal(value)}`;
}

/** Render operational timestamps in the active organization's configured timezone. */
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

/** Keep the confirmation timestamp faithful to the organization-local form value. */
function formatEnteredOccurrence(value: string, timezone: string): string {
    if (value === '') {
        return 'Not selected';
    }

    return `${value.replace('T', ' ')} (${timezone})`;
}

/** Keep CSV export synchronized with the server-authoritative active filters. */
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

/** Preserve server-backed report filters while removing one selected dimension. */
function buildReportUrl(
    filters: Props['filters'],
    changes: Record<string, string | null>,
): string {
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

    Object.entries(changes).forEach(([key, value]) => {
        if (value === null || value === '') {
            params.delete(key);

            return;
        }

        params.set(key, value);
    });
    const query = params.toString();

    return query === ''
        ? WasteController.index().url
        : `${WasteController.index().url}?${query}`;
}

/** Render quantity aggregates without combining unrelated base units. */
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

/** Resolve a selected option without duplicating lookup logic across confirmation fields. */
function selectedOption<T extends Option>(
    options: T[],
    value: string,
): T | undefined {
    return options.find((option) => option.id.toString() === value);
}

/** Render one required label while keeping the shared Field composition authoritative. */
function requiredLabel(label: string): ReactNode {
    return (
        <>
            {label}
            <span className="ml-0.5 text-destructive" aria-hidden="true">
                *
            </span>
            <span className="sr-only"> required</span>
        </>
    );
}

/** Build human-readable active filter labels from the exact server-applied filters. */
function activeReportFilters(
    filters: Props['filters'],
    options: ReportOptions,
): ActiveFilter[] {
    const active: ActiveFilter[] = [];

    if (filters.locationId !== null) {
        active.push({
            key: 'location_id',
            label: 'Location',
            value:
                options.locations.find(
                    (option) => option.id === filters.locationId,
                )?.name ?? `#${filters.locationId}`,
        });
    }

    if (filters.inventoryCategoryId !== null) {
        active.push({
            key: 'inventory_category_id',
            label: 'Category',
            value:
                options.inventoryCategories.find(
                    (option) => option.id === filters.inventoryCategoryId,
                )?.name ?? `#${filters.inventoryCategoryId}`,
        });
    }

    if (filters.inventoryItemId !== null) {
        const item = options.inventoryItems.find(
            (option) => option.id === filters.inventoryItemId,
        );

        active.push({
            key: 'inventory_item_id',
            label: 'Item',
            value: item
                ? `${item.name} (${item.sku})`
                : `#${filters.inventoryItemId}`,
        });
    }

    if (filters.wasteReasonId !== null) {
        active.push({
            key: 'waste_reason_id',
            label: 'Reason',
            value:
                options.wasteReasons.find(
                    (option) => option.id === filters.wasteReasonId,
                )?.name ?? `#${filters.wasteReasonId}`,
        });
    }

    if (filters.from !== null) {
        active.push({ key: 'from', label: 'From', value: filters.from });
    }

    if (filters.to !== null) {
        active.push({ key: 'to', label: 'To', value: filters.to });
    }

    return active;
}

/** Render one progressive-disclosure boundary for a secondary breakdown table. */
function BreakdownDisclosure({
    title,
    description,
    count,
    defaultOpen = false,
    children,
}: {
    title: string;
    description: string;
    count: number;
    defaultOpen?: boolean;
    children: ReactNode;
}) {
    return (
        <details
            className="overflow-hidden rounded-xl border border-border bg-card"
            open={defaultOpen}
        >
            <summary className="flex cursor-pointer list-none items-center justify-between gap-4 px-4 py-3 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none">
                <span className="min-w-0">
                    <span className="block text-sm font-semibold">{title}</span>
                    <span className="mt-0.5 block text-xs font-normal text-muted-foreground">
                        {description}
                    </span>
                </span>

                <span className="flex shrink-0 items-center gap-2 text-xs text-muted-foreground">
                    {count.toLocaleString()} groups
                    <ChevronDown className="size-4" aria-hidden="true" />
                </span>
            </summary>

            <div className="border-t border-border">{children}</div>
        </details>
    );
}

/**
 * Keep the irreversible Waste operation in its dedicated workspace.
 * Every submission is intercepted by an inventory-impact confirmation.
 */
function RecordWasteForm({
    recordForm,
    timezone,
}: {
    recordForm: RecordForm;
    timezone: string;
}) {
    const confirmedSubmission = useRef(false);
    const [locationId, setLocationId] = useState('');
    const [storageLocationId, setStorageLocationId] = useState('');
    const [inventoryItemId, setInventoryItemId] = useState('');
    const [unitId, setUnitId] = useState('');
    const [reasonId, setReasonId] = useState('');
    const [quantity, setQuantity] = useState('');
    const [occurredAt, setOccurredAt] = useState(recordForm.defaultOccurredAt);
    const [dirty, setDirty] = useState(false);
    const [confirmationOpen, setConfirmationOpen] = useState(false);

    useEffect(() => {
        if (!dirty) {
            return;
        }

        const removeBeforeListener = router.on('before', (event) => {
            if (!window.confirm('Discard the waste details you entered?')) {
                event.preventDefault();
            }
        });

        const handleBeforeUnload = (event: BeforeUnloadEvent) => {
            event.preventDefault();
            event.returnValue = '';
        };

        window.addEventListener('beforeunload', handleBeforeUnload);

        return () => {
            removeBeforeListener();
            window.removeEventListener('beforeunload', handleBeforeUnload);
        };
    }, [dirty]);

    const storageOptions = recordForm.storageLocationOptions.filter(
        (storageLocation) =>
            storageLocation.locationId.toString() === locationId,
    );
    const selectedItem = selectedOption(
        recordForm.inventoryItemOptions,
        inventoryItemId,
    );
    const unitOptions = recordForm.unitOptions.filter((unit) =>
        selectedItem?.validUnitIds.includes(unit.id),
    );
    const selectedLocation = selectedOption(
        recordForm.locationOptions,
        locationId,
    );
    const selectedStorage = selectedOption(storageOptions, storageLocationId);
    const selectedUnit = selectedOption(unitOptions, unitId);
    const selectedReason = selectedOption(recordForm.reasonOptions, reasonId);

    /** Submit only after the user explicitly confirms the irreversible inventory impact. */
    function confirmWasteSubmission(): void {
        const form = document.getElementById('record-waste-form');

        if (!(form instanceof HTMLFormElement)) {
            return;
        }

        confirmedSubmission.current = true;
        setConfirmationOpen(false);
        setDirty(false);
        form.requestSubmit();
    }

    return (
        <>
            <Form
                id="record-waste-form"
                action={WasteController.store().url}
                method="post"
                errorBag="recordWaste"
                onSubmit={(event) => {
                    if (confirmedSubmission.current) {
                        confirmedSubmission.current = false;

                        return;
                    }

                    event.preventDefault();

                    if (!event.currentTarget.reportValidity()) {
                        return;
                    }

                    setConfirmationOpen(true);
                }}
                onError={() => setDirty(true)}
            >
                {({ errors, processing }) => (
                    <div
                        className="grid gap-5 p-5"
                        onChange={() => setDirty(true)}
                    >
                        <input
                            type="hidden"
                            name="operation_id"
                            value={recordForm.operationId}
                        />

                        <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                            <Field
                                id="location_id"
                                label={requiredLabel('Location')}
                                error={errors.location_id}
                            >
                                <NativeSelect
                                    name="location_id"
                                    value={locationId}
                                    onChange={(event) => {
                                        setLocationId(event.target.value);
                                        setStorageLocationId('');
                                    }}
                                    required
                                >
                                    <option value="">Select location</option>
                                    {recordForm.locationOptions.map(
                                        (option) => (
                                            <option
                                                key={option.id}
                                                value={option.id}
                                            >
                                                {option.name}
                                            </option>
                                        ),
                                    )}
                                </NativeSelect>
                            </Field>

                            <Field
                                id="storage_location_id"
                                label={requiredLabel('Storage location')}
                                error={errors.storage_location_id}
                            >
                                <NativeSelect
                                    name="storage_location_id"
                                    value={storageLocationId}
                                    onChange={(event) =>
                                        setStorageLocationId(event.target.value)
                                    }
                                    disabled={locationId === ''}
                                    required
                                >
                                    <option value="">
                                        {locationId === ''
                                            ? 'Select location first'
                                            : 'Select storage location'}
                                    </option>
                                    {storageOptions.map((option) => (
                                        <option
                                            key={option.id}
                                            value={option.id}
                                        >
                                            {option.name}
                                        </option>
                                    ))}
                                </NativeSelect>
                            </Field>

                            <Field
                                id="inventory_item_id"
                                label={requiredLabel('Inventory item')}
                                error={errors.inventory_item_id}
                            >
                                <NativeSelect
                                    name="inventory_item_id"
                                    value={inventoryItemId}
                                    onChange={(event) => {
                                        setInventoryItemId(event.target.value);
                                        setUnitId('');
                                    }}
                                    required
                                >
                                    <option value="">Select item</option>
                                    {recordForm.inventoryItemOptions.map(
                                        (option) => (
                                            <option
                                                key={option.id}
                                                value={option.id}
                                            >
                                                {option.name} ({option.sku})
                                            </option>
                                        ),
                                    )}
                                </NativeSelect>
                            </Field>

                            <Field
                                id="unit_id"
                                label={requiredLabel('Unit')}
                                error={errors.unit_id ?? errors.unit}
                            >
                                <NativeSelect
                                    name="unit_id"
                                    value={unitId}
                                    onChange={(event) =>
                                        setUnitId(event.target.value)
                                    }
                                    disabled={inventoryItemId === ''}
                                    required
                                >
                                    <option value="">
                                        {inventoryItemId === ''
                                            ? 'Select item first'
                                            : 'Select unit'}
                                    </option>
                                    {unitOptions.map((option) => (
                                        <option
                                            key={option.id}
                                            value={option.id}
                                        >
                                            {option.name} ({option.symbol})
                                        </option>
                                    ))}
                                </NativeSelect>
                            </Field>

                            <Field
                                id="waste_reason_id"
                                label={requiredLabel('Reason')}
                                error={errors.waste_reason_id}
                            >
                                <NativeSelect
                                    name="waste_reason_id"
                                    value={reasonId}
                                    onChange={(event) =>
                                        setReasonId(event.target.value)
                                    }
                                    required
                                >
                                    <option value="">Select reason</option>
                                    {recordForm.reasonOptions.map((option) => (
                                        <option
                                            key={option.id}
                                            value={option.id}
                                        >
                                            {option.name}
                                        </option>
                                    ))}
                                </NativeSelect>
                            </Field>

                            <Field
                                id="quantity"
                                label={requiredLabel('Quantity')}
                                error={errors.quantity}
                            >
                                <Input
                                    name="quantity"
                                    type="number"
                                    inputMode="decimal"
                                    min="0.000001"
                                    step="0.000001"
                                    value={quantity}
                                    onChange={(event) =>
                                        setQuantity(event.target.value)
                                    }
                                    placeholder="e.g. 2.5"
                                    required
                                />
                            </Field>

                            <Field
                                id="occurred_at"
                                label={requiredLabel('Occurred at')}
                                error={errors.occurred_at}
                                helper={`Interpreted in ${timezone}.`}
                            >
                                <Input
                                    name="occurred_at"
                                    type="datetime-local"
                                    value={occurredAt}
                                    onChange={(event) =>
                                        setOccurredAt(event.target.value)
                                    }
                                    required
                                />
                            </Field>
                        </div>

                        <Field
                            id="notes"
                            label="Notes"
                            error={errors.notes}
                            helper="Optional. Up to 2,000 characters."
                        >
                            <textarea
                                name="notes"
                                rows={3}
                                maxLength={2000}
                                placeholder="Add any additional details"
                                className={textareaClassName}
                            />
                        </Field>

                        <InputError message={errors.operation_id} />

                        {recordForm.reasonOptions.length === 0 && (
                            <div
                                className="rounded-md border border-destructive/30 bg-destructive/10 px-3 py-2 text-sm text-destructive"
                                role="alert"
                            >
                                Create an active waste reason before recording
                                waste.
                            </div>
                        )}

                        <div>
                            <Button
                                type="submit"
                                disabled={
                                    processing ||
                                    recordForm.reasonOptions.length === 0
                                }
                            >
                                <ClipboardList
                                    className="size-4"
                                    aria-hidden="true"
                                />
                                {processing
                                    ? 'Recording…'
                                    : 'Review and record waste'}
                            </Button>
                        </div>
                    </div>
                )}
            </Form>

            <Dialog open={confirmationOpen} onOpenChange={setConfirmationOpen}>
                <DialogContent className="sm:max-w-lg">
                    <DialogHeader>
                        <DialogTitle>Confirm record waste</DialogTitle>
                        <DialogDescription>
                            Recording waste is irreversible from this workspace.
                            Confirm the exact inventory operation before stock
                            is reduced.
                        </DialogDescription>
                    </DialogHeader>

                    <dl className="grid gap-3 rounded-lg border border-border bg-muted/30 p-4 text-sm">
                        <div className="flex items-start justify-between gap-4">
                            <dt className="text-muted-foreground">Item</dt>
                            <dd className="text-right font-medium">
                                {selectedItem
                                    ? `${selectedItem.name} (${selectedItem.sku})`
                                    : 'Not selected'}
                            </dd>
                        </div>
                        <div className="flex items-start justify-between gap-4">
                            <dt className="text-muted-foreground">
                                Inventory decrease
                            </dt>
                            <dd className="text-right font-semibold">
                                {quantity === ''
                                    ? 'Not entered'
                                    : `−${quantity} ${
                                          selectedUnit?.symbol ?? ''
                                      }`}
                            </dd>
                        </div>
                        <div className="flex items-start justify-between gap-4">
                            <dt className="text-muted-foreground">Location</dt>
                            <dd className="text-right font-medium">
                                {selectedLocation?.name ?? 'Not selected'}
                            </dd>
                        </div>
                        <div className="flex items-start justify-between gap-4">
                            <dt className="text-muted-foreground">
                                Storage location
                            </dt>
                            <dd className="text-right font-medium">
                                {selectedStorage?.name ?? 'Not selected'}
                            </dd>
                        </div>
                        <div className="flex items-start justify-between gap-4">
                            <dt className="text-muted-foreground">Reason</dt>
                            <dd className="text-right font-medium">
                                {selectedReason?.name ?? 'Not selected'}
                            </dd>
                        </div>
                        <div className="flex items-start justify-between gap-4">
                            <dt className="text-muted-foreground">
                                Occurred at
                            </dt>
                            <dd className="text-right font-medium">
                                {formatEnteredOccurrence(occurredAt, timezone)}
                            </dd>
                        </div>
                    </dl>

                    {selectedItem &&
                        selectedUnit &&
                        selectedUnit.symbol !== selectedItem.baseUnitSymbol && (
                            <p className="text-xs text-muted-foreground">
                                The existing server-side quantity conversion
                                will convert this entered unit to{' '}
                                {selectedItem.baseUnitSymbol} before the
                                authoritative stock movement is recorded.
                            </p>
                        )}

                    <div className="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => setConfirmationOpen(false)}
                        >
                            Go back
                        </Button>
                        <Button
                            type="button"
                            variant="destructive"
                            onClick={confirmWasteSubmission}
                        >
                            Confirm and reduce stock
                        </Button>
                    </div>
                </DialogContent>
            </Dialog>
        </>
    );
}

/** Create a reusable Waste reason without leaving the current report context. */
function CreateWasteReasonDialog() {
    const dialog = useGuardedDialog(
        'Discard the new waste reason you entered?',
    );

    return (
        <Dialog open={dialog.open} onOpenChange={dialog.onOpenChange}>
            <DialogTrigger asChild>
                <Button size="sm">
                    <Plus className="size-4" aria-hidden="true" />
                    Add reason
                </Button>
            </DialogTrigger>

            <DialogContent className="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>Add waste reason</DialogTitle>
                    <DialogDescription>
                        Add a reusable reason for future Waste records.
                        Historical waste records are never rewritten.
                    </DialogDescription>
                </DialogHeader>

                <div onChange={dialog.markDirty}>
                    <Form
                        {...WasteReasonController.store.form()}
                        errorBag="createWasteReason"
                        resetOnSuccess
                        options={{ preserveScroll: true }}
                        onSuccess={dialog.closeAfterSuccess}
                    >
                        {({ errors, processing }) => (
                            <div className="grid gap-5">
                                <input type="hidden" name="_modal" value="1" />

                                <Field
                                    id="modal-waste-reason-name"
                                    label={requiredLabel('Reason name')}
                                    error={errors.name}
                                >
                                    <Input
                                        name="name"
                                        maxLength={100}
                                        placeholder="e.g. Spoilage"
                                        autoFocus
                                        required
                                    />
                                </Field>

                                <div className="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
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
                                        {processing ? 'Adding…' : 'Add reason'}
                                    </Button>
                                </div>
                            </div>
                        )}
                    </Form>
                </div>
            </DialogContent>
        </Dialog>
    );
}

/** Confirm Waste reason lifecycle changes without rewriting historical evidence. */
function WasteReasonStatusDialog({ reason }: { reason: WasteReason }) {
    const [open, setOpen] = useState(false);
    const nextActive = !reason.active;

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button type="button" variant="ghost" size="sm">
                    {reason.active ? 'Deactivate' : 'Activate'}
                </Button>
            </DialogTrigger>

            <DialogContent className="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>
                        {reason.active
                            ? 'Deactivate waste reason?'
                            : 'Activate waste reason?'}
                    </DialogTitle>
                    <DialogDescription>
                        {reason.active
                            ? `${reason.name} will no longer be selectable for new waste records. Historical waste records and reports remain unchanged.`
                            : `${reason.name} will become selectable for new waste records again. Historical waste records remain unchanged.`}
                    </DialogDescription>
                </DialogHeader>

                <Form
                    {...WasteReasonController.update.form(reason.id)}
                    errorBag={`updateWasteReason${reason.id}`}
                    options={{ preserveScroll: true }}
                    onSuccess={() => setOpen(false)}
                >
                    {({ errors, processing }) => (
                        <div className="grid gap-4">
                            <input type="hidden" name="_modal" value="1" />
                            <input
                                type="hidden"
                                name="active"
                                value={nextActive ? '1' : '0'}
                            />

                            <InputError message={errors.active} />

                            <div className="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                                <Button
                                    type="button"
                                    variant="outline"
                                    disabled={processing}
                                    onClick={() => setOpen(false)}
                                >
                                    Cancel
                                </Button>
                                <Button
                                    type="submit"
                                    variant={
                                        reason.active
                                            ? 'destructive'
                                            : 'default'
                                    }
                                    disabled={processing}
                                >
                                    {processing
                                        ? reason.active
                                            ? 'Deactivating…'
                                            : 'Activating…'
                                        : reason.active
                                          ? 'Deactivate reason'
                                          : 'Activate reason'}
                                </Button>
                            </div>
                        </div>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

/** Render Waste Reason administration independently from operational stock entry. */
function WasteReasonsPanel({ wasteReasons }: { wasteReasons: WasteReason[] }) {
    return (
        <section
            className="overflow-hidden rounded-xl border border-border bg-card"
            aria-labelledby="waste-reasons-title"
        >
            <div className="flex min-h-16 flex-wrap items-center justify-between gap-3 border-b border-border px-4 py-3">
                <div>
                    <h2 id="waste-reasons-title" className="font-semibold">
                        Waste reason administration
                    </h2>
                    <p className="mt-0.5 text-xs text-muted-foreground">
                        Control which retained reasons can be selected for new
                        Waste records.
                    </p>
                </div>

                <CreateWasteReasonDialog />
            </div>

            {wasteReasons.length === 0 ? (
                <EmptyState
                    className="px-4 py-10"
                    icon={Tags}
                    title="No waste reasons configured"
                    description="Add an active reason before operational waste can be recorded."
                    action={<CreateWasteReasonDialog />}
                />
            ) : (
                <>
                    <div className="divide-y divide-border sm:hidden">
                        {wasteReasons.map((reason) => (
                            <article
                                key={reason.id}
                                className="flex items-center justify-between gap-4 p-4"
                            >
                                <div className="min-w-0">
                                    <p className="truncate font-medium">
                                        {reason.name}
                                    </p>
                                    <div className="mt-1">
                                        <StatusBadge
                                            label={
                                                reason.active
                                                    ? 'Active'
                                                    : 'Inactive'
                                            }
                                            variant={
                                                reason.active
                                                    ? 'success'
                                                    : 'neutral'
                                            }
                                        />
                                    </div>
                                </div>

                                <WasteReasonStatusDialog reason={reason} />
                            </article>
                        ))}
                    </div>

                    <div className="hidden overflow-x-auto sm:block">
                        <table className="w-full min-w-[440px] text-sm">
                            <caption className="sr-only">
                                Configured Waste reasons and their lifecycle
                                status.
                            </caption>
                            <thead className="border-b border-border bg-muted/40 text-left">
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
                                {wasteReasons.map((reason) => (
                                    <tr
                                        key={reason.id}
                                        className="border-b border-border last:border-b-0"
                                    >
                                        <td className="px-4 py-2.5 font-medium">
                                            {reason.name}
                                        </td>
                                        <td className="px-4 py-2.5">
                                            <StatusBadge
                                                label={
                                                    reason.active
                                                        ? 'Active'
                                                        : 'Inactive'
                                                }
                                                variant={
                                                    reason.active
                                                        ? 'success'
                                                        : 'neutral'
                                                }
                                            />
                                        </td>
                                        <td className="px-4 py-2 text-right">
                                            <WasteReasonStatusDialog
                                                reason={reason}
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
    );
}

/** Render a standard aggregate table for grouped Waste quantities and costs. */
function AggregateTable({
    heading,
    rows,
    summary,
    currency,
    canViewCosts,
}: {
    heading: string;
    rows: {
        key: string | number;
        label: ReactNode;
        recordCount: number;
        quantityTotals: QuantityTotal[];
        totalCost: string | null;
    }[];
    summary: WasteReport['summary'];
    currency: string;
    canViewCosts: boolean;
}) {
    if (rows.length === 0) {
        return (
            <EmptyState
                className="px-4 py-10"
                icon={Search}
                title={`No ${heading.toLowerCase()} breakdown`}
                description="No finalized Waste evidence matches the selected filters."
            />
        );
    }

    return (
        <div className="overflow-x-auto">
            <table className="w-full min-w-[520px] text-sm">
                <thead className="border-b border-border bg-muted/40 text-left">
                    <tr>
                        <th
                            scope="col"
                            className="px-4 py-2.5 font-medium text-muted-foreground"
                        >
                            {heading}
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
                    {rows.map((row) => (
                        <tr
                            key={row.key}
                            className="border-b border-border last:border-b-0 hover:bg-muted/30"
                        >
                            <td className="px-4 py-2.5">{row.label}</td>
                            <td className="px-4 py-2.5 text-right tabular-nums">
                                {row.recordCount.toLocaleString()}
                            </td>
                            <td className="px-4 py-2.5 text-right">
                                <QuantityTotals
                                    totals={row.quantityTotals}
                                    align="right"
                                />
                            </td>
                            {canViewCosts && (
                                <td className="px-4 py-2.5 text-right font-medium whitespace-nowrap tabular-nums">
                                    {row.totalCost === null
                                        ? '—'
                                        : formatCurrency(
                                              row.totalCost,
                                              currency,
                                          )}
                                </td>
                            )}
                        </tr>
                    ))}
                </tbody>
                <tfoot>
                    <tr className="border-t border-border bg-muted/30 font-medium">
                        <td className="px-4 py-2.5">Total</td>
                        <td className="px-4 py-2.5 text-right tabular-nums">
                            {summary.recordCount.toLocaleString()}
                        </td>
                        <td className="px-4 py-2.5 text-right font-semibold">
                            <QuantityTotals
                                totals={summary.quantityTotals}
                                align="right"
                            />
                        </td>
                        {canViewCosts && (
                            <td className="px-4 py-2.5 text-right font-semibold whitespace-nowrap tabular-nums">
                                {summary.totalCost === null
                                    ? '—'
                                    : formatCurrency(
                                          summary.totalCost,
                                          currency,
                                      )}
                            </td>
                        )}
                    </tr>
                </tfoot>
            </table>
        </div>
    );
}

/** Render one immutable Waste record for small screens without losing evidence fields. */
function WasteEvidenceCard({
    row,
    timezone,
    currency,
    canViewCosts,
}: {
    row: WasteRow;
    timezone: string;
    currency: string;
    canViewCosts: boolean;
}) {
    return (
        <article className="space-y-4 p-4">
            <div className="flex items-start justify-between gap-4">
                <div>
                    <p className="font-medium">Waste #{row.recordId}</p>
                    <p className="mt-0.5 text-xs text-muted-foreground">
                        {formatOrganizationDate(row.occurredAt, timezone)}
                    </p>
                </div>
                <StatusBadge label={row.reasonName} variant="warning" />
            </div>

            <div>
                <p className="font-medium">{row.itemName}</p>
                <p className="text-xs text-muted-foreground">
                    <span className="font-mono">{row.itemSku}</span>
                    {' · '}
                    {row.locationName} · {row.storageLocationName}
                </p>
            </div>

            <dl className="grid grid-cols-2 gap-x-4 gap-y-3 text-sm">
                <div>
                    <dt className="text-xs text-muted-foreground">Entered</dt>
                    <dd className="mt-1 font-medium tabular-nums">
                        {formatDecimal(row.quantity)} {row.unitSymbol}
                    </dd>
                </div>
                <div>
                    <dt className="text-xs text-muted-foreground">
                        Base quantity
                    </dt>
                    <dd className="mt-1 font-medium tabular-nums">
                        {formatDecimal(row.baseQuantity)} {row.baseUnitSymbol}
                    </dd>
                </div>
                <div>
                    <dt className="text-xs text-muted-foreground">
                        Recorded by
                    </dt>
                    <dd className="mt-1">{row.recordedBy ?? 'Unknown user'}</dd>
                </div>
                <div>
                    <dt className="text-xs text-muted-foreground">
                        Stock movement
                    </dt>
                    <dd className="mt-1">
                        {row.movementId === null
                            ? 'Unavailable'
                            : `#${row.movementId}`}
                    </dd>
                </div>

                {canViewCosts && (
                    <>
                        <div>
                            <dt className="text-xs text-muted-foreground">
                                Unit cost
                            </dt>
                            <dd className="mt-1 tabular-nums">
                                {row.unitCost === null
                                    ? '—'
                                    : formatCurrency(row.unitCost, currency)}
                            </dd>
                        </div>
                        <div>
                            <dt className="text-xs text-muted-foreground">
                                Waste value
                            </dt>
                            <dd className="mt-1 font-medium tabular-nums">
                                {row.totalCost === null
                                    ? '—'
                                    : formatCurrency(row.totalCost, currency)}
                            </dd>
                        </div>
                    </>
                )}
            </dl>

            {row.notes && (
                <div className="rounded-md bg-muted/50 p-3">
                    <p className="text-xs font-medium">Notes</p>
                    <p className="mt-1 text-sm text-muted-foreground">
                        {row.notes}
                    </p>
                </div>
            )}
        </article>
    );
}

/** Render the Waste operational workspace, analysis, and immutable evidence. */
export default function WasteIndex({
    rows,
    report,
    filters,
    currency,
    timezone,
    canRecord,
    canManageReasons,
    canViewReport,
    canViewCosts,
    wasteReasons,
    recordForm,
    reportOptions,
}: Props) {
    const { organizationContext } = usePage<{
        organizationContext: OrganizationContext;
    }>().props;

    const canExportReports =
        organizationContext.entitlements?.grants['reports.export'] ?? false;
    const reportRows = rows?.data ?? [];
    const showRecordForm = canRecord && recordForm !== null;
    const exportUrl = buildExportUrl(filters);

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

    const activeFilters =
        reportOptions === null
            ? []
            : activeReportFilters(filters, reportOptions);
    const hasActiveFilters = activeFilters.length > 0;

    const reasonRows =
        report?.byReason.map((row) => ({
            key: row.reasonId,
            label: <span className="font-medium">{row.reasonName}</span>,
            recordCount: row.recordCount,
            quantityTotals: row.quantityTotals,
            totalCost: row.totalCost,
        })) ?? [];

    const employeeRows =
        report?.byEmployee.map((row) => ({
            key: row.employeeId ?? 'unknown',
            label: <span className="font-medium">{row.employeeName}</span>,
            recordCount: row.recordCount,
            quantityTotals: row.quantityTotals,
            totalCost: row.totalCost,
        })) ?? [];

    const itemRows =
        report?.byItem.map((row) => ({
            key: row.itemId,
            label: (
                <>
                    <div className="font-medium">{row.itemName}</div>
                    <div className="mt-0.5 text-xs text-muted-foreground">
                        {row.itemSku}
                    </div>
                </>
            ),
            recordCount: row.recordCount,
            quantityTotals: [
                {
                    baseUnitId: row.baseUnitId,
                    quantity: row.totalQuantity,
                    unitSymbol: row.baseUnitSymbol,
                },
            ],
            totalCost: row.totalCost,
        })) ?? [];

    const locationRows =
        report?.byLocation.map((row) => ({
            key: row.locationId,
            label: <span className="font-medium">{row.locationName}</span>,
            recordCount: row.recordCount,
            quantityTotals: row.quantityTotals,
            totalCost: row.totalCost,
        })) ?? [];

    return (
        <>
            <Head title="Waste" />

            <div className="flex flex-1 flex-col gap-8 p-4 sm:p-6">
                <PageHeader
                    title="Waste"
                    description="Record known operational stock loss, administer retained reasons, analyze finalized Waste, and review immutable evidence."
                />

                <nav
                    aria-label="Waste workspace sections"
                    className="flex gap-1 overflow-x-auto border-b border-border px-1"
                >
                    {(showRecordForm || canManageReasons) && (
                        <a
                            href="#waste-operations-title"
                            className="border-b-2 border-transparent px-3 py-2 text-sm font-medium whitespace-nowrap text-muted-foreground underline-offset-4 hover:border-primary hover:text-foreground hover:underline focus-visible:rounded-sm focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                        >
                            Operations
                        </a>
                    )}
                    {canViewReport &&
                        reportOptions !== null &&
                        report !== null && (
                            <>
                                <a
                                    href="#waste-report-overview-title"
                                    className="border-b-2 border-transparent px-3 py-2 text-sm font-medium whitespace-nowrap text-muted-foreground underline-offset-4 hover:border-primary hover:text-foreground hover:underline focus-visible:rounded-sm focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                >
                                    Report
                                </a>
                                <a
                                    href="#waste-breakdown-analysis-title"
                                    className="border-b-2 border-transparent px-3 py-2 text-sm font-medium whitespace-nowrap text-muted-foreground underline-offset-4 hover:border-primary hover:text-foreground hover:underline focus-visible:rounded-sm focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                >
                                    Breakdowns
                                </a>
                                <a
                                    href="#waste-evidence-title"
                                    className="border-b-2 border-transparent px-3 py-2 text-sm font-medium whitespace-nowrap text-muted-foreground underline-offset-4 hover:border-primary hover:text-foreground hover:underline focus-visible:rounded-sm focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                >
                                    Evidence
                                </a>
                            </>
                        )}
                </nav>

                {(showRecordForm || canManageReasons) && (
                    <section
                        className="grid gap-4"
                        aria-labelledby="waste-operations-title"
                    >
                        <div>
                            <h2
                                id="waste-operations-title"
                                className="text-lg font-semibold"
                            >
                                Waste operations
                            </h2>
                            <p className="mt-0.5 text-sm text-muted-foreground">
                                Operational stock entry and reason
                                administration remain separate responsibilities.
                            </p>
                        </div>

                        <div
                            className={
                                showRecordForm && canManageReasons
                                    ? 'grid gap-4 xl:grid-cols-[minmax(0,2fr)_minmax(20rem,1fr)]'
                                    : 'grid gap-4'
                            }
                        >
                            {showRecordForm && (
                                <section
                                    className="overflow-hidden rounded-xl border border-border bg-card"
                                    aria-labelledby="record-waste-title"
                                >
                                    <div className="flex items-start gap-3 border-b border-border px-5 py-4">
                                        <div className="flex size-9 shrink-0 items-center justify-center rounded-lg bg-muted">
                                            <ClipboardList
                                                className="size-4 text-muted-foreground"
                                                aria-hidden="true"
                                            />
                                        </div>
                                        <div>
                                            <h3
                                                id="record-waste-title"
                                                className="font-semibold"
                                            >
                                                Record Waste
                                            </h3>
                                            <p className="mt-0.5 text-xs text-muted-foreground">
                                                This is a dedicated stock-entry
                                                workspace. A confirmation is
                                                required before inventory is
                                                reduced.
                                            </p>
                                        </div>
                                    </div>

                                    <RecordWasteForm
                                        recordForm={recordForm}
                                        timezone={timezone}
                                    />
                                </section>
                            )}

                            <div className="grid content-start gap-4">
                                {canManageReasons && (
                                    <WasteReasonsPanel
                                        wasteReasons={wasteReasons}
                                    />
                                )}

                                {showRecordForm && (
                                    <aside
                                        className="rounded-xl border border-info-border bg-info-subtle p-4"
                                        aria-labelledby="inventory-impact-title"
                                    >
                                        <div className="flex items-start gap-3">
                                            <Info
                                                className="mt-0.5 size-4 shrink-0 text-info-foreground"
                                                aria-hidden="true"
                                            />
                                            <div>
                                                <h3
                                                    id="inventory-impact-title"
                                                    className="text-sm font-semibold"
                                                >
                                                    Inventory impact
                                                </h3>
                                                <p className="mt-1 text-xs text-muted-foreground">
                                                    Confirmed Waste immediately
                                                    records an outbound stock
                                                    movement. The resulting
                                                    Waste record, quantity,
                                                    snapshotted cost, recorder,
                                                    reason, and stock movement
                                                    remain traceable evidence.
                                                </p>
                                            </div>
                                        </div>
                                    </aside>
                                )}
                            </div>
                        </div>
                    </section>
                )}

                {canViewReport && reportOptions !== null && report !== null && (
                    <>
                        <section
                            className="grid gap-4"
                            aria-labelledby="waste-report-overview-title"
                        >
                            <div className="flex flex-wrap items-end justify-between gap-3">
                                <div>
                                    <h2
                                        id="waste-report-overview-title"
                                        className="text-lg font-semibold"
                                    >
                                        Report overview
                                    </h2>
                                    <p className="mt-0.5 text-sm text-muted-foreground">
                                        Filters, summary metrics, and export
                                        apply to the same finalized immutable
                                        Waste evidence.
                                    </p>
                                </div>

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
                            </div>

                            <Form
                                action={WasteController.index().url}
                                method="get"
                            >
                                {({ errors, processing }) => (
                                    <FilterToolbar>
                                        <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-6">
                                            <Field
                                                id="report_location_id"
                                                label="Location"
                                                error={errors.location_id}
                                            >
                                                <NativeSelect
                                                    name="location_id"
                                                    defaultValue={
                                                        filters.locationId?.toString() ??
                                                        ''
                                                    }
                                                >
                                                    <option value="">
                                                        All locations
                                                    </option>
                                                    {reportOptions.locations.map(
                                                        (option) => (
                                                            <option
                                                                key={option.id}
                                                                value={
                                                                    option.id
                                                                }
                                                            >
                                                                {option.name}
                                                            </option>
                                                        ),
                                                    )}
                                                </NativeSelect>
                                            </Field>

                                            <Field
                                                id="report_inventory_category_id"
                                                label="Category"
                                                error={
                                                    errors.inventory_category_id
                                                }
                                            >
                                                <NativeSelect
                                                    name="inventory_category_id"
                                                    defaultValue={
                                                        filters.inventoryCategoryId?.toString() ??
                                                        ''
                                                    }
                                                >
                                                    <option value="">
                                                        All categories
                                                    </option>
                                                    {reportOptions.inventoryCategories.map(
                                                        (option) => (
                                                            <option
                                                                key={option.id}
                                                                value={
                                                                    option.id
                                                                }
                                                            >
                                                                {option.name}
                                                            </option>
                                                        ),
                                                    )}
                                                </NativeSelect>
                                            </Field>

                                            <Field
                                                id="report_inventory_item_id"
                                                label="Item"
                                                error={errors.inventory_item_id}
                                            >
                                                <NativeSelect
                                                    name="inventory_item_id"
                                                    defaultValue={
                                                        filters.inventoryItemId?.toString() ??
                                                        ''
                                                    }
                                                >
                                                    <option value="">
                                                        All items
                                                    </option>
                                                    {reportOptions.inventoryItems.map(
                                                        (option) => (
                                                            <option
                                                                key={option.id}
                                                                value={
                                                                    option.id
                                                                }
                                                            >
                                                                {option.name} (
                                                                {option.sku})
                                                            </option>
                                                        ),
                                                    )}
                                                </NativeSelect>
                                            </Field>

                                            <Field
                                                id="report_waste_reason_id"
                                                label="Reason"
                                                error={errors.waste_reason_id}
                                            >
                                                <NativeSelect
                                                    name="waste_reason_id"
                                                    defaultValue={
                                                        filters.wasteReasonId?.toString() ??
                                                        ''
                                                    }
                                                >
                                                    <option value="">
                                                        All reasons
                                                    </option>
                                                    {reportOptions.wasteReasons.map(
                                                        (option) => (
                                                            <option
                                                                key={option.id}
                                                                value={
                                                                    option.id
                                                                }
                                                            >
                                                                {option.name}
                                                            </option>
                                                        ),
                                                    )}
                                                </NativeSelect>
                                            </Field>

                                            <Field
                                                id="report_from"
                                                label="From"
                                                error={errors.from}
                                            >
                                                <Input
                                                    name="from"
                                                    type="date"
                                                    defaultValue={
                                                        filters.from ?? ''
                                                    }
                                                />
                                            </Field>

                                            <Field
                                                id="report_to"
                                                label="To"
                                                error={errors.to}
                                            >
                                                <Input
                                                    name="to"
                                                    type="date"
                                                    defaultValue={
                                                        filters.to ?? ''
                                                    }
                                                />
                                            </Field>
                                        </div>

                                        <div className="mt-4 flex flex-wrap gap-2">
                                            <Button
                                                type="submit"
                                                disabled={processing}
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
                                                asChild
                                            >
                                                <Link
                                                    href={WasteController.index()}
                                                >
                                                    <RotateCcw
                                                        className="size-4"
                                                        aria-hidden="true"
                                                    />
                                                    Reset
                                                </Link>
                                            </Button>
                                        </div>

                                        {Object.keys(errors).length > 0 && (
                                            <div
                                                role="alert"
                                                className="mt-4 rounded-md border border-destructive/30 bg-destructive/10 px-3 py-2 text-sm text-destructive"
                                            >
                                                One or more Waste report filters
                                                are invalid. Review the
                                                associated fields or reset the
                                                filters.
                                            </div>
                                        )}

                                        <div
                                            className="mt-4 border-t border-border pt-4"
                                            aria-label="Active report filters"
                                        >
                                            <p className="text-xs font-medium text-muted-foreground">
                                                Active filters
                                            </p>

                                            {hasActiveFilters ? (
                                                <div className="mt-2 flex flex-wrap gap-2">
                                                    {activeFilters.map(
                                                        (filter) => (
                                                            <Button
                                                                key={filter.key}
                                                                variant="outline"
                                                                size="sm"
                                                                className="h-7 gap-1 px-2 text-xs"
                                                                asChild
                                                            >
                                                                <Link
                                                                    href={buildReportUrl(
                                                                        filters,
                                                                        {
                                                                            [filter.key]:
                                                                                null,
                                                                        },
                                                                    )}
                                                                >
                                                                    {
                                                                        filter.label
                                                                    }
                                                                    :{' '}
                                                                    {
                                                                        filter.value
                                                                    }
                                                                    <X
                                                                        className="size-3"
                                                                        aria-hidden="true"
                                                                    />
                                                                    <span className="sr-only">
                                                                        Remove{' '}
                                                                        {
                                                                            filter.label
                                                                        }{' '}
                                                                        filter
                                                                    </span>
                                                                </Link>
                                                            </Button>
                                                        ),
                                                    )}
                                                </div>
                                            ) : (
                                                <p className="mt-1 text-sm text-muted-foreground">
                                                    No filters applied. Showing
                                                    all available Waste
                                                    evidence.
                                                </p>
                                            )}
                                        </div>
                                    </FilterToolbar>
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
                                    description="Finalized entries matching the active filters"
                                    icon={ClipboardList}
                                    tone="blue"
                                />
                                <DashboardMetricCard
                                    title="Waste quantity"
                                    value={
                                        <QuantityTotals
                                            totals={
                                                report.summary.quantityTotals
                                            }
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
                                        description="Authorized snapshotted cost of filtered Waste"
                                        icon={CircleDollarSign}
                                        tone="emerald"
                                    />
                                )}
                                <DashboardMetricCard
                                    title="Top reason"
                                    value={topReason?.reasonName ?? '—'}
                                    description={
                                        topReason === null
                                            ? 'No matching Waste records'
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
                        </section>

                        <section
                            className="grid gap-4"
                            aria-labelledby="waste-breakdown-analysis-title"
                        >
                            <div>
                                <h2
                                    id="waste-breakdown-analysis-title"
                                    className="text-lg font-semibold"
                                >
                                    Breakdown analysis
                                </h2>
                                <p className="mt-0.5 text-sm text-muted-foreground">
                                    Open only the grouping needed for the
                                    current analysis to reduce repeated table
                                    density.
                                </p>
                            </div>

                            <BreakdownDisclosure
                                title="Waste by reason"
                                description="Quantity and value grouped by retained Waste reason."
                                count={reasonRows.length}
                                defaultOpen
                            >
                                <AggregateTable
                                    heading="Reason"
                                    rows={reasonRows}
                                    summary={report.summary}
                                    currency={currency}
                                    canViewCosts={canViewCosts}
                                />
                            </BreakdownDisclosure>

                            <BreakdownDisclosure
                                title="Waste by employee"
                                description="Quantity and value grouped by the user who recorded the Waste."
                                count={employeeRows.length}
                            >
                                <AggregateTable
                                    heading="Employee"
                                    rows={employeeRows}
                                    summary={report.summary}
                                    currency={currency}
                                    canViewCosts={canViewCosts}
                                />
                            </BreakdownDisclosure>

                            <BreakdownDisclosure
                                title="Waste by item"
                                description="Quantity and value grouped by inventory item."
                                count={itemRows.length}
                            >
                                <AggregateTable
                                    heading="Item"
                                    rows={itemRows}
                                    summary={report.summary}
                                    currency={currency}
                                    canViewCosts={canViewCosts}
                                />
                            </BreakdownDisclosure>

                            <BreakdownDisclosure
                                title="Waste by location"
                                description="Quantity and value grouped by organization location."
                                count={locationRows.length}
                            >
                                <AggregateTable
                                    heading="Location"
                                    rows={locationRows}
                                    summary={report.summary}
                                    currency={currency}
                                    canViewCosts={canViewCosts}
                                />
                            </BreakdownDisclosure>
                        </section>

                        <section
                            className="overflow-hidden rounded-xl border border-border bg-card"
                            aria-labelledby="waste-evidence-title"
                        >
                            <div className="flex min-h-14 flex-wrap items-center justify-between gap-3 border-b border-border px-4 py-3">
                                <div>
                                    <h2
                                        id="waste-evidence-title"
                                        className="text-sm font-semibold"
                                    >
                                        Immutable Waste evidence
                                    </h2>
                                    <p className="mt-0.5 text-xs text-muted-foreground">
                                        Detailed finalized evidence. Occurred
                                        timestamps are shown in {timezone}.
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

                            {reportRows.length === 0 ? (
                                <EmptyState
                                    className="px-4 py-14"
                                    icon={Search}
                                    title={
                                        hasActiveFilters
                                            ? 'No matching Waste evidence'
                                            : 'No Waste evidence yet'
                                    }
                                    description={
                                        hasActiveFilters
                                            ? 'No finalized Waste records match the active filters. Adjust or reset the filters.'
                                            : 'Finalized Waste records will appear here after operational Waste is recorded.'
                                    }
                                    action={
                                        hasActiveFilters ? (
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                asChild
                                            >
                                                <Link
                                                    href={WasteController.index()}
                                                >
                                                    <RotateCcw
                                                        className="size-4"
                                                        aria-hidden="true"
                                                    />
                                                    Reset filters
                                                </Link>
                                            </Button>
                                        ) : undefined
                                    }
                                />
                            ) : (
                                <>
                                    <div className="divide-y divide-border md:hidden">
                                        {reportRows.map((row) => (
                                            <WasteEvidenceCard
                                                key={row.recordId}
                                                row={row}
                                                timezone={timezone}
                                                currency={currency}
                                                canViewCosts={canViewCosts}
                                            />
                                        ))}
                                    </div>

                                    <div className="hidden overflow-x-auto md:block">
                                        <table className="w-full min-w-[1120px] text-sm">
                                            <caption className="sr-only">
                                                Immutable Waste evidence with
                                                occurrence, inventory context,
                                                entered and base quantity,
                                                recorder, stock movement, and
                                                authorized cost snapshots.
                                            </caption>
                                            <thead className="border-b border-border bg-muted/40 text-left">
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
                                                        Entered
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
                                                {reportRows.map((row) => (
                                                    <tr
                                                        key={row.recordId}
                                                        className="border-b border-border align-top last:border-b-0 hover:bg-muted/30"
                                                    >
                                                        <td className="px-4 py-3">
                                                            <div className="font-medium">
                                                                Waste #
                                                                {row.recordId}
                                                            </div>
                                                            <div className="mt-0.5 text-xs text-muted-foreground">
                                                                {row.movementId ===
                                                                null
                                                                    ? 'No linked movement'
                                                                    : `Movement #${row.movementId}`}
                                                            </div>
                                                        </td>
                                                        <td className="px-4 py-3 whitespace-nowrap">
                                                            {formatOrganizationDate(
                                                                row.occurredAt,
                                                                timezone,
                                                            )}
                                                        </td>
                                                        <td className="px-4 py-3">
                                                            <div>
                                                                {
                                                                    row.locationName
                                                                }
                                                            </div>
                                                            <div className="mt-0.5 text-xs text-muted-foreground">
                                                                {
                                                                    row.storageLocationName
                                                                }
                                                            </div>
                                                        </td>
                                                        <td className="px-4 py-3">
                                                            <div className="font-medium">
                                                                {row.itemName}
                                                            </div>
                                                            <div className="mt-0.5 font-mono text-xs text-muted-foreground">
                                                                {row.itemSku}
                                                            </div>
                                                        </td>
                                                        <td className="px-4 py-3">
                                                            <StatusBadge
                                                                label={
                                                                    row.reasonName
                                                                }
                                                                variant="warning"
                                                            />
                                                            {row.notes && (
                                                                <p className="mt-2 max-w-xs text-xs text-muted-foreground">
                                                                    {row.notes}
                                                                </p>
                                                            )}
                                                        </td>
                                                        <td className="px-4 py-3 text-right font-medium whitespace-nowrap tabular-nums">
                                                            {formatDecimal(
                                                                row.quantity,
                                                            )}{' '}
                                                            {row.unitSymbol}
                                                        </td>
                                                        <td className="px-4 py-3 text-right whitespace-nowrap tabular-nums">
                                                            {formatDecimal(
                                                                row.baseQuantity,
                                                            )}{' '}
                                                            {row.baseUnitSymbol}
                                                        </td>
                                                        <td className="px-4 py-3">
                                                            {row.recordedBy ??
                                                                'Unknown user'}
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
                                                                <td className="px-4 py-3 text-right font-medium whitespace-nowrap tabular-nums">
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
                                                ))}
                                            </tbody>
                                        </table>
                                    </div>
                                </>
                            )}

                            {rows !== null && (
                                <PaginationControls
                                    currentPage={rows.current_page}
                                    from={rows.from}
                                    to={rows.to}
                                    total={rows.total}
                                    lastPage={rows.last_page}
                                    previousPageUrl={rows.prev_page_url}
                                    nextPageUrl={rows.next_page_url}
                                    itemLabel="records"
                                    preserveScroll
                                />
                            )}
                        </section>
                    </>
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
