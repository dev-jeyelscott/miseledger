import { Form, Head, Link } from '@inertiajs/react';
import { useState } from 'react';
import WasteController from '@/actions/App/Http/Controllers/Inventory/WasteController';
import WasteReasonController from '@/actions/App/Http/Controllers/Inventory/WasteReasonController';
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

const formatDecimal = (value: string): string => {
    const [rawInteger, rawDecimal = ''] = value.trim().split('.');
    const groupedInteger = rawInteger.replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    const decimal = rawDecimal.replace(/0+$/, '');

    return `${groupedInteger}${decimal === '' ? '' : `.${decimal}`}`;
};

const formatDate = (value: string): string => new Date(value).toLocaleString();

const QuantityTotals = ({ totals }: { totals: QuantityTotal[] }) => {
    if (totals.length === 0) {
        return <span>—</span>;
    }

    return (
        <div className="grid gap-1">
            {totals.map((total) => (
                <div key={total.baseUnitId}>
                    {formatDecimal(total.quantity)} {total.unitSymbol}
                </div>
            ))}
        </div>
    );
};

const ErrorText = ({ message }: { message?: string }) =>
    message ? <p className="text-sm text-destructive">{message}</p> : null;

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

            <div className="flex flex-1 flex-col gap-8 p-4">
                <div>
                    <h1 className="text-2xl font-semibold">Waste</h1>
                    <p className="mt-1 text-sm text-muted-foreground">
                        Record known operational stock loss separately from
                        physical-count variance.
                    </p>
                </div>

                {canRecord && recordForm !== null && (
                    <section className="rounded-xl border border-sidebar-border/70 p-5 dark:border-sidebar-border">
                        <div className="mb-5">
                            <h2 className="text-lg font-semibold">
                                Record waste
                            </h2>
                            <p className="text-sm text-muted-foreground">
                                Recording waste immediately decreases stock.
                            </p>
                        </div>

                        <Form
                            action={WasteController.store().url}
                            method="post"
                        >
                            {({ errors, processing }) => (
                                <div className="grid gap-5">
                                    <input
                                        type="hidden"
                                        name="operation_id"
                                        value={recordForm.operationId}
                                    />

                                    <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                                        <div className="grid gap-2">
                                            <Label htmlFor="location_id">
                                                Location
                                            </Label>
                                            <select
                                                id="location_id"
                                                name="location_id"
                                                value={recordLocationId}
                                                onChange={(event) =>
                                                    handleRecordLocationChange(
                                                        event.target.value,
                                                    )
                                                }
                                                className="h-9 rounded-md border bg-background px-3 text-sm"
                                                required
                                            >
                                                <option value="">
                                                    Select location
                                                </option>
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
                                            </select>
                                            <ErrorText
                                                message={errors.location_id}
                                            />
                                        </div>

                                        <div className="grid gap-2">
                                            <Label htmlFor="storage_location_id">
                                                Storage location
                                            </Label>
                                            <select
                                                id="storage_location_id"
                                                name="storage_location_id"
                                                value={recordStorageLocationId}
                                                onChange={(event) =>
                                                    setRecordStorageLocationId(
                                                        event.target.value,
                                                    )
                                                }
                                                disabled={
                                                    recordLocationId === ''
                                                }
                                                className="h-9 rounded-md border bg-background px-3 text-sm"
                                                required
                                            >
                                                <option value="">
                                                    {recordLocationId === ''
                                                        ? 'Select location first'
                                                        : 'Select storage'}
                                                </option>
                                                {selectedStorageLocationOptions.map(
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
                                            <ErrorText
                                                message={
                                                    errors.storage_location_id
                                                }
                                            />
                                        </div>

                                        <div className="grid gap-2">
                                            <Label htmlFor="inventory_item_id">
                                                Inventory item
                                            </Label>
                                            <select
                                                id="inventory_item_id"
                                                name="inventory_item_id"
                                                value={recordInventoryItemId}
                                                onChange={(event) =>
                                                    handleRecordInventoryItemChange(
                                                        event.target.value,
                                                    )
                                                }
                                                className="h-9 rounded-md border bg-background px-3 text-sm"
                                                required
                                            >
                                                <option value="">
                                                    Select item
                                                </option>
                                                {recordForm.inventoryItemOptions.map(
                                                    (option) => (
                                                        <option
                                                            key={option.id}
                                                            value={option.id}
                                                        >
                                                            {option.name} (
                                                            {option.sku})
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
                                            <Label htmlFor="waste_reason_id">
                                                Reason
                                            </Label>
                                            <select
                                                id="waste_reason_id"
                                                name="waste_reason_id"
                                                className="h-9 rounded-md border bg-background px-3 text-sm"
                                                required
                                            >
                                                <option value="">
                                                    Select reason
                                                </option>
                                                {recordForm.reasonOptions.map(
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
                                            <ErrorText
                                                message={errors.waste_reason_id}
                                            />
                                        </div>

                                        <div className="grid gap-2">
                                            <Label htmlFor="quantity">
                                                Quantity
                                            </Label>
                                            <Input
                                                id="quantity"
                                                name="quantity"
                                                type="number"
                                                min="0.000001"
                                                step="0.000001"
                                                required
                                            />
                                            <ErrorText
                                                message={errors.quantity}
                                            />
                                        </div>

                                        <div className="grid gap-2">
                                            <Label htmlFor="unit_id">
                                                Unit
                                            </Label>
                                            <select
                                                id="unit_id"
                                                name="unit_id"
                                                value={recordUnitId}
                                                onChange={(event) =>
                                                    setRecordUnitId(
                                                        event.target.value,
                                                    )
                                                }
                                                disabled={
                                                    recordInventoryItemId === ''
                                                }
                                                className="h-9 rounded-md border bg-background px-3 text-sm"
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
                                                            key={option.id}
                                                            value={option.id}
                                                        >
                                                            {option.name} (
                                                            {option.symbol})
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
                                            <Label htmlFor="occurred_at">
                                                Occurred at
                                            </Label>
                                            <Input
                                                id="occurred_at"
                                                name="occurred_at"
                                                type="datetime-local"
                                                defaultValue={
                                                    recordForm.defaultOccurredAt
                                                }
                                                required
                                            />
                                            <ErrorText
                                                message={errors.occurred_at}
                                            />
                                        </div>
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="notes">Notes</Label>
                                        <textarea
                                            id="notes"
                                            name="notes"
                                            rows={3}
                                            maxLength={2000}
                                            className="rounded-md border bg-background px-3 py-2 text-sm"
                                        />
                                        <ErrorText message={errors.notes} />
                                        <ErrorText
                                            message={errors.operation_id}
                                        />
                                    </div>

                                    {recordForm.reasonOptions.length === 0 && (
                                        <p className="text-sm text-destructive">
                                            Create an active waste reason before
                                            recording waste.
                                        </p>
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
                                            {processing
                                                ? 'Recording...'
                                                : 'Record waste'}
                                        </Button>
                                    </div>
                                </div>
                            )}
                        </Form>
                    </section>
                )}

                {canManageReasons && (
                    <section className="rounded-xl border border-sidebar-border/70 p-5 dark:border-sidebar-border">
                        <div className="mb-5">
                            <h2 className="text-lg font-semibold">
                                Waste reasons
                            </h2>
                            <p className="text-sm text-muted-foreground">
                                Suggested reasons: Spoilage, Expired, Damaged,
                                Preparation Error, Overproduction, Customer
                                Complaint, and Other.
                            </p>
                        </div>

                        <Form
                            action={WasteReasonController.store().url}
                            method="post"
                        >
                            {({ errors, processing }) => (
                                <div className="mb-6 flex max-w-xl items-start gap-3">
                                    <div className="flex-1">
                                        <Input
                                            name="name"
                                            placeholder="New waste reason"
                                            maxLength={100}
                                            required
                                        />
                                        <ErrorText message={errors.name} />
                                    </div>
                                    <Button type="submit" disabled={processing}>
                                        Add reason
                                    </Button>
                                </div>
                            )}
                        </Form>

                        <div className="divide-y rounded-lg border">
                            {wasteReasons.length === 0 ? (
                                <p className="p-4 text-sm text-muted-foreground">
                                    No waste reasons configured.
                                </p>
                            ) : (
                                wasteReasons.map((reason) => (
                                    <div
                                        key={reason.id}
                                        className="flex items-center justify-between gap-4 p-4"
                                    >
                                        <div>
                                            <div className="font-medium">
                                                {reason.name}
                                            </div>
                                            <div className="text-xs text-muted-foreground">
                                                {reason.active
                                                    ? 'Active'
                                                    : 'Inactive'}
                                            </div>
                                        </div>

                                        <Form
                                            action={
                                                WasteReasonController.update(
                                                    reason.id,
                                                ).url
                                            }
                                            method="put"
                                        >
                                            {({ processing }) => (
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
                                                        variant="outline"
                                                        disabled={processing}
                                                    >
                                                        {reason.active
                                                            ? 'Deactivate'
                                                            : 'Activate'}
                                                    </Button>
                                                </>
                                            )}
                                        </Form>
                                    </div>
                                ))
                            )}
                        </div>
                    </section>
                )}

                {canViewReport && reportOptions !== null && report !== null && (
                    <section className="grid gap-5">
                        <div>
                            <h2 className="text-lg font-semibold">
                                Waste report
                            </h2>
                            <p className="text-sm text-muted-foreground">
                                Summarize and trace finalized waste by period,
                                location, category, item, reason, and employee.
                            </p>
                        </div>

                        <Form action={WasteController.index().url} method="get">
                            {({ processing }) => (
                                <div className="grid gap-4 rounded-xl border border-sidebar-border/70 p-5 md:grid-cols-2 lg:grid-cols-4 xl:grid-cols-7 dark:border-sidebar-border">
                                    <div className="grid gap-2">
                                        <Label>Location</Label>
                                        <select
                                            name="location_id"
                                            defaultValue={
                                                filters.locationId?.toString() ??
                                                ''
                                            }
                                            className="h-9 rounded-md border bg-background px-3 text-sm"
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
                                        <Label>Category</Label>
                                        <select
                                            name="inventory_category_id"
                                            defaultValue={
                                                filters.inventoryCategoryId?.toString() ??
                                                ''
                                            }
                                            className="h-9 rounded-md border bg-background px-3 text-sm"
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
                                        <Label>Item</Label>
                                        <select
                                            name="inventory_item_id"
                                            defaultValue={
                                                filters.inventoryItemId?.toString() ??
                                                ''
                                            }
                                            className="h-9 rounded-md border bg-background px-3 text-sm"
                                        >
                                            <option value="">All items</option>
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
                                        <Label>Reason</Label>
                                        <select
                                            name="waste_reason_id"
                                            defaultValue={
                                                filters.wasteReasonId?.toString() ??
                                                ''
                                            }
                                            className="h-9 rounded-md border bg-background px-3 text-sm"
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

                                    <div className="flex items-end gap-2">
                                        <Button
                                            type="submit"
                                            disabled={processing}
                                        >
                                            Apply
                                        </Button>
                                        <Button variant="outline" asChild>
                                            <Link
                                                href={WasteController.index()}
                                            >
                                                Clear
                                            </Link>
                                        </Button>
                                    </div>
                                </div>
                            )}
                        </Form>

                        <section className="grid gap-3">
                            <div>
                                <h3 className="font-semibold">Waste Summary</h3>
                                <p className="text-sm text-muted-foreground">
                                    Totals for the selected report filters.
                                </p>
                            </div>

                            <div
                                className={`grid gap-4 ${
                                    canViewCosts
                                        ? 'md:grid-cols-3'
                                        : 'md:grid-cols-2'
                                }`}
                            >
                                <div className="rounded-xl border border-sidebar-border/70 p-5 dark:border-sidebar-border">
                                    <div className="text-sm text-muted-foreground">
                                        Waste records
                                    </div>
                                    <div className="mt-2 text-2xl font-semibold">
                                        {report.summary.recordCount.toLocaleString()}
                                    </div>
                                </div>

                                <div className="rounded-xl border border-sidebar-border/70 p-5 dark:border-sidebar-border">
                                    <div className="text-sm text-muted-foreground">
                                        Waste quantity
                                    </div>
                                    <div className="mt-2 font-semibold">
                                        <QuantityTotals
                                            totals={
                                                report.summary.quantityTotals
                                            }
                                        />
                                    </div>
                                </div>

                                {canViewCosts && (
                                    <div className="rounded-xl border border-sidebar-border/70 p-5 dark:border-sidebar-border">
                                        <div className="text-sm text-muted-foreground">
                                            Waste value
                                        </div>
                                        <div className="mt-2 text-2xl font-semibold">
                                            {currency}{' '}
                                            {formatDecimal(
                                                report.summary.totalCost ??
                                                    '0.0000',
                                            )}
                                        </div>
                                    </div>
                                )}
                            </div>
                        </section>

                        <section className="grid gap-3">
                            <div>
                                <h3 className="font-semibold">
                                    Waste by Reason
                                </h3>
                                <p className="text-sm text-muted-foreground">
                                    Quantity and value grouped by retained waste
                                    reason.
                                </p>
                            </div>

                            <div className="overflow-x-auto rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                                <table className="w-full text-sm">
                                    <thead className="border-b text-left">
                                        <tr>
                                            <th className="px-4 py-3">
                                                Reason
                                            </th>
                                            <th className="px-4 py-3">
                                                Records
                                            </th>
                                            <th className="px-4 py-3">
                                                Quantity
                                            </th>
                                            {canViewCosts && (
                                                <th className="px-4 py-3 text-right">
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
                                                    className="px-4 py-8 text-center text-muted-foreground"
                                                >
                                                    No waste records match the
                                                    selected filters.
                                                </td>
                                            </tr>
                                        ) : (
                                            report.byReason.map((row) => (
                                                <tr
                                                    key={row.reasonId}
                                                    className="border-b last:border-b-0"
                                                >
                                                    <td className="px-4 py-3 font-medium">
                                                        {row.reasonName}
                                                    </td>
                                                    <td className="px-4 py-3">
                                                        {row.recordCount.toLocaleString()}
                                                    </td>
                                                    <td className="px-4 py-3">
                                                        <QuantityTotals
                                                            totals={
                                                                row.quantityTotals
                                                            }
                                                        />
                                                    </td>
                                                    {canViewCosts && (
                                                        <td className="px-4 py-3 text-right font-medium">
                                                            {currency}{' '}
                                                            {formatDecimal(
                                                                row.totalCost ??
                                                                    '0.0000',
                                                            )}
                                                        </td>
                                                    )}
                                                </tr>
                                            ))
                                        )}
                                    </tbody>
                                </table>
                            </div>
                        </section>

                        <section className="grid gap-3">
                            <div>
                                <h3 className="font-semibold">
                                    Waste by Employee
                                </h3>
                                <p className="text-sm text-muted-foreground">
                                    Quantity and value grouped by the user who
                                    recorded the waste.
                                </p>
                            </div>

                            <div className="overflow-x-auto rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                                <table className="w-full text-sm">
                                    <thead className="border-b text-left">
                                        <tr>
                                            <th className="px-4 py-3">
                                                Employee
                                            </th>
                                            <th className="px-4 py-3">
                                                Records
                                            </th>
                                            <th className="px-4 py-3">
                                                Quantity
                                            </th>
                                            {canViewCosts && (
                                                <th className="px-4 py-3 text-right">
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
                                                    className="px-4 py-8 text-center text-muted-foreground"
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
                                                    className="border-b last:border-b-0"
                                                >
                                                    <td className="px-4 py-3 font-medium">
                                                        {row.employeeName}
                                                    </td>
                                                    <td className="px-4 py-3">
                                                        {row.recordCount.toLocaleString()}
                                                    </td>
                                                    <td className="px-4 py-3">
                                                        <QuantityTotals
                                                            totals={
                                                                row.quantityTotals
                                                            }
                                                        />
                                                    </td>
                                                    {canViewCosts && (
                                                        <td className="px-4 py-3 text-right font-medium">
                                                            {currency}{' '}
                                                            {formatDecimal(
                                                                row.totalCost ??
                                                                    '0.0000',
                                                            )}
                                                        </td>
                                                    )}
                                                </tr>
                                            ))
                                        )}
                                    </tbody>
                                </table>
                            </div>
                        </section>

                        <section className="grid gap-3">
                            <div>
                                <h3 className="font-semibold">Waste by Item</h3>
                                <p className="text-sm text-muted-foreground">
                                    Quantity and value grouped by inventory
                                    item.
                                </p>
                            </div>

                            <div className="overflow-x-auto rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                                <table className="w-full text-sm">
                                    <thead className="border-b text-left">
                                        <tr>
                                            <th className="px-4 py-3">Item</th>
                                            <th className="px-4 py-3">
                                                Records
                                            </th>
                                            <th className="px-4 py-3">
                                                Quantity
                                            </th>
                                            {canViewCosts && (
                                                <th className="px-4 py-3 text-right">
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
                                                    className="px-4 py-8 text-center text-muted-foreground"
                                                >
                                                    No waste records match the
                                                    selected filters.
                                                </td>
                                            </tr>
                                        ) : (
                                            report.byItem.map((row) => (
                                                <tr
                                                    key={row.itemId}
                                                    className="border-b last:border-b-0"
                                                >
                                                    <td className="px-4 py-3">
                                                        <div className="font-medium">
                                                            {row.itemName}
                                                        </div>
                                                        <div className="text-xs text-muted-foreground">
                                                            {row.itemSku}
                                                        </div>
                                                    </td>
                                                    <td className="px-4 py-3">
                                                        {row.recordCount.toLocaleString()}
                                                    </td>
                                                    <td className="px-4 py-3">
                                                        {formatDecimal(
                                                            row.totalQuantity,
                                                        )}{' '}
                                                        {row.baseUnitSymbol}
                                                    </td>
                                                    {canViewCosts && (
                                                        <td className="px-4 py-3 text-right font-medium">
                                                            {currency}{' '}
                                                            {formatDecimal(
                                                                row.totalCost ??
                                                                    '0.0000',
                                                            )}
                                                        </td>
                                                    )}
                                                </tr>
                                            ))
                                        )}
                                    </tbody>
                                </table>
                            </div>
                        </section>

                        <section className="grid gap-3">
                            <div>
                                <h3 className="font-semibold">
                                    Waste by Location
                                </h3>
                                <p className="text-sm text-muted-foreground">
                                    Quantity and value grouped by restaurant
                                    location.
                                </p>
                            </div>

                            <div className="overflow-x-auto rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                                <table className="w-full text-sm">
                                    <thead className="border-b text-left">
                                        <tr>
                                            <th className="px-4 py-3">
                                                Location
                                            </th>
                                            <th className="px-4 py-3">
                                                Records
                                            </th>
                                            <th className="px-4 py-3">
                                                Quantity
                                            </th>
                                            {canViewCosts && (
                                                <th className="px-4 py-3 text-right">
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
                                                    className="px-4 py-8 text-center text-muted-foreground"
                                                >
                                                    No waste records match the
                                                    selected filters.
                                                </td>
                                            </tr>
                                        ) : (
                                            report.byLocation.map((row) => (
                                                <tr
                                                    key={row.locationId}
                                                    className="border-b last:border-b-0"
                                                >
                                                    <td className="px-4 py-3 font-medium">
                                                        {row.locationName}
                                                    </td>
                                                    <td className="px-4 py-3">
                                                        {row.recordCount.toLocaleString()}
                                                    </td>
                                                    <td className="px-4 py-3">
                                                        <QuantityTotals
                                                            totals={
                                                                row.quantityTotals
                                                            }
                                                        />
                                                    </td>
                                                    {canViewCosts && (
                                                        <td className="px-4 py-3 text-right font-medium">
                                                            {currency}{' '}
                                                            {formatDecimal(
                                                                row.totalCost ??
                                                                    '0.0000',
                                                            )}
                                                        </td>
                                                    )}
                                                </tr>
                                            ))
                                        )}
                                    </tbody>
                                </table>
                            </div>
                        </section>

                        <div>
                            <h3 className="font-semibold">Waste evidence</h3>
                            <p className="text-sm text-muted-foreground">
                                Paginated immutable records behind the selected
                                report totals.
                            </p>
                        </div>

                        <div className="overflow-x-auto rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                            <table className="w-full text-sm">
                                <thead className="border-b text-left">
                                    <tr>
                                        <th className="px-4 py-3">Source</th>
                                        <th className="px-4 py-3">Occurred</th>
                                        <th className="px-4 py-3">Location</th>
                                        <th className="px-4 py-3">Item</th>
                                        <th className="px-4 py-3">Reason</th>
                                        <th className="px-4 py-3">
                                            Entered quantity
                                        </th>
                                        <th className="px-4 py-3">
                                            Base quantity
                                        </th>
                                        <th className="px-4 py-3">
                                            Recorded by
                                        </th>

                                        {canViewCosts && (
                                            <>
                                                <th className="px-4 py-3 text-right">
                                                    Unit cost
                                                </th>
                                                <th className="px-4 py-3 text-right">
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
                                                colSpan={canViewCosts ? 10 : 8}
                                                className="px-4 py-8 text-center text-muted-foreground"
                                            >
                                                No waste records match the
                                                selected filters.
                                            </td>
                                        </tr>
                                    ) : (
                                        reportRows.map((row) => (
                                            <tr
                                                key={row.recordId}
                                                className="border-b align-top last:border-b-0"
                                            >
                                                <td className="px-4 py-3">
                                                    <div className="font-medium">
                                                        Waste #{row.recordId}
                                                    </div>
                                                    <div className="text-xs text-muted-foreground">
                                                        Movement{' '}
                                                        {row.movementId === null
                                                            ? '—'
                                                            : `#${row.movementId}`}
                                                    </div>
                                                </td>
                                                <td className="px-4 py-3">
                                                    {formatDate(row.occurredAt)}
                                                </td>
                                                <td className="px-4 py-3">
                                                    <div>
                                                        {row.locationName}
                                                    </div>
                                                    <div className="text-xs text-muted-foreground">
                                                        {
                                                            row.storageLocationName
                                                        }
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
                                                    {row.reasonName}
                                                </td>
                                                <td className="px-4 py-3">
                                                    {formatDecimal(
                                                        row.quantity,
                                                    )}{' '}
                                                    {row.unitSymbol}
                                                </td>
                                                <td className="px-4 py-3">
                                                    {formatDecimal(
                                                        row.baseQuantity,
                                                    )}{' '}
                                                    {row.baseUnitSymbol}
                                                </td>
                                                <td className="px-4 py-3">
                                                    {row.recordedBy ?? '—'}
                                                    {row.notes && (
                                                        <div className="mt-1 max-w-xs text-xs text-muted-foreground">
                                                            {row.notes}
                                                        </div>
                                                    )}
                                                </td>

                                                {canViewCosts && (
                                                    <>
                                                        <td className="px-4 py-3 text-right">
                                                            {currency}{' '}
                                                            {formatDecimal(
                                                                row.unitCost ??
                                                                    '0',
                                                            )}
                                                        </td>
                                                        <td className="px-4 py-3 text-right font-medium">
                                                            {currency}{' '}
                                                            {formatDecimal(
                                                                row.totalCost ??
                                                                    '0',
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
                            <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                <p className="text-sm text-muted-foreground">
                                    Showing {rows.from ?? 0} to {rows.to ?? 0}{' '}
                                    of {rows.total} waste records.
                                </p>

                                {rows.last_page > 1 && (
                                    <div className="flex items-center gap-2">
                                        {rows.prev_page_url !== null ? (
                                            <Button variant="outline" asChild>
                                                <Link
                                                    href={rows.prev_page_url}
                                                    preserveScroll
                                                    preserveState
                                                >
                                                    Previous
                                                </Link>
                                            </Button>
                                        ) : (
                                            <Button
                                                type="button"
                                                variant="outline"
                                                disabled
                                            >
                                                Previous
                                            </Button>
                                        )}

                                        <span className="px-2 text-sm text-muted-foreground">
                                            Page {rows.current_page} of{' '}
                                            {rows.last_page}
                                        </span>

                                        {rows.next_page_url !== null ? (
                                            <Button variant="outline" asChild>
                                                <Link
                                                    href={rows.next_page_url}
                                                    preserveScroll
                                                    preserveState
                                                >
                                                    Next
                                                </Link>
                                            </Button>
                                        ) : (
                                            <Button
                                                type="button"
                                                variant="outline"
                                                disabled
                                            >
                                                Next
                                            </Button>
                                        )}
                                    </div>
                                )}
                            </div>
                        )}
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
