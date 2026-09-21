import { Form, Head } from '@inertiajs/react';
import { Plus, Trash2 } from 'lucide-react';
import { useEffect, useId, useState } from 'react';
import type { ReactNode } from 'react';

import PlatformProductCatalogController from '@/actions/App/Http/Controllers/Platform/PlatformProductCatalogController';
import { PreviousPageButton } from '@/components/navigation/previous-page-button';
import { PageHeader } from '@/components/page-header';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Field } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { NativeSelect } from '@/components/ui/native-select';
import { useDirtyFormNavigation } from '@/hooks/use-dirty-form-navigation';

type Option = {
    value: string;
    label: string;
};

type Price = {
    id: number;
    provider: string;
    collectionMethod: string;
    interval: string;
    currency: string;
    amountMinor: string;
};

type PlanVersion = {
    id: number;
    version: number;
    name: string;
    tier: number;
    status: 'draft' | 'published' | 'superseded';
    featureCodes: string[];
    limits: Record<string, number | null>;
    prices: Price[];
    subscriberCount: number;
};

type Props = {
    plan: {
        planCode: string;
        name: string;
    };
    version: PlanVersion;
    featureOptions: Option[];
    limitOptions: Option[];
    providerOptions: Option[];
    collectionMethodOptions: Option[];
    intervalOptions: Option[];
};

type PriceRow = {
    key: string;
    provider: string;
    collectionMethod: string;
    interval: string;
    currency: string;
    amountMinor: string;
};

let priceRowSequence = 0;

function nextPriceRowKey(): string {
    priceRowSequence += 1;

    return `price-${priceRowSequence}`;
}

function toPriceRow(price: Price): PriceRow {
    return {
        key: nextPriceRowKey(),
        provider: price.provider,
        collectionMethod: price.collectionMethod,
        interval: price.interval,
        currency: price.currency,
        amountMinor: price.amountMinor,
    };
}

function DirtyStateTracker({
    dirty,
    successful,
    onChange,
}: {
    dirty: boolean;
    successful: boolean;
    onChange: (dirty: boolean) => void;
}) {
    useEffect(() => {
        onChange(dirty && !successful);
    }, [dirty, onChange, successful]);

    return null;
}

/** Publish this draft, requiring an explicit subscriber-pinning acknowledgement. */
function PublishDialog({
    versionId,
    trigger,
}: {
    versionId: number;
    trigger: ReactNode;
}) {
    const [open, setOpen] = useState(false);
    const confirmId = useId();

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>{trigger}</DialogTrigger>

            <DialogContent className="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Publish this version?</DialogTitle>
                    <DialogDescription>
                        This makes the version the current plan for new
                        acquisition and supersedes the prior current version.
                        Existing subscribers remain pinned to their
                        already-resolved plan version and are not repriced or
                        moved by this action.
                    </DialogDescription>
                </DialogHeader>

                <Form
                    {...PlatformProductCatalogController.publish.form(
                        versionId,
                    )}
                    onSuccess={() => setOpen(false)}
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="flex items-start gap-2">
                                <Checkbox
                                    id={confirmId}
                                    name="confirm"
                                    value="1"
                                    required
                                    aria-describedby={
                                        errors.confirm
                                            ? `${confirmId}-error`
                                            : undefined
                                    }
                                />
                                <Label
                                    htmlFor={confirmId}
                                    className="text-sm leading-snug font-normal"
                                >
                                    I understand existing subscribers stay on
                                    their current plan version and will not be
                                    repriced.
                                </Label>
                            </div>

                            {errors.confirm ? (
                                <p
                                    id={`${confirmId}-error`}
                                    className="mt-2 text-sm text-destructive"
                                >
                                    {errors.confirm}
                                </p>
                            ) : null}

                            <DialogFooter className="mt-5">
                                <Button
                                    type="button"
                                    variant="outline"
                                    disabled={processing}
                                    onClick={() => setOpen(false)}
                                >
                                    Cancel
                                </Button>

                                <Button type="submit" disabled={processing}>
                                    {processing
                                        ? 'Publishing…'
                                        : 'Publish version'}
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

/** Edit a draft commercial version's entitlements, limits, and prices. */
export default function PlatformProductCatalogVersionEdit({
    plan,
    version,
    featureOptions,
    limitOptions,
    providerOptions,
    collectionMethodOptions,
    intervalOptions,
}: Props) {
    const dirtyFormNavigation = useDirtyFormNavigation(
        'You have unsaved draft version changes. Leave without saving them?',
    );

    const [priceRows, setPriceRows] = useState<PriceRow[]>(() =>
        version.prices.map(toPriceRow),
    );

    const addPriceRow = () => {
        setPriceRows((rows) => [
            ...rows,
            {
                key: nextPriceRowKey(),
                provider: providerOptions[0]?.value ?? '',
                collectionMethod: collectionMethodOptions[0]?.value ?? '',
                interval: intervalOptions[0]?.value ?? 'monthly',
                currency: 'PHP',
                amountMinor: '',
            },
        ]);
    };

    const removePriceRow = (key: string) => {
        setPriceRows((rows) => rows.filter((row) => row.key !== key));
    };

    return (
        <>
            <Head title={`Edit ${plan.name} draft`} />

            <div className="flex flex-1 flex-col gap-6 p-4 sm:p-6">
                <PageHeader
                    title={`${plan.name} · Draft version ${version.version}`}
                    description="Published versions are immutable. This draft is only visible on the platform console until it is published."
                    actions={
                        <>
                            <StatusBadge label="Draft" variant="warning" />

                            <PublishDialog
                                versionId={version.id}
                                trigger={
                                    <Button variant="outline">
                                        Publish version
                                    </Button>
                                }
                            />

                            <PreviousPageButton
                                variant="outline"
                                fallback={
                                    PlatformProductCatalogController.show(
                                        plan.planCode,
                                    ).url
                                }
                                onNavigate={
                                    dirtyFormNavigation.confirmNavigation
                                }
                            >
                                Back to plan
                            </PreviousPageButton>
                        </>
                    }
                />

                <Form
                    {...PlatformProductCatalogController.update.form(
                        version.id,
                    )}
                    className="space-y-6"
                >
                    {({ processing, errors, isDirty, wasSuccessful }) => (
                        <>
                            <DirtyStateTracker
                                dirty={isDirty}
                                successful={wasSuccessful}
                                onChange={dirtyFormNavigation.setIsDirty}
                            />

                            <div className="rounded-xl border border-border bg-card p-5 shadow-sm">
                                <h2 className="mb-5 font-medium">
                                    Plan identity
                                </h2>

                                <div className="grid gap-5 sm:grid-cols-2">
                                    <Field
                                        id="version-name"
                                        label="Plan name"
                                        error={errors.name}
                                    >
                                        <Input
                                            name="name"
                                            required
                                            maxLength={160}
                                            defaultValue={version.name}
                                        />
                                    </Field>

                                    <Field
                                        id="version-tier"
                                        label="Tier"
                                        helper="The sole precedence signal for upgrade eligibility."
                                        error={errors.tier}
                                    >
                                        <Input
                                            name="tier"
                                            type="number"
                                            min="1"
                                            step="1"
                                            required
                                            defaultValue={version.tier}
                                        />
                                    </Field>
                                </div>
                            </div>

                            <div className="rounded-xl border border-border bg-card p-5 shadow-sm">
                                <h2 className="mb-1 font-medium">
                                    Feature entitlements
                                </h2>
                                <p className="mb-4 text-sm text-muted-foreground">
                                    Only recognized feature codes may be
                                    granted. Arbitrary feature text is never
                                    accepted.
                                </p>

                                {errors.feature_codes ? (
                                    <p className="mb-3 text-sm text-destructive">
                                        {errors.feature_codes}
                                    </p>
                                ) : null}

                                <div className="grid gap-3 sm:grid-cols-2">
                                    {featureOptions.map((option) => (
                                        <div
                                            key={option.value}
                                            className="flex items-center gap-2"
                                        >
                                            <Checkbox
                                                id={`feature-${option.value}`}
                                                name="feature_codes[]"
                                                value={option.value}
                                                defaultChecked={version.featureCodes.includes(
                                                    option.value,
                                                )}
                                            />
                                            <Label
                                                htmlFor={`feature-${option.value}`}
                                                className="font-normal"
                                            >
                                                {option.label}
                                            </Label>
                                        </div>
                                    ))}
                                </div>
                            </div>

                            <div className="rounded-xl border border-border bg-card p-5 shadow-sm">
                                <h2 className="mb-1 font-medium">
                                    Usage limits
                                </h2>
                                <p className="mb-4 text-sm text-muted-foreground">
                                    Every limit must be set. Leave a limit blank
                                    to make it explicitly unlimited.
                                </p>

                                {errors.limits ? (
                                    <p className="mb-3 text-sm text-destructive">
                                        {errors.limits}
                                    </p>
                                ) : null}

                                <div className="grid gap-5 sm:grid-cols-3">
                                    {limitOptions.map((option) => (
                                        <Field
                                            key={option.value}
                                            id={`limit-${option.value}`}
                                            label={option.label}
                                        >
                                            <Input
                                                name={`limits[${option.value}]`}
                                                type="number"
                                                min="0"
                                                step="1"
                                                placeholder="Unlimited"
                                                defaultValue={
                                                    version.limits[
                                                        option.value
                                                    ] ?? ''
                                                }
                                            />
                                        </Field>
                                    ))}
                                </div>
                            </div>

                            <div className="rounded-xl border border-border bg-card p-5 shadow-sm">
                                <div className="mb-4 flex items-center justify-between">
                                    <div>
                                        <h2 className="font-medium">
                                            Recurring prices
                                        </h2>
                                        <p className="text-sm text-muted-foreground">
                                            Amounts are exact minor units (e.g.
                                            49900 for ₱499.00). At least one
                                            price is required before publishing.
                                        </p>
                                    </div>

                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        onClick={addPriceRow}
                                    >
                                        <Plus
                                            className="size-4"
                                            aria-hidden="true"
                                        />
                                        Add price
                                    </Button>
                                </div>

                                {errors.prices ? (
                                    <p className="mb-3 text-sm text-destructive">
                                        {errors.prices}
                                    </p>
                                ) : null}

                                {priceRows.length === 0 ? (
                                    <p className="text-sm text-muted-foreground">
                                        No prices added yet.
                                    </p>
                                ) : (
                                    <div className="space-y-4">
                                        {priceRows.map((row, index) => (
                                            <div
                                                key={row.key}
                                                className="grid gap-3 rounded-lg border border-border p-3 sm:grid-cols-[1fr_1fr_1fr_6rem_8rem_auto] sm:items-end"
                                            >
                                                <Field
                                                    id={`price-${row.key}-provider`}
                                                    label="Provider"
                                                >
                                                    <NativeSelect
                                                        name={`prices[${index}][provider]`}
                                                        defaultValue={
                                                            row.provider
                                                        }
                                                    >
                                                        {providerOptions.map(
                                                            (option) => (
                                                                <option
                                                                    key={
                                                                        option.value
                                                                    }
                                                                    value={
                                                                        option.value
                                                                    }
                                                                >
                                                                    {
                                                                        option.label
                                                                    }
                                                                </option>
                                                            ),
                                                        )}
                                                    </NativeSelect>
                                                </Field>

                                                <Field
                                                    id={`price-${row.key}-collection`}
                                                    label="Collection"
                                                >
                                                    <NativeSelect
                                                        name={`prices[${index}][collection_method]`}
                                                        defaultValue={
                                                            row.collectionMethod
                                                        }
                                                    >
                                                        {collectionMethodOptions.map(
                                                            (option) => (
                                                                <option
                                                                    key={
                                                                        option.value
                                                                    }
                                                                    value={
                                                                        option.value
                                                                    }
                                                                >
                                                                    {
                                                                        option.label
                                                                    }
                                                                </option>
                                                            ),
                                                        )}
                                                    </NativeSelect>
                                                </Field>

                                                <Field
                                                    id={`price-${row.key}-interval`}
                                                    label="Interval"
                                                >
                                                    <NativeSelect
                                                        name={`prices[${index}][interval]`}
                                                        defaultValue={
                                                            row.interval
                                                        }
                                                    >
                                                        {intervalOptions.map(
                                                            (option) => (
                                                                <option
                                                                    key={
                                                                        option.value
                                                                    }
                                                                    value={
                                                                        option.value
                                                                    }
                                                                >
                                                                    {
                                                                        option.label
                                                                    }
                                                                </option>
                                                            ),
                                                        )}
                                                    </NativeSelect>
                                                </Field>

                                                <Field
                                                    id={`price-${row.key}-currency`}
                                                    label="Currency"
                                                >
                                                    <Input
                                                        name={`prices[${index}][currency]`}
                                                        maxLength={3}
                                                        className="uppercase"
                                                        defaultValue={
                                                            row.currency
                                                        }
                                                    />
                                                </Field>

                                                <Field
                                                    id={`price-${row.key}-amount`}
                                                    label="Amount (minor)"
                                                >
                                                    <Input
                                                        name={`prices[${index}][amount_minor]`}
                                                        type="number"
                                                        min="1"
                                                        step="1"
                                                        defaultValue={
                                                            row.amountMinor
                                                        }
                                                    />
                                                </Field>

                                                <Button
                                                    type="button"
                                                    variant="outline"
                                                    size="icon"
                                                    aria-label="Remove price"
                                                    onClick={() =>
                                                        removePriceRow(row.key)
                                                    }
                                                >
                                                    <Trash2
                                                        className="size-4"
                                                        aria-hidden="true"
                                                    />
                                                </Button>
                                            </div>
                                        ))}
                                    </div>
                                )}
                            </div>

                            <div className="flex justify-end">
                                <Button type="submit" disabled={processing}>
                                    {processing
                                        ? 'Saving…'
                                        : 'Save draft version'}
                                </Button>
                            </div>
                        </>
                    )}
                </Form>
            </div>
        </>
    );
}
