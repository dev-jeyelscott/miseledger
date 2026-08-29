import { Form, Head } from '@inertiajs/react';
import { AlertCircle, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';

import StockCountController from '@/actions/App/Http/Controllers/Inventory/StockCountController';
import InputError from '@/components/input-error';
import { PreviousPageButton } from '@/components/navigation/previous-page-button';
import { PageHeader } from '@/components/page-header';
import { StatusBadge } from '@/components/status-badge';
import type { StatusBadgeProps } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Field } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';
import { useDirtyFormNavigation } from '@/hooks/use-dirty-form-navigation';
import { dashboard } from '@/routes';

type StockCountStatus = 'draft' | 'submitted' | 'finalized' | 'cancelled';

type LocationOption = {
    id: number;
    name: string;
};

type StorageLocationOption = {
    id: number;
    locationId: number;
    name: string;
};

type InventoryItemOption = {
    id: number;
    name: string;
    sku: string;
    baseUnitId: number;
    baseUnitSymbol: string;
};

type UnitOption = {
    id: number;
    name: string;
    symbol: string;
};

type StockCountLine = {
    id: number;
    inventoryItemId: number;
    itemName: string;
    itemSku: string;
    expectedBaseQuantity: string;
    countedQuantity: string;
    countUnitId: number;
    countUnitSymbol: string;
    countedBaseQuantity: string;
    baseUnitSymbol: string;
    varianceBaseQuantity: string;
    varianceUnitCost: string | null;
    varianceTotalCost: string | null;
    notes: string | null;
    movementId: number | null;
};

type StockCount = {
    id: number;
    number: string;
    status: StockCountStatus;
    locationId: number;
    locationName: string;
    storageLocationId: number;
    storageLocationName: string;
    countedAt: string | null;
    createdBy: string | null;
    submittedBy: string | null;
    finalizedBy: string | null;
    finalizedAt: string | null;
    lines: StockCountLine[];
};

type LineState = {
    inventoryItemId: string;
    countedQuantity: string;
    countUnitId: string;
    notes: string;
};

type Props = {
    stockCount: StockCount | null;
    locationOptions: LocationOption[];
    storageLocationOptions: StorageLocationOption[];
    inventoryItemOptions: InventoryItemOption[];
    unitOptions: UnitOption[];
    currency: string;
    timezone: string;
    canCreate: boolean;
    canFinalize: boolean;
    canViewCosts: boolean;
};

type ErrorMap = Record<string, string>;

type ErrorTarget = {
    key: string;
    label: string;
    targetId: string;
    message: string;
};

/** Create the initial editable line state for a new physical-count row. */
function emptyLine(): LineState {
    return {
        inventoryItemId: '',
        countedQuantity: '0',
        countUnitId: '',
        notes: '',
    };
}

/** Format persisted decimal strings without introducing floating-point arithmetic. */
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

/** Format authoritative workflow timestamps in the active organization timezone. */
function formatOrganizationDate(value: string, timezone: string): string {
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

/** Resolve the canonical semantic badge treatment for a count lifecycle state. */
function stockCountStatusVariant(
    status: StockCountStatus,
): StatusBadgeProps['variant'] {
    if (status === 'finalized') {
        return 'success';
    }

    if (status === 'submitted') {
        return 'info';
    }

    if (status === 'cancelled') {
        return 'danger';
    }

    return 'neutral';
}

/** Convert a lifecycle status into its visible human-readable label. */
function stockCountStatusLabel(status: StockCountStatus): string {
    return status.charAt(0).toUpperCase() + status.slice(1);
}

/** Make finalized variance direction explicit without relying on color alone. */
function FinalizedVariance({ value, unit }: { value: string; unit: string }) {
    const numericValue = value.trim();
    const variant =
        numericValue === '0' || numericValue === '0.0'
            ? 'neutral'
            : numericValue.startsWith('-')
              ? 'danger'
              : 'success';
    const label =
        variant === 'neutral'
            ? 'No variance'
            : variant === 'danger'
              ? 'Shortage'
              : 'Overage';

    return (
        <div className="flex flex-wrap items-center gap-2">
            <span className="font-medium tabular-nums">
                {formatDecimal(value)} {unit}
            </span>
            <StatusBadge label={label} variant={variant} />
        </div>
    );
}

/** Map one server validation key to the exact rendered control that owns it. */
function errorTargetId(key: string): string | null {
    if (key === 'number') {
        return 'stock-count-number';
    }

    if (key === 'location_id') {
        return 'stock-count-location';
    }

    if (key === 'storage_location_id') {
        return 'stock-count-storage-location';
    }

    if (key === 'lines') {
        return 'stock-count-lines';
    }

    const lineMatch = key.match(
        /^lines\.(\d+)\.(inventory_item_id|counted_quantity|count_unit_id|notes)$/,
    );

    if (lineMatch === null) {
        return null;
    }

    const [, index, field] = lineMatch;

    const fieldSuffix = {
        inventory_item_id: 'item',
        counted_quantity: 'quantity',
        count_unit_id: 'unit',
        notes: 'notes',
    }[field];

    return `stock-count-line-${index}-${fieldSuffix}`;
}

/** Produce concise labels for server validation errors in the navigation summary. */
function errorTargetLabel(key: string): string {
    if (key === 'number') {
        return 'Count number';
    }

    if (key === 'location_id') {
        return 'Location';
    }

    if (key === 'storage_location_id') {
        return 'Storage location';
    }

    if (key === 'lines') {
        return 'Count lines';
    }

    const lineMatch = key.match(/^lines\.(\d+)\.(.+)$/);

    if (lineMatch === null) {
        return key;
    }

    const [, rawIndex, field] = lineMatch;
    const index = Number(rawIndex) + 1;

    const fieldLabel = {
        inventory_item_id: 'Inventory item',
        counted_quantity: 'Physical quantity',
        count_unit_id: 'Count unit',
        notes: 'Line notes',
    }[field];

    return `Line ${index}: ${fieldLabel ?? field}`;
}

/** Convert Inertia's server errors into accessible in-page navigation targets. */
function errorTargets(errors: ErrorMap): ErrorTarget[] {
    return Object.entries(errors).flatMap(([key, message]) => {
        const targetId = errorTargetId(key);

        return targetId === null
            ? []
            : [
                  {
                      key,
                      label: errorTargetLabel(key),
                      targetId,
                      message,
                  },
              ];
    });
}

/** Return the first authoritative lifecycle-action validation error. */
function firstActionError(errors: ErrorMap): string | null {
    return Object.values(errors)[0] ?? null;
}

/** Move keyboard focus to a server-invalid field while respecting reduced motion. */
function focusErrorTarget(targetId: string): void {
    const element = document.getElementById(targetId);

    if (element === null) {
        return;
    }

    const prefersReducedMotion = window.matchMedia(
        '(prefers-reduced-motion: reduce)',
    ).matches;

    element.scrollIntoView({
        behavior: prefersReducedMotion ? 'auto' : 'smooth',
        block: 'center',
    });

    element.focus({
        preventScroll: true,
    });
}

/** Render immutable physical evidence attached to a non-editable count. */
function CountEvidence({
    stockCount,
    finalized,
    currency,
    canViewCosts,
}: {
    stockCount: StockCount;
    finalized: boolean;
    currency: string;
    canViewCosts: boolean;
}) {
    const evidenceHeading = finalized
        ? 'Finalized audit evidence'
        : stockCount.status === 'submitted'
          ? 'Submitted evidence'
          : 'Cancelled evidence';

    const evidenceDescription = finalized
        ? 'This evidence is locked. Expected quantities, variances, and adjustment references reflect the finalized server record.'
        : stockCount.status === 'submitted'
          ? 'This physical count evidence is frozen while awaiting finalization or cancellation. Inventory has not been adjusted by submission.'
          : 'This physical count evidence is frozen because the count was cancelled without inventory adjustments.';

    return (
        <section
            className="overflow-hidden rounded-xl border border-border bg-card"
            aria-labelledby="stock-count-evidence-heading"
        >
            <div className="border-b border-border px-4 py-4 sm:px-5">
                <h2 id="stock-count-evidence-heading" className="font-semibold">
                    {evidenceHeading}
                </h2>
                <p className="mt-1 text-sm text-muted-foreground">
                    {evidenceDescription}
                </p>
            </div>

            <div className="divide-y divide-border md:hidden">
                {stockCount.lines.map((line, index) => (
                    <article key={line.id} className="space-y-4 p-4">
                        <div className="flex items-start justify-between gap-3">
                            <div className="min-w-0">
                                <p className="text-xs font-medium text-muted-foreground">
                                    Line {index + 1}
                                </p>
                                <h3 className="truncate font-medium">
                                    {line.itemName}
                                </h3>
                                <p className="text-xs text-muted-foreground">
                                    {line.itemSku}
                                </p>
                            </div>
                        </div>

                        <dl className="grid grid-cols-2 gap-x-4 gap-y-3 text-sm">
                            <div>
                                <dt className="text-muted-foreground">
                                    Counted
                                </dt>
                                <dd className="font-medium">
                                    {formatDecimal(line.countedQuantity)}{' '}
                                    {line.countUnitSymbol}
                                </dd>
                            </div>

                            <div>
                                <dt className="text-muted-foreground">
                                    Counted base
                                </dt>
                                <dd className="font-medium">
                                    {formatDecimal(line.countedBaseQuantity)}{' '}
                                    {line.baseUnitSymbol}
                                </dd>
                            </div>

                            {finalized && (
                                <>
                                    <div>
                                        <dt className="text-muted-foreground">
                                            Expected
                                        </dt>
                                        <dd className="font-medium">
                                            {formatDecimal(
                                                line.expectedBaseQuantity,
                                            )}{' '}
                                            {line.baseUnitSymbol}
                                        </dd>
                                    </div>

                                    <div>
                                        <dt className="text-muted-foreground">
                                            Variance
                                        </dt>
                                        <dd>
                                            <FinalizedVariance
                                                value={
                                                    line.varianceBaseQuantity
                                                }
                                                unit={line.baseUnitSymbol}
                                            />
                                        </dd>
                                    </div>
                                </>
                            )}

                            {finalized && canViewCosts && (
                                <div>
                                    <dt className="text-muted-foreground">
                                        Variance value
                                    </dt>
                                    <dd className="font-medium">
                                        {line.varianceTotalCost === null
                                            ? 'Not available'
                                            : `${currency} ${formatDecimal(
                                                  line.varianceTotalCost,
                                              )}`}
                                    </dd>
                                </div>
                            )}

                            {finalized && (
                                <div>
                                    <dt className="text-muted-foreground">
                                        Adjustment
                                    </dt>
                                    <dd className="font-medium">
                                        {line.movementId === null
                                            ? 'No movement'
                                            : `Stock movement #${line.movementId}`}
                                    </dd>
                                </div>
                            )}
                        </dl>

                        {line.notes && (
                            <div className="rounded-md bg-muted/50 p-3 text-sm">
                                <p className="text-xs font-medium text-muted-foreground">
                                    Count note
                                </p>
                                <p className="mt-1">{line.notes}</p>
                            </div>
                        )}
                    </article>
                ))}
            </div>

            <div className="hidden overflow-x-auto md:block">
                <table className="w-full text-sm">
                    <thead className="border-b border-border bg-muted/40 text-left">
                        <tr>
                            <th scope="col" className="px-4 py-3 font-medium">
                                Item
                            </th>
                            <th scope="col" className="px-4 py-3 font-medium">
                                Counted
                            </th>
                            <th scope="col" className="px-4 py-3 font-medium">
                                Counted base
                            </th>

                            {finalized && (
                                <>
                                    <th
                                        scope="col"
                                        className="px-4 py-3 font-medium"
                                    >
                                        Expected
                                    </th>
                                    <th
                                        scope="col"
                                        className="px-4 py-3 font-medium"
                                    >
                                        Variance
                                    </th>
                                </>
                            )}

                            {finalized && canViewCosts && (
                                <th
                                    scope="col"
                                    className="px-4 py-3 text-right font-medium"
                                >
                                    Variance value
                                </th>
                            )}

                            {finalized && (
                                <th
                                    scope="col"
                                    className="px-4 py-3 font-medium"
                                >
                                    Adjustment
                                </th>
                            )}
                        </tr>
                    </thead>

                    <tbody className="divide-y divide-border">
                        {stockCount.lines.map((line) => (
                            <tr key={line.id}>
                                <td className="px-4 py-3 align-top">
                                    <div className="font-medium">
                                        {line.itemName}
                                    </div>
                                    <div className="text-xs text-muted-foreground">
                                        {line.itemSku}
                                    </div>
                                    {line.notes && (
                                        <div className="mt-2 max-w-sm text-xs text-muted-foreground">
                                            {line.notes}
                                        </div>
                                    )}
                                </td>

                                <td className="px-4 py-3 align-top">
                                    {formatDecimal(line.countedQuantity)}{' '}
                                    {line.countUnitSymbol}
                                </td>

                                <td className="px-4 py-3 align-top">
                                    {formatDecimal(line.countedBaseQuantity)}{' '}
                                    {line.baseUnitSymbol}
                                </td>

                                {finalized && (
                                    <>
                                        <td className="px-4 py-3 align-top">
                                            {formatDecimal(
                                                line.expectedBaseQuantity,
                                            )}{' '}
                                            {line.baseUnitSymbol}
                                        </td>

                                        <td className="px-4 py-3 align-top">
                                            <FinalizedVariance
                                                value={
                                                    line.varianceBaseQuantity
                                                }
                                                unit={line.baseUnitSymbol}
                                            />
                                        </td>
                                    </>
                                )}

                                {finalized && canViewCosts && (
                                    <td className="px-4 py-3 text-right align-top">
                                        {line.varianceTotalCost === null
                                            ? 'Not available'
                                            : `${currency} ${formatDecimal(
                                                  line.varianceTotalCost,
                                              )}`}
                                    </td>
                                )}

                                {finalized && (
                                    <td className="px-4 py-3 align-top">
                                        {line.movementId === null
                                            ? 'No movement'
                                            : `Stock movement #${line.movementId}`}
                                    </td>
                                )}
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </section>
    );
}

/** Render the lifecycle-oriented Stock Count creation, evidence, and audit workspace. */
export default function StockCountForm({
    stockCount,
    locationOptions,
    storageLocationOptions,
    inventoryItemOptions,
    unitOptions,
    currency,
    timezone,
    canCreate,
    canFinalize,
    canViewCosts,
}: Props) {
    const editable =
        canCreate && (stockCount === null || stockCount.status === 'draft');
    const finalized = stockCount?.status === 'finalized';
    const { confirmNavigation, setIsDirty } = useDirtyFormNavigation(
        'Discard unsaved stock count changes?',
    );

    const initialLocationId =
        stockCount?.locationId.toString() ??
        locationOptions[0]?.id.toString() ??
        '';

    const initialStorageLocationId =
        stockCount?.storageLocationId.toString() ??
        storageLocationOptions
            .find(
                (storageLocation) =>
                    storageLocation.locationId.toString() === initialLocationId,
            )
            ?.id.toString() ??
        '';

    const [locationId, setLocationId] = useState(initialLocationId);
    const [storageLocationId, setStorageLocationId] = useState(
        initialStorageLocationId,
    );
    const [submitDialogOpen, setSubmitDialogOpen] = useState(false);
    const [finalizeDialogOpen, setFinalizeDialogOpen] = useState(false);
    const [cancelDialogOpen, setCancelDialogOpen] = useState(false);

    const [lines, setLines] = useState<LineState[]>(
        stockCount?.lines.map((line) => ({
            inventoryItemId: line.inventoryItemId.toString(),
            countedQuantity: line.countedQuantity,
            countUnitId: line.countUnitId.toString(),
            notes: line.notes ?? '',
        })) ?? [emptyLine()],
    );

    const selectedStorageLocations = storageLocationOptions.filter(
        (storageLocation) =>
            storageLocation.locationId.toString() === locationId,
    );

    /** Update one dynamic physical-count line without mutating sibling lines. */
    const updateLine = (index: number, values: Partial<LineState>) => {
        setIsDirty(true);
        setLines((current) =>
            current.map((line, currentIndex) =>
                currentIndex === index
                    ? {
                          ...line,
                          ...values,
                      }
                    : line,
            ),
        );
    };

    /** Append one blank physical-count evidence line. */
    const addLine = () => {
        setIsDirty(true);
        setLines((current) => [...current, emptyLine()]);
    };

    /** Remove one physical-count line while retaining at least one line. */
    const removeLine = (index: number) => {
        setIsDirty(true);
        setLines((current) =>
            current.filter((_, currentIndex) => currentIndex !== index),
        );
    };

    /** Keep storage selection inside the newly selected parent location. */
    const handleLocationChange = (value: string) => {
        setIsDirty(true);
        setLocationId(value);

        const firstStorage = storageLocationOptions.find(
            (storageLocation) =>
                storageLocation.locationId.toString() === value,
        );

        setStorageLocationId(firstStorage?.id.toString() ?? '');
    };

    /** Default a selected inventory item to its server-provided base count unit. */
    const handleItemChange = (index: number, value: string) => {
        const inventoryItem = inventoryItemOptions.find(
            (item) => item.id.toString() === value,
        );

        updateLine(index, {
            inventoryItemId: value,
            countUnitId: inventoryItem?.baseUnitId.toString() ?? '',
        });
    };

    const formAttributes =
        stockCount === null
            ? StockCountController.store.form()
            : StockCountController.update.form.put(stockCount.id);

    const title = stockCount === null ? 'New stock count' : stockCount.number;
    const status = stockCount?.status;

    return (
        <>
            <Head title={title} />

            <div className="flex flex-1 flex-col gap-6 p-4 sm:p-6">
                <PageHeader
                    title={
                        <span className="flex flex-wrap items-center gap-2">
                            <span>{title}</span>
                            {status && (
                                <StatusBadge
                                    label={stockCountStatusLabel(status)}
                                    variant={stockCountStatusVariant(status)}
                                />
                            )}
                        </span>
                    }
                    description={
                        stockCount === null
                            ? 'Create physical count evidence as a draft before submitting it for reconciliation.'
                            : `${stockCount.locationName} / ${stockCount.storageLocationName}`
                    }
                    actions={
                        <PreviousPageButton
                            fallback={StockCountController.index.url()}
                            onNavigate={confirmNavigation}
                            variant="outline"
                        >
                            Back to stock counts
                        </PreviousPageButton>
                    }
                />

                {stockCount && (
                    <section
                        className="rounded-xl border border-border bg-card p-4 sm:p-5"
                        aria-labelledby="stock-count-lifecycle-heading"
                    >
                        <div className="mb-4">
                            <h2
                                id="stock-count-lifecycle-heading"
                                className="font-semibold"
                            >
                                Lifecycle and evidence
                            </h2>
                            <p className="mt-1 text-sm text-muted-foreground">
                                Recorded workflow times are shown in {timezone}.
                            </p>
                        </div>

                        <dl className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                            {stockCount.createdBy && (
                                <div>
                                    <dt className="text-sm text-muted-foreground">
                                        Created by
                                    </dt>
                                    <dd className="mt-1 font-medium">
                                        {stockCount.createdBy}
                                    </dd>
                                </div>
                            )}

                            {stockCount.countedAt && (
                                <div>
                                    <dt className="text-sm text-muted-foreground">
                                        Counted at
                                    </dt>
                                    <dd className="mt-1 font-medium">
                                        {formatOrganizationDate(
                                            stockCount.countedAt,
                                            timezone,
                                        )}
                                    </dd>
                                </div>
                            )}

                            {stockCount.submittedBy && (
                                <div>
                                    <dt className="text-sm text-muted-foreground">
                                        Submitted by
                                    </dt>
                                    <dd className="mt-1 font-medium">
                                        {stockCount.submittedBy}
                                    </dd>
                                </div>
                            )}

                            {stockCount.finalizedBy && (
                                <div>
                                    <dt className="text-sm text-muted-foreground">
                                        Finalized by
                                    </dt>
                                    <dd className="mt-1 font-medium">
                                        {stockCount.finalizedBy}
                                    </dd>
                                </div>
                            )}

                            {stockCount.finalizedAt && (
                                <div>
                                    <dt className="text-sm text-muted-foreground">
                                        Finalized at
                                    </dt>
                                    <dd className="mt-1 font-medium">
                                        {formatOrganizationDate(
                                            stockCount.finalizedAt,
                                            timezone,
                                        )}
                                    </dd>
                                </div>
                            )}
                        </dl>
                    </section>
                )}

                {editable ? (
                    <Form
                        {...formAttributes}
                        onChangeCapture={() => setIsDirty(true)}
                        onSuccess={() => setIsDirty(false)}
                    >
                        {({ processing, errors }) => {
                            const targets = errorTargets(errors);

                            return (
                                <div className="space-y-6">
                                    {targets.length > 0 && (
                                        <section
                                            className="rounded-xl border border-destructive/30 bg-destructive/5 p-4"
                                            aria-labelledby="stock-count-errors-heading"
                                            role="alert"
                                        >
                                            <div className="flex gap-3">
                                                <AlertCircle
                                                    className="mt-0.5 size-5 shrink-0 text-destructive"
                                                    aria-hidden="true"
                                                />

                                                <div>
                                                    <h2
                                                        id="stock-count-errors-heading"
                                                        className="font-medium"
                                                    >
                                                        Review the highlighted
                                                        fields
                                                    </h2>
                                                    <p className="mt-1 text-sm text-muted-foreground">
                                                        The server rejected part
                                                        of this draft. Select an
                                                        issue to move directly
                                                        to its field.
                                                    </p>

                                                    <ul className="mt-3 space-y-1 text-sm">
                                                        {targets.map(
                                                            (target) => (
                                                                <li
                                                                    key={
                                                                        target.key
                                                                    }
                                                                >
                                                                    <button
                                                                        type="button"
                                                                        className="text-left font-medium text-destructive underline-offset-4 hover:underline focus-visible:rounded-sm focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                                                        onClick={() =>
                                                                            focusErrorTarget(
                                                                                target.targetId,
                                                                            )
                                                                        }
                                                                    >
                                                                        {
                                                                            target.label
                                                                        }
                                                                        :{' '}
                                                                        {
                                                                            target.message
                                                                        }
                                                                    </button>
                                                                </li>
                                                            ),
                                                        )}
                                                    </ul>
                                                </div>
                                            </div>
                                        </section>
                                    )}

                                    <section
                                        className="rounded-xl border border-border bg-card"
                                        aria-labelledby="stock-count-scope-heading"
                                    >
                                        <div className="border-b border-border px-4 py-4 sm:px-5">
                                            <h2
                                                id="stock-count-scope-heading"
                                                className="font-semibold"
                                            >
                                                Count scope
                                            </h2>
                                            <p className="mt-1 text-sm text-muted-foreground">
                                                Define where this physical
                                                evidence was collected. Server
                                                validation remains
                                                authoritative.
                                            </p>
                                        </div>

                                        <div className="grid gap-4 p-4 sm:p-5 md:grid-cols-3">
                                            <Field
                                                id="stock-count-number"
                                                label="Count number"
                                                error={errors.number}
                                            >
                                                <Input
                                                    name="number"
                                                    defaultValue={
                                                        stockCount?.number ?? ''
                                                    }
                                                    required
                                                />
                                            </Field>

                                            <Field
                                                id="stock-count-location"
                                                label="Location"
                                                error={errors.location_id}
                                            >
                                                <NativeSelect
                                                    name="location_id"
                                                    value={locationId}
                                                    onChange={(event) =>
                                                        handleLocationChange(
                                                            event.target.value,
                                                        )
                                                    }
                                                    required
                                                >
                                                    <option value="">
                                                        Select location
                                                    </option>

                                                    {locationOptions.map(
                                                        (location) => (
                                                            <option
                                                                key={
                                                                    location.id
                                                                }
                                                                value={
                                                                    location.id
                                                                }
                                                            >
                                                                {location.name}
                                                            </option>
                                                        ),
                                                    )}
                                                </NativeSelect>
                                            </Field>

                                            <Field
                                                id="stock-count-storage-location"
                                                label="Storage location"
                                                error={
                                                    errors.storage_location_id
                                                }
                                            >
                                                <NativeSelect
                                                    name="storage_location_id"
                                                    value={storageLocationId}
                                                    onChange={(event) =>
                                                        setStorageLocationId(
                                                            event.target.value,
                                                        )
                                                    }
                                                    required
                                                >
                                                    <option value="">
                                                        Select storage
                                                    </option>

                                                    {selectedStorageLocations.map(
                                                        (storageLocation) => (
                                                            <option
                                                                key={
                                                                    storageLocation.id
                                                                }
                                                                value={
                                                                    storageLocation.id
                                                                }
                                                            >
                                                                {
                                                                    storageLocation.name
                                                                }
                                                            </option>
                                                        ),
                                                    )}
                                                </NativeSelect>
                                            </Field>
                                        </div>
                                    </section>

                                    <section
                                        id="stock-count-lines"
                                        tabIndex={-1}
                                        className="rounded-xl border border-border bg-card"
                                        aria-labelledby="stock-count-lines-heading"
                                    >
                                        <div className="flex flex-col gap-3 border-b border-border px-4 py-4 sm:flex-row sm:items-start sm:justify-between sm:px-5">
                                            <div>
                                                <h2
                                                    id="stock-count-lines-heading"
                                                    className="font-semibold"
                                                >
                                                    Physical count evidence
                                                </h2>
                                                <p className="mt-1 text-sm text-muted-foreground">
                                                    Enter each observed quantity
                                                    in the practical unit used
                                                    during counting.
                                                </p>
                                            </div>

                                            <Button
                                                type="button"
                                                variant="outline"
                                                onClick={addLine}
                                                className="w-full sm:w-auto"
                                            >
                                                <Plus
                                                    className="size-4"
                                                    aria-hidden="true"
                                                />
                                                Add item
                                            </Button>
                                        </div>

                                        <div className="divide-y divide-border">
                                            {lines.map((line, index) => {
                                                const selectedItem =
                                                    inventoryItemOptions.find(
                                                        (item) =>
                                                            item.id.toString() ===
                                                            line.inventoryItemId,
                                                    );

                                                return (
                                                    <fieldset
                                                        key={index}
                                                        className="space-y-4 p-4 sm:p-5"
                                                    >
                                                        <legend className="mb-3 flex w-full items-center justify-between gap-3">
                                                            <span className="font-medium">
                                                                Line {index + 1}
                                                            </span>

                                                            <Button
                                                                type="button"
                                                                variant="ghost"
                                                                size="sm"
                                                                onClick={() =>
                                                                    removeLine(
                                                                        index,
                                                                    )
                                                                }
                                                                disabled={
                                                                    lines.length ===
                                                                    1
                                                                }
                                                                aria-label={`Remove line ${index + 1}`}
                                                            >
                                                                <Trash2
                                                                    className="size-4"
                                                                    aria-hidden="true"
                                                                />
                                                                Remove
                                                            </Button>
                                                        </legend>

                                                        <div className="grid gap-4 lg:grid-cols-4">
                                                            <Field
                                                                id={`stock-count-line-${index}-item`}
                                                                label="Inventory item"
                                                                error={
                                                                    errors[
                                                                        `lines.${index}.inventory_item_id`
                                                                    ]
                                                                }
                                                                className="lg:col-span-2"
                                                            >
                                                                <NativeSelect
                                                                    name={`lines[${index}][inventory_item_id]`}
                                                                    value={
                                                                        line.inventoryItemId
                                                                    }
                                                                    onChange={(
                                                                        event,
                                                                    ) =>
                                                                        handleItemChange(
                                                                            index,
                                                                            event
                                                                                .target
                                                                                .value,
                                                                        )
                                                                    }
                                                                    required
                                                                >
                                                                    <option value="">
                                                                        Select
                                                                        item
                                                                    </option>

                                                                    {inventoryItemOptions.map(
                                                                        (
                                                                            item,
                                                                        ) => {
                                                                            const used =
                                                                                lines.some(
                                                                                    (
                                                                                        otherLine,
                                                                                        otherIndex,
                                                                                    ) =>
                                                                                        otherIndex !==
                                                                                            index &&
                                                                                        otherLine.inventoryItemId ===
                                                                                            item.id.toString(),
                                                                                );

                                                                            return (
                                                                                <option
                                                                                    key={
                                                                                        item.id
                                                                                    }
                                                                                    value={
                                                                                        item.id
                                                                                    }
                                                                                    disabled={
                                                                                        used
                                                                                    }
                                                                                >
                                                                                    {
                                                                                        item.name
                                                                                    }{' '}
                                                                                    (
                                                                                    {
                                                                                        item.sku
                                                                                    }

                                                                                    )
                                                                                </option>
                                                                            );
                                                                        },
                                                                    )}
                                                                </NativeSelect>
                                                            </Field>

                                                            <Field
                                                                id={`stock-count-line-${index}-quantity`}
                                                                label="Physical quantity"
                                                                error={
                                                                    errors[
                                                                        `lines.${index}.counted_quantity`
                                                                    ]
                                                                }
                                                            >
                                                                <Input
                                                                    name={`lines[${index}][counted_quantity]`}
                                                                    type="number"
                                                                    min="0"
                                                                    max="999999999.999999"
                                                                    step="0.000001"
                                                                    value={
                                                                        line.countedQuantity
                                                                    }
                                                                    onChange={(
                                                                        event,
                                                                    ) =>
                                                                        updateLine(
                                                                            index,
                                                                            {
                                                                                countedQuantity:
                                                                                    event
                                                                                        .target
                                                                                        .value,
                                                                            },
                                                                        )
                                                                    }
                                                                    required
                                                                />
                                                            </Field>

                                                            <Field
                                                                id={`stock-count-line-${index}-unit`}
                                                                label="Count unit"
                                                                error={
                                                                    errors[
                                                                        `lines.${index}.count_unit_id`
                                                                    ]
                                                                }
                                                            >
                                                                <NativeSelect
                                                                    name={`lines[${index}][count_unit_id]`}
                                                                    value={
                                                                        line.countUnitId
                                                                    }
                                                                    onChange={(
                                                                        event,
                                                                    ) =>
                                                                        updateLine(
                                                                            index,
                                                                            {
                                                                                countUnitId:
                                                                                    event
                                                                                        .target
                                                                                        .value,
                                                                            },
                                                                        )
                                                                    }
                                                                    required
                                                                >
                                                                    <option value="">
                                                                        Select
                                                                        unit
                                                                    </option>

                                                                    {unitOptions.map(
                                                                        (
                                                                            unit,
                                                                        ) => (
                                                                            <option
                                                                                key={
                                                                                    unit.id
                                                                                }
                                                                                value={
                                                                                    unit.id
                                                                                }
                                                                            >
                                                                                {
                                                                                    unit.name
                                                                                }{' '}
                                                                                (
                                                                                {
                                                                                    unit.symbol
                                                                                }

                                                                                )
                                                                            </option>
                                                                        ),
                                                                    )}
                                                                </NativeSelect>
                                                            </Field>
                                                        </div>

                                                        <Field
                                                            id={`stock-count-line-${index}-notes`}
                                                            label="Line notes"
                                                            error={
                                                                errors[
                                                                    `lines.${index}.notes`
                                                                ]
                                                            }
                                                        >
                                                            <Input
                                                                name={`lines[${index}][notes]`}
                                                                value={
                                                                    line.notes
                                                                }
                                                                onChange={(
                                                                    event,
                                                                ) =>
                                                                    updateLine(
                                                                        index,
                                                                        {
                                                                            notes: event
                                                                                .target
                                                                                .value,
                                                                        },
                                                                    )
                                                                }
                                                            />
                                                        </Field>

                                                        {selectedItem && (
                                                            <p className="text-xs text-muted-foreground">
                                                                Selected item:{' '}
                                                                {
                                                                    selectedItem.name
                                                                }{' '}
                                                                (
                                                                {
                                                                    selectedItem.sku
                                                                }
                                                                ). Base unit:{' '}
                                                                {
                                                                    selectedItem.baseUnitSymbol
                                                                }
                                                                . Conversion is
                                                                validated and
                                                                snapshotted by
                                                                the server.
                                                            </p>
                                                        )}

                                                        {stockCount?.lines[
                                                            index
                                                        ] && (
                                                            <p className="text-xs text-muted-foreground">
                                                                Last saved base
                                                                quantity:{' '}
                                                                {formatDecimal(
                                                                    stockCount
                                                                        .lines[
                                                                        index
                                                                    ]
                                                                        .countedBaseQuantity,
                                                                )}{' '}
                                                                {
                                                                    stockCount
                                                                        .lines[
                                                                        index
                                                                    ]
                                                                        .baseUnitSymbol
                                                                }
                                                            </p>
                                                        )}
                                                    </fieldset>
                                                );
                                            })}
                                        </div>

                                        {errors.lines && (
                                            <div className="border-t border-border px-4 py-3 sm:px-5">
                                                <InputError
                                                    id="stock-count-lines-error"
                                                    message={errors.lines}
                                                />
                                            </div>
                                        )}
                                    </section>

                                    <div className="sticky bottom-0 z-10 -mx-4 border-t border-border bg-background/95 px-4 py-3 backdrop-blur sm:static sm:mx-0 sm:border-0 sm:bg-transparent sm:p-0 sm:backdrop-blur-none">
                                        <Button
                                            type="submit"
                                            disabled={processing}
                                            className="w-full sm:w-auto"
                                        >
                                            {processing
                                                ? 'Saving…'
                                                : stockCount === null
                                                  ? 'Create draft'
                                                  : 'Save draft'}
                                        </Button>
                                    </div>
                                </div>
                            );
                        }}
                    </Form>
                ) : (
                    stockCount && (
                        <CountEvidence
                            stockCount={stockCount}
                            finalized={finalized}
                            currency={currency}
                            canViewCosts={canViewCosts}
                        />
                    )
                )}

                {stockCount?.status === 'draft' && canCreate && (
                    <section
                        className="rounded-xl border border-border bg-card p-4 sm:p-5"
                        aria-labelledby="stock-count-draft-actions"
                    >
                        <h2
                            id="stock-count-draft-actions"
                            className="font-semibold"
                        >
                            Draft actions
                        </h2>
                        <p className="mt-1 text-sm text-muted-foreground">
                            Save any evidence changes before changing the count
                            lifecycle.
                        </p>

                        <div className="mt-4 flex flex-col gap-2 sm:flex-row">
                            <Button
                                type="button"
                                onClick={() => setSubmitDialogOpen(true)}
                            >
                                Submit count
                            </Button>

                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setCancelDialogOpen(true)}
                            >
                                Cancel count
                            </Button>
                        </div>
                    </section>
                )}

                {stockCount?.status === 'submitted' && (
                    <section
                        className="rounded-xl border border-border bg-card p-4 sm:p-5"
                        aria-labelledby="stock-count-submitted-actions"
                    >
                        <h2
                            id="stock-count-submitted-actions"
                            className="font-semibold"
                        >
                            Submitted count
                        </h2>
                        <p className="mt-1 text-sm text-muted-foreground">
                            Evidence is frozen. Finalization remains subject to
                            all server-side inventory and ledger validation.
                        </p>

                        <div className="mt-4 flex flex-col gap-2 sm:flex-row">
                            {canFinalize && (
                                <Button
                                    type="button"
                                    onClick={() => setFinalizeDialogOpen(true)}
                                >
                                    Finalize count
                                </Button>
                            )}

                            {canCreate && (
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={() => setCancelDialogOpen(true)}
                                >
                                    Cancel count
                                </Button>
                            )}
                        </div>
                    </section>
                )}
            </div>

            {stockCount?.status === 'draft' && canCreate && (
                <Dialog
                    open={submitDialogOpen}
                    onOpenChange={setSubmitDialogOpen}
                >
                    <DialogContent>
                        <Form
                            {...StockCountController.submit.form(stockCount.id)}
                            onSuccess={() => setSubmitDialogOpen(false)}
                        >
                            {({ processing, errors }) => {
                                const actionError = firstActionError(errors);

                                return (
                                    <div className="grid gap-4">
                                        <DialogHeader>
                                            <DialogTitle>
                                                Submit stock count?
                                            </DialogTitle>
                                            <DialogDescription>
                                                Submitting freezes the current
                                                physical count evidence for
                                                finalization. It does not adjust
                                                inventory. The server will
                                                validate the lifecycle
                                                transition before it is
                                                accepted.
                                            </DialogDescription>
                                        </DialogHeader>

                                        {actionError !== null && (
                                            <p
                                                role="alert"
                                                className="rounded-lg border border-destructive/30 bg-destructive/10 px-3 py-2 text-sm text-destructive"
                                            >
                                                {actionError}
                                            </p>
                                        )}

                                        <DialogFooter>
                                            <Button
                                                type="button"
                                                variant="outline"
                                                disabled={processing}
                                                onClick={() =>
                                                    setSubmitDialogOpen(false)
                                                }
                                            >
                                                Keep draft
                                            </Button>

                                            <Button
                                                type="submit"
                                                disabled={processing}
                                            >
                                                {processing
                                                    ? 'Submitting…'
                                                    : 'Submit count'}
                                            </Button>
                                        </DialogFooter>
                                    </div>
                                );
                            }}
                        </Form>
                    </DialogContent>
                </Dialog>
            )}

            {stockCount?.status === 'submitted' && canFinalize && (
                <Dialog
                    open={finalizeDialogOpen}
                    onOpenChange={setFinalizeDialogOpen}
                >
                    <DialogContent>
                        <Form
                            {...StockCountController.finalize.form(
                                stockCount.id,
                            )}
                            onSuccess={() => setFinalizeDialogOpen(false)}
                        >
                            {({ processing, errors }) => {
                                const actionError = firstActionError(errors);

                                return (
                                    <div className="grid gap-4">
                                        <DialogHeader>
                                            <DialogTitle>
                                                Finalize count and commit
                                                inventory adjustments?
                                            </DialogTitle>
                                            <DialogDescription>
                                                Finalization is the
                                                inventory-impacting step. After
                                                server validation, MiseLedger
                                                will commit the required count
                                                adjustments through the existing
                                                stock-ledger workflow. The
                                                finalized evidence is then
                                                locked for audit integrity.
                                            </DialogDescription>

                                            <p className="rounded-md bg-muted px-3 py-2 text-sm text-muted-foreground">
                                                This will finalize{' '}
                                                {stockCount.lines.length}{' '}
                                                {stockCount.lines.length === 1
                                                    ? 'count line'
                                                    : 'count lines'}
                                                .
                                            </p>
                                        </DialogHeader>

                                        {actionError !== null && (
                                            <p
                                                role="alert"
                                                className="rounded-lg border border-destructive/30 bg-destructive/10 px-3 py-2 text-sm text-destructive"
                                            >
                                                {actionError}
                                            </p>
                                        )}

                                        <DialogFooter>
                                            <Button
                                                type="button"
                                                variant="outline"
                                                disabled={processing}
                                                onClick={() =>
                                                    setFinalizeDialogOpen(false)
                                                }
                                            >
                                                Go back
                                            </Button>

                                            <Button
                                                type="submit"
                                                disabled={processing}
                                            >
                                                {processing
                                                    ? 'Finalizing…'
                                                    : 'Finalize and commit adjustments'}
                                            </Button>
                                        </DialogFooter>
                                    </div>
                                );
                            }}
                        </Form>
                    </DialogContent>
                </Dialog>
            )}

            {stockCount &&
                canCreate &&
                (stockCount.status === 'draft' ||
                    stockCount.status === 'submitted') && (
                    <Dialog
                        open={cancelDialogOpen}
                        onOpenChange={setCancelDialogOpen}
                    >
                        <DialogContent>
                            <Form
                                {...StockCountController.cancel.form(
                                    stockCount.id,
                                )}
                                onSuccess={() => setCancelDialogOpen(false)}
                            >
                                {({ processing, errors }) => {
                                    const actionError =
                                        firstActionError(errors);

                                    return (
                                        <div className="grid gap-4">
                                            <DialogHeader>
                                                <DialogTitle>
                                                    Cancel stock count?
                                                </DialogTitle>
                                                <DialogDescription>
                                                    Cancelling ends this count
                                                    without committing inventory
                                                    adjustments. The server
                                                    remains responsible for
                                                    validating whether the
                                                    transition is allowed.
                                                </DialogDescription>
                                            </DialogHeader>

                                            {actionError !== null && (
                                                <p
                                                    role="alert"
                                                    className="rounded-lg border border-destructive/30 bg-destructive/10 px-3 py-2 text-sm text-destructive"
                                                >
                                                    {actionError}
                                                </p>
                                            )}

                                            <DialogFooter>
                                                <Button
                                                    type="button"
                                                    variant="outline"
                                                    disabled={processing}
                                                    onClick={() =>
                                                        setCancelDialogOpen(
                                                            false,
                                                        )
                                                    }
                                                >
                                                    Keep count
                                                </Button>

                                                <Button
                                                    type="submit"
                                                    variant="destructive"
                                                    disabled={processing}
                                                >
                                                    {processing
                                                        ? 'Cancelling…'
                                                        : 'Cancel count'}
                                                </Button>
                                            </DialogFooter>
                                        </div>
                                    );
                                }}
                            </Form>
                        </DialogContent>
                    </Dialog>
                )}
        </>
    );
}

StockCountForm.layout = {
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: dashboard(),
        },
        {
            title: 'Stock counts',
            href: StockCountController.index(),
        },
    ],
};
