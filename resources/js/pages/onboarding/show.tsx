import { Form, Head, Link, useForm, usePage } from '@inertiajs/react';
import {
    ArrowLeft,
    ArrowRight,
    CheckCircle2,
    Circle,
    CircleDashed,
    FileUp,
    Lock,
    MinusCircle,
    Truck,
    Users,
} from 'lucide-react';
import { useState } from 'react';
import type { ReactNode } from 'react';
import InventoryItemController from '@/actions/App/Http/Controllers/Inventory/InventoryItemController';
import UnitOfMeasureController from '@/actions/App/Http/Controllers/Inventory/UnitOfMeasureController';
import OnboardingController from '@/actions/App/Http/Controllers/OnboardingController';
import OrganizationController from '@/actions/App/Http/Controllers/OrganizationController';
import OrganizationMemberController from '@/actions/App/Http/Controllers/OrganizationMemberController';
import SupplierController from '@/actions/App/Http/Controllers/Suppliers/SupplierController';
import InputError from '@/components/input-error';
import { PageHeader } from '@/components/page-header';
import { StatusBadge } from '@/components/status-badge';
import type { StatusBadgeProps } from '@/components/status-badge';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button, buttonVariants } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Field } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { NativeSelect } from '@/components/ui/native-select';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';

type StepKey =
    | 'organization'
    | 'location'
    | 'units'
    | 'inventory'
    | 'opening_stock'
    | 'suppliers'
    | 'team';

type StepStatus =
    'not_started' | 'in_progress' | 'complete' | 'skipped' | 'locked';

type SetupStep = {
    key: StepKey;
    required: boolean;
    status: StepStatus;
};

type SetupPermissions = {
    manageOrganization: boolean;
    manageLocations: boolean;
    manageInventory: boolean;
    managePurchasing: boolean;
    manageUsers: boolean;
};

type SetupCounts = {
    items: number;
    unresolvedOpeningStock: number;
    suppliers: number;
    supplierItems: number;
    members: number;
};

type SetupLocation = { id: number; name: string; code: string };

type CatalogUnit = {
    symbol: string;
    name: string;
    dimension: string;
    added: boolean;
};

type SetupUnit = { id: number; name: string; symbol: string };

type SetupItem = {
    id: number;
    name: string;
    sku: string;
    unitSymbol: string;
    openingStock: 'recorded' | 'none' | 'unresolved';
};

type InventoryImportResult = {
    mode: 'items' | 'items_with_opening_stock';
    committed: boolean;
    created: number;
    updated: number;
    openingStockRecorded: number;
    rows: {
        row: number;
        sku: string;
        name: string;
        unit: string;
        openingQuantity: string | null;
        openingUnitCost: string | null;
    }[];
    errors: { row: number; messages: string[] }[];
};

type OnboardingProps = {
    organization: { id: number; name: string } | null;
    operationId: string | null;
    currentStep: StepKey;
    ready: boolean;
    steps: SetupStep[];
    permissions?: SetupPermissions;
    purchasingAvailable?: boolean;
    counts?: SetupCounts;
    locations?: SetupLocation[];
    unitCatalog?: CatalogUnit[];
    units?: SetupUnit[];
    items?: SetupItem[];
};

type OrganizationSetupProps = Required<Omit<OnboardingProps, 'operationId'>> & {
    organization: { id: number; name: string };
};

const stepContent: Record<StepKey, { title: string; description: string }> = {
    organization: {
        title: 'Organization',
        description: 'Your business name. Everything else uses safe defaults.',
    },
    location: {
        title: 'Location',
        description: 'Where stock is kept. Only a name is needed now.',
    },
    units: {
        title: 'Units',
        description: 'Choose the units you count and buy stock in.',
    },
    inventory: {
        title: 'Inventory',
        description: 'Add the items you track, one by one or from a CSV file.',
    },
    opening_stock: {
        title: 'Opening stock',
        description:
            'Record how much of each item you have now, or mark it as starting empty.',
    },
    suppliers: {
        title: 'Suppliers',
        description: 'Optional. Add the vendors you buy from.',
    },
    team: {
        title: 'Invite your team',
        description:
            'Optional. Available after the required steps are complete.',
    },
};

const statusPresentation: Record<
    StepStatus,
    {
        label: string;
        variant: StatusBadgeProps['variant'];
        icon: typeof Circle;
    }
> = {
    complete: { label: 'Complete', variant: 'success', icon: CheckCircle2 },
    in_progress: { label: 'In progress', variant: 'info', icon: CircleDashed },
    not_started: { label: 'Not started', variant: 'neutral', icon: Circle },
    skipped: { label: 'Skipped', variant: 'neutral', icon: MinusCircle },
    locked: { label: 'Locked', variant: 'neutral', icon: Lock },
};

const dimensionLabels: Record<string, string> = {
    weight: 'Weight',
    volume: 'Volume',
    count: 'Count and packaging',
};

/** Render the server-derived first-time setup checklist and the current step. */
export default function OnboardingShow(props: OnboardingProps) {
    const { steps, currentStep, ready, organization } = props;

    const requiredSteps = steps.filter((step) => step.required);
    const optionalSteps = steps.filter((step) => !step.required);
    const completedRequired = requiredSteps.filter(
        (step) => step.status === 'complete',
    ).length;

    const stepIndex = steps.findIndex((step) => step.key === currentStep);
    const previousStep = stepIndex > 0 ? steps[stepIndex - 1] : null;
    const nextStep =
        stepIndex >= 0 && stepIndex < steps.length - 1
            ? steps[stepIndex + 1]
            : null;

    return (
        <>
            <Head title="Set up your organization" />

            <div className="flex flex-1 flex-col gap-6 p-4 sm:p-6">
                <PageHeader
                    title="Set up your organization"
                    description="Complete the required steps to start recording stock. Your progress is saved as you go, so you can leave and come back anytime."
                />

                <Alert
                    className={cn(
                        ready
                            ? 'border-success-border bg-success-subtle text-success-foreground'
                            : 'border-info-border bg-info-subtle text-info-foreground',
                    )}
                    role="status"
                >
                    {ready ? (
                        <CheckCircle2 aria-hidden="true" />
                    ) : (
                        <Lock aria-hidden="true" />
                    )}
                    <AlertTitle>
                        {ready
                            ? 'Minimum setup is complete'
                            : `${completedRequired} of ${requiredSteps.length} required steps complete`}
                    </AlertTitle>
                    <AlertDescription className="text-current/80">
                        {ready
                            ? 'Purchasing, receiving, stock counts, transfers, waste, and adjustments are available. You can still finish the optional steps.'
                            : 'Purchasing, receiving, stock counts, transfers, waste, and adjustments stay locked until every required step is complete. You can still browse the rest of MiseLedger.'}
                    </AlertDescription>
                </Alert>

                <div className="grid gap-6 lg:grid-cols-[18rem_minmax(0,1fr)]">
                    <nav
                        aria-label="Setup steps"
                        className="flex flex-col gap-4 self-start rounded-xl border border-border bg-card p-4"
                    >
                        <StepGroup
                            title="Required"
                            steps={requiredSteps}
                            currentStep={currentStep}
                            linkable={organization !== null}
                        />
                        <StepGroup
                            title="Optional"
                            steps={optionalSteps}
                            currentStep={currentStep}
                            linkable={organization !== null}
                        />
                    </nav>

                    <section
                        aria-labelledby="current-step-title"
                        className="flex min-w-0 flex-col rounded-xl border border-border bg-card"
                    >
                        <div className="border-b border-border px-4 py-3 sm:px-5">
                            <h2
                                id="current-step-title"
                                className="text-base font-semibold"
                            >
                                {stepContent[currentStep].title}
                            </h2>
                            <p className="mt-1 text-sm text-muted-foreground">
                                {stepContent[currentStep].description}
                            </p>
                        </div>

                        <div className="flex flex-col gap-6 p-4 sm:p-5">
                            {organization === null ? (
                                <OrganizationCreateStep
                                    operationId={props.operationId ?? ''}
                                />
                            ) : (
                                <CurrentStep
                                    {...(props as OrganizationSetupProps)}
                                />
                            )}
                        </div>

                        {organization !== null && (
                            <div className="mt-auto flex flex-wrap items-center justify-between gap-2 border-t border-border px-4 py-3 sm:px-5">
                                {previousStep ? (
                                    <Link
                                        href={OnboardingController.show({
                                            query: { step: previousStep.key },
                                        })}
                                        className={buttonVariants({
                                            variant: 'outline',
                                            size: 'sm',
                                        })}
                                    >
                                        <ArrowLeft aria-hidden="true" />
                                        Back
                                    </Link>
                                ) : (
                                    <span />
                                )}

                                <div className="flex flex-wrap gap-2">
                                    {ready && (
                                        <Link
                                            href={dashboard()}
                                            className={buttonVariants({
                                                variant: 'outline',
                                                size: 'sm',
                                            })}
                                        >
                                            Go to dashboard
                                        </Link>
                                    )}
                                    {nextStep && (
                                        <Link
                                            href={OnboardingController.show({
                                                query: { step: nextStep.key },
                                            })}
                                            className={buttonVariants({
                                                size: 'sm',
                                            })}
                                        >
                                            Continue to{' '}
                                            {stepContent[
                                                nextStep.key
                                            ].title.toLowerCase()}
                                            <ArrowRight aria-hidden="true" />
                                        </Link>
                                    )}
                                </div>
                            </div>
                        )}
                    </section>
                </div>
            </div>
        </>
    );
}

OnboardingShow.layout = {
    breadcrumbs: [
        {
            title: 'Setup',
            href: OnboardingController.show(),
        },
    ],
};

function StepGroup({
    title,
    steps,
    currentStep,
    linkable,
}: {
    title: string;
    steps: SetupStep[];
    currentStep: StepKey;
    linkable: boolean;
}) {
    return (
        <div>
            <h2 className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                {title}
            </h2>
            <ol className="mt-2 flex flex-col gap-1">
                {steps.map((step) => {
                    const presentation = statusPresentation[step.status];
                    const Icon = presentation.icon;
                    const isCurrent = step.key === currentStep;
                    const content = (
                        <>
                            <Icon
                                className={cn(
                                    'size-4 shrink-0',
                                    step.status === 'complete'
                                        ? 'text-success-foreground'
                                        : 'text-muted-foreground',
                                )}
                                aria-hidden="true"
                            />
                            <span className="min-w-0 flex-1 text-sm font-medium">
                                {stepContent[step.key].title}
                            </span>
                            <StatusBadge
                                label={presentation.label}
                                variant={presentation.variant}
                            />
                        </>
                    );

                    return (
                        <li key={step.key}>
                            {linkable ? (
                                <Link
                                    href={OnboardingController.show({
                                        query: { step: step.key },
                                    })}
                                    aria-current={
                                        isCurrent ? 'step' : undefined
                                    }
                                    className={cn(
                                        'flex min-h-10 items-center gap-2 rounded-md px-2 py-1.5 transition-colors hover:bg-muted/50 focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none',
                                        isCurrent && 'bg-muted',
                                    )}
                                >
                                    {content}
                                </Link>
                            ) : (
                                <div
                                    aria-current={
                                        isCurrent ? 'step' : undefined
                                    }
                                    className={cn(
                                        'flex min-h-10 items-center gap-2 rounded-md px-2 py-1.5',
                                        isCurrent && 'bg-muted',
                                    )}
                                >
                                    {content}
                                </div>
                            )}
                        </li>
                    );
                })}
            </ol>
        </div>
    );
}

function CurrentStep(props: OrganizationSetupProps) {
    switch (props.currentStep) {
        case 'organization':
            return <OrganizationSummaryStep {...props} />;
        case 'location':
            return <LocationStep {...props} />;
        case 'units':
            return <UnitsStep {...props} />;
        case 'inventory':
            return <InventoryStep {...props} />;
        case 'opening_stock':
            return <OpeningStockStep {...props} />;
        case 'suppliers':
            return <SuppliersStep {...props} />;
        case 'team':
            return <TeamStep {...props} />;
    }
}

function PermissionNote({ children }: { children: ReactNode }) {
    return <p className="text-sm text-muted-foreground">{children}</p>;
}

function OrganizationCreateStep({ operationId }: { operationId: string }) {
    return (
        <Form
            {...OrganizationController.store.form()}
            className="grid max-w-xl gap-6"
        >
            {({ processing, errors }) => (
                <>
                    <input
                        type="hidden"
                        name="operation_id"
                        value={operationId}
                    />
                    <Field
                        id="onboarding-organization-name"
                        label="Business name"
                        helper="Time zone and currency start as Asia/Manila and PHP. You can change them later in organization settings."
                        error={errors.name ?? errors.operation_id}
                    >
                        <Input
                            name="name"
                            required
                            maxLength={160}
                            autoFocus
                            autoComplete="organization"
                            placeholder="Example Restaurant"
                        />
                    </Field>
                    <div>
                        <Button type="submit" disabled={processing}>
                            {processing
                                ? 'Creating…'
                                : 'Create organization and continue'}
                        </Button>
                    </div>
                </>
            )}
        </Form>
    );
}

function OrganizationSummaryStep({
    organization,
    permissions,
}: OrganizationSetupProps) {
    return (
        <div className="flex flex-col gap-4">
            <dl className="grid gap-1">
                <dt className="text-xs text-muted-foreground">Business name</dt>
                <dd className="text-sm font-medium">{organization.name}</dd>
            </dl>
            {permissions.manageOrganization ? (
                <div>
                    <Link
                        href={OrganizationController.edit(organization.id)}
                        className={buttonVariants({
                            variant: 'outline',
                            size: 'sm',
                        })}
                    >
                        Edit organization settings
                    </Link>
                </div>
            ) : null}
        </div>
    );
}

function LocationStep({ locations, permissions }: OrganizationSetupProps) {
    return (
        <div className="flex flex-col gap-6">
            {locations.length > 0 ? (
                <div>
                    <h3 className="text-sm font-semibold">Your locations</h3>
                    <ul className="mt-2 divide-y divide-border rounded-lg border border-border">
                        {locations.map((location) => (
                            <li
                                key={location.id}
                                className="flex items-center justify-between gap-2 px-3 py-2 text-sm"
                            >
                                <span className="font-medium">
                                    {location.name}
                                </span>
                                <span className="font-mono text-xs text-muted-foreground">
                                    {location.code}
                                </span>
                            </li>
                        ))}
                    </ul>
                </div>
            ) : null}

            {permissions.manageLocations ? (
                <Form
                    {...OnboardingController.storeLocation.form()}
                    options={{ preserveScroll: true }}
                    resetOnSuccess
                    className="grid max-w-xl gap-4"
                >
                    {({ processing, errors }) => (
                        <>
                            <Field
                                id="onboarding-location-name"
                                label={
                                    locations.length > 0
                                        ? 'Add another location'
                                        : 'Location name'
                                }
                                helper="Address and other details can be added later."
                                error={errors.name}
                            >
                                <Input
                                    name="name"
                                    required
                                    maxLength={160}
                                    placeholder="Main Kitchen"
                                />
                            </Field>
                            <div>
                                <Button type="submit" disabled={processing}>
                                    {processing ? 'Saving…' : 'Save location'}
                                </Button>
                            </div>
                        </>
                    )}
                </Form>
            ) : (
                <PermissionNote>
                    Ask your organization owner to add a location.
                </PermissionNote>
            )}
        </div>
    );
}

function UnitsStep({ unitCatalog, permissions }: OrganizationSetupProps) {
    const dimensions = Array.from(
        new Set(unitCatalog.map((unit) => unit.dimension)),
    );

    if (!permissions.manageInventory) {
        return (
            <PermissionNote>
                Ask your organization owner to choose units.
            </PermissionNote>
        );
    }

    return (
        <Form
            {...OnboardingController.storeUnits.form()}
            options={{ preserveScroll: true }}
            className="flex flex-col gap-6"
        >
            {({ processing, errors }) => (
                <>
                    {dimensions.map((dimension) => (
                        <fieldset key={dimension} className="grid gap-3">
                            <legend className="text-sm font-semibold">
                                {dimensionLabels[dimension] ?? dimension}
                            </legend>
                            <div className="grid gap-2 sm:grid-cols-2 xl:grid-cols-3">
                                {unitCatalog
                                    .filter(
                                        (unit) => unit.dimension === dimension,
                                    )
                                    .map((unit) => (
                                        <div
                                            key={unit.symbol}
                                            className="flex min-h-10 items-center gap-2 rounded-md border border-border px-3 py-2"
                                        >
                                            <Checkbox
                                                id={`unit-${unit.symbol}`}
                                                name="symbols[]"
                                                value={unit.symbol}
                                                defaultChecked={unit.added}
                                                disabled={unit.added}
                                            />
                                            <Label
                                                htmlFor={`unit-${unit.symbol}`}
                                                className="flex-1 font-normal"
                                            >
                                                {unit.name}{' '}
                                                <span className="font-mono text-xs text-muted-foreground">
                                                    ({unit.symbol})
                                                </span>
                                            </Label>
                                            {unit.added && (
                                                <StatusBadge
                                                    label="Added"
                                                    variant="success"
                                                />
                                            )}
                                        </div>
                                    ))}
                            </div>
                        </fieldset>
                    ))}

                    <InputError message={errors.symbols} />

                    <div className="flex flex-wrap items-center gap-2">
                        <Button type="submit" disabled={processing}>
                            {processing ? 'Saving…' : 'Add selected units'}
                        </Button>
                        <Link
                            href={UnitOfMeasureController.index()}
                            className={buttonVariants({
                                variant: 'ghost',
                                size: 'sm',
                            })}
                        >
                            Create a custom unit
                        </Link>
                    </div>
                </>
            )}
        </Form>
    );
}

function InventoryStep(props: OrganizationSetupProps) {
    const { units, counts, permissions, locations } = props;

    if (!permissions.manageInventory) {
        return (
            <PermissionNote>
                Ask your organization owner to add inventory items.
            </PermissionNote>
        );
    }

    return (
        <div className="flex flex-col gap-8">
            <p className="text-sm">
                <span className="font-medium tabular-nums">{counts.items}</span>{' '}
                active {counts.items === 1 ? 'item' : 'items'} so far.{' '}
                <Link
                    href={InventoryItemController.index()}
                    className="underline underline-offset-4"
                >
                    View all items
                </Link>
            </p>

            {units.length === 0 ? (
                <Alert>
                    <AlertTitle>Add units first</AlertTitle>
                    <AlertDescription>
                        Every item needs a base unit. Choose units in the Units
                        step before adding items.
                    </AlertDescription>
                </Alert>
            ) : (
                <ManualItemForm units={units} />
            )}

            {locations.length === 0 ? (
                <Alert>
                    <AlertTitle>CSV import needs a location</AlertTitle>
                    <AlertDescription>
                        Add a location first so opening quantities have a place
                        to go.
                    </AlertDescription>
                </Alert>
            ) : (
                <CsvImport locations={locations} />
            )}
        </div>
    );
}

function ManualItemForm({ units }: { units: SetupUnit[] }) {
    return (
        <section aria-labelledby="manual-item-title" className="grid gap-4">
            <h3 id="manual-item-title" className="text-sm font-semibold">
                Add an item
            </h3>
            <Form
                {...InventoryItemController.store.form()}
                options={{ preserveScroll: true }}
                resetOnSuccess={['name', 'sku']}
                className="grid gap-4 sm:grid-cols-2 xl:grid-cols-[2fr_1fr_1fr_auto] xl:items-start"
            >
                {({ processing, errors }) => (
                    <>
                        <input type="hidden" name="type" value="ingredient" />
                        <input
                            type="hidden"
                            name="yield_percentage"
                            value="100"
                        />
                        <input type="hidden" name="active" value="1" />
                        <input type="hidden" name="_modal" value="1" />
                        <Field
                            id="onboarding-item-name"
                            label="Item name"
                            error={errors.name}
                        >
                            <Input
                                name="name"
                                required
                                maxLength={160}
                                placeholder="Jasmine Rice"
                            />
                        </Field>
                        <Field
                            id="onboarding-item-sku"
                            label="SKU"
                            error={errors.sku}
                        >
                            <Input
                                name="sku"
                                required
                                maxLength={64}
                                placeholder="RICE-JAS"
                                className="font-mono uppercase"
                            />
                        </Field>
                        <Field
                            id="onboarding-item-unit"
                            label="Base unit"
                            error={errors.base_unit_of_measure_id}
                        >
                            <NativeSelect
                                name="base_unit_of_measure_id"
                                required
                                defaultValue=""
                            >
                                <option value="" disabled>
                                    Select unit
                                </option>
                                {units.map((unit) => (
                                    <option key={unit.id} value={unit.id}>
                                        {unit.name} ({unit.symbol})
                                    </option>
                                ))}
                            </NativeSelect>
                        </Field>
                        <div className="xl:pt-[1.625rem]">
                            <Button type="submit" disabled={processing}>
                                {processing ? 'Adding…' : 'Add item'}
                            </Button>
                        </div>
                    </>
                )}
            </Form>
        </section>
    );
}

function CsvImport({ locations }: { locations: SetupLocation[] }) {
    const { flash } = usePage();
    const preview = (flash as { inventoryImport?: InventoryImportResult })
        .inventoryImport;
    const [previewedFile, setPreviewedFile] = useState<File | null>(null);

    const form = useForm<{ file: File | null; location_id: string }>({
        file: null,
        location_id: String(locations[0]?.id ?? ''),
    });

    const canImport =
        form.data.file !== null &&
        previewedFile === form.data.file &&
        preview !== undefined &&
        preview.errors.length === 0 &&
        !preview.committed;

    function submitPreview(): void {
        const file = form.data.file;

        form.post(OnboardingController.previewInventoryImport.url(), {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => setPreviewedFile(file),
        });
    }

    function submitImport(): void {
        form.post(OnboardingController.importInventory.url(), {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => setPreviewedFile(null),
        });
    }

    return (
        <section aria-labelledby="csv-import-title" className="grid gap-4">
            <div>
                <h3 id="csv-import-title" className="text-sm font-semibold">
                    Import from CSV
                </h3>
                <p className="mt-1 text-sm text-muted-foreground">
                    Required columns: <code>sku</code>, <code>name</code>,{' '}
                    <code>base_unit_symbol</code>. To record opening stock in
                    the same import, add <code>opening_quantity</code> and{' '}
                    <code>opening_unit_cost</code> (in the item&apos;s base
                    unit). Leave a quantity blank to decide later. Nothing is
                    saved until you import a file with no errors.
                </p>
            </div>

            <form
                className="grid gap-4 sm:grid-cols-2"
                onSubmit={(event) => {
                    event.preventDefault();
                    submitPreview();
                }}
            >
                <div className="grid gap-2">
                    <Label htmlFor="onboarding-csv-file">CSV file</Label>
                    <Input
                        id="onboarding-csv-file"
                        type="file"
                        accept=".csv,text/csv"
                        required
                        aria-invalid={Boolean(form.errors.file)}
                        aria-describedby={
                            form.errors.file
                                ? 'onboarding-csv-file-error'
                                : undefined
                        }
                        onChange={(event) =>
                            form.setData(
                                'file',
                                event.currentTarget.files?.[0] ?? null,
                            )
                        }
                    />
                    <InputError
                        id="onboarding-csv-file-error"
                        message={form.errors.file}
                    />
                </div>
                <Field
                    id="onboarding-csv-location"
                    label="Location for opening quantities"
                    error={form.errors.location_id}
                >
                    <NativeSelect
                        value={form.data.location_id}
                        onChange={(event) =>
                            form.setData('location_id', event.target.value)
                        }
                    >
                        {locations.map((location) => (
                            <option key={location.id} value={location.id}>
                                {location.name}
                            </option>
                        ))}
                    </NativeSelect>
                </Field>
                <div className="flex flex-wrap gap-2 sm:col-span-2">
                    <Button
                        type="submit"
                        variant="outline"
                        disabled={form.processing || form.data.file === null}
                    >
                        <FileUp aria-hidden="true" />
                        {form.processing ? 'Checking…' : 'Check file'}
                    </Button>
                    <Button
                        type="button"
                        disabled={form.processing || !canImport}
                        onClick={submitImport}
                    >
                        Import items
                    </Button>
                </div>
            </form>

            {preview ? <CsvPreview result={preview} /> : null}
        </section>
    );
}

function CsvPreview({ result }: { result: InventoryImportResult }) {
    const errorRows = new Map(
        result.errors.map((error) => [error.row, error.messages]),
    );
    const withOpeningStock = result.mode === 'items_with_opening_stock';
    const visibleRows = result.rows.slice(0, 50);

    return (
        <div className="grid gap-3" aria-live="polite">
            {result.errors.length > 0 ? (
                <Alert variant="destructive">
                    <AlertTitle>
                        {result.errors.length}{' '}
                        {result.errors.length === 1 ? 'row needs' : 'rows need'}{' '}
                        fixing. Nothing was saved.
                    </AlertTitle>
                    <AlertDescription>
                        <ul className="list-disc pl-4">
                            {result.errors.map((error) => (
                                <li key={error.row}>
                                    Row {error.row}: {error.messages.join(' ')}
                                </li>
                            ))}
                        </ul>
                    </AlertDescription>
                </Alert>
            ) : (
                <Alert className="border-success-border bg-success-subtle text-success-foreground">
                    <CheckCircle2 aria-hidden="true" />
                    <AlertTitle>File is ready to import</AlertTitle>
                    <AlertDescription className="text-current/80">
                        {result.created} new and {result.updated} updated{' '}
                        {withOpeningStock
                            ? `items, with opening stock for ${result.openingStockRecorded}.`
                            : 'items. Opening stock can be resolved in the next step.'}
                    </AlertDescription>
                </Alert>
            )}

            <div className="overflow-x-auto rounded-lg border border-border">
                <table className="w-full min-w-[36rem] text-sm">
                    <caption className="sr-only">CSV import preview</caption>
                    <thead className="bg-muted/40 text-left text-xs font-medium text-muted-foreground">
                        <tr>
                            <th scope="col" className="px-3 py-2">
                                Row
                            </th>
                            <th scope="col" className="px-3 py-2">
                                SKU
                            </th>
                            <th scope="col" className="px-3 py-2">
                                Name
                            </th>
                            <th scope="col" className="px-3 py-2">
                                Unit
                            </th>
                            {withOpeningStock && (
                                <th
                                    scope="col"
                                    className="px-3 py-2 text-right"
                                >
                                    Opening qty
                                </th>
                            )}
                            <th scope="col" className="px-3 py-2">
                                Status
                            </th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-border">
                        {visibleRows.map((row) => (
                            <tr key={row.row}>
                                <td className="px-3 py-2 tabular-nums">
                                    {row.row}
                                </td>
                                <td className="px-3 py-2 font-mono">
                                    {row.sku}
                                </td>
                                <td className="px-3 py-2">{row.name}</td>
                                <td className="px-3 py-2 font-mono">
                                    {row.unit}
                                </td>
                                {withOpeningStock && (
                                    <td className="px-3 py-2 text-right tabular-nums">
                                        {row.openingQuantity ?? 'Decide later'}
                                    </td>
                                )}
                                <td className="px-3 py-2">
                                    {errorRows.has(row.row) ? (
                                        <StatusBadge
                                            label="Needs fixing"
                                            variant="danger"
                                        />
                                    ) : (
                                        <StatusBadge
                                            label="Ready"
                                            variant="success"
                                        />
                                    )}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
            {result.rows.length > visibleRows.length && (
                <p className="text-xs text-muted-foreground">
                    Showing the first {visibleRows.length} of{' '}
                    {result.rows.length} rows.
                </p>
            )}
        </div>
    );
}

function OpeningStockStep({
    items,
    locations,
    counts,
    permissions,
}: OrganizationSetupProps) {
    if (items.length === 0) {
        return (
            <PermissionNote>
                Add inventory items first. Each item then needs its opening
                stock resolved here.
            </PermissionNote>
        );
    }

    if (locations.length === 0) {
        return (
            <PermissionNote>
                Add a location first so opening stock has a place to go.
            </PermissionNote>
        );
    }

    return (
        <div className="flex flex-col gap-4">
            <p className="text-sm">
                <span className="font-medium tabular-nums">
                    {counts.unresolvedOpeningStock}
                </span>{' '}
                {counts.unresolvedOpeningStock === 1
                    ? 'item still needs'
                    : 'items still need'}{' '}
                a decision. Enter the quantity on hand in each item&apos;s base
                unit, or choose <strong>No opening stock</strong> if it starts
                empty. A blank or zero quantity is never treated as empty.
            </p>

            <ul className="flex flex-col gap-3">
                {items.map((item) => (
                    <OpeningStockRow
                        key={item.id}
                        item={item}
                        locations={locations}
                        canEdit={permissions.manageInventory}
                    />
                ))}
            </ul>
        </div>
    );
}

function OpeningStockRow({
    item,
    locations,
    canEdit,
}: {
    item: SetupItem;
    locations: SetupLocation[];
    canEdit: boolean;
}) {
    const status =
        item.openingStock === 'recorded'
            ? { label: 'Recorded', variant: 'success' as const }
            : item.openingStock === 'none'
              ? { label: 'No opening stock', variant: 'neutral' as const }
              : { label: 'Needs decision', variant: 'warning' as const };
    const fieldPrefix = `opening-${item.id}`;

    return (
        <li className="rounded-lg border border-border p-3">
            <div className="flex flex-wrap items-center justify-between gap-2">
                <div className="min-w-0">
                    <p className="text-sm font-medium">{item.name}</p>
                    <p className="font-mono text-xs text-muted-foreground">
                        {item.sku} · {item.unitSymbol}
                    </p>
                </div>
                <StatusBadge label={status.label} variant={status.variant} />
            </div>

            {canEdit && item.openingStock !== 'recorded' && (
                <div className="mt-3 flex flex-col gap-3 lg:flex-row lg:items-start">
                    <Form
                        {...OnboardingController.resolveOpeningStock.form(
                            item.id,
                        )}
                        options={{ preserveScroll: true }}
                        className="grid flex-1 gap-3 sm:grid-cols-3 sm:items-start"
                    >
                        {({ processing, errors }) => (
                            <>
                                <input
                                    type="hidden"
                                    name="resolution"
                                    value="quantity"
                                />
                                <Field
                                    id={`${fieldPrefix}-quantity`}
                                    label={`Quantity (${item.unitSymbol})`}
                                    error={errors.quantity}
                                >
                                    <Input
                                        name="quantity"
                                        inputMode="decimal"
                                        required
                                        className="tabular-nums"
                                    />
                                </Field>
                                <Field
                                    id={`${fieldPrefix}-cost`}
                                    label={`Cost per ${item.unitSymbol}`}
                                    error={errors.base_unit_cost}
                                >
                                    <Input
                                        name="base_unit_cost"
                                        inputMode="decimal"
                                        required
                                        className="tabular-nums"
                                    />
                                </Field>
                                {locations.length > 1 ? (
                                    <Field
                                        id={`${fieldPrefix}-location`}
                                        label="Location"
                                        error={errors.location_id}
                                    >
                                        <NativeSelect
                                            name="location_id"
                                            defaultValue={locations[0].id}
                                        >
                                            {locations.map((location) => (
                                                <option
                                                    key={location.id}
                                                    value={location.id}
                                                >
                                                    {location.name}
                                                </option>
                                            ))}
                                        </NativeSelect>
                                    </Field>
                                ) : (
                                    <input
                                        type="hidden"
                                        name="location_id"
                                        value={locations[0].id}
                                    />
                                )}
                                <div className="sm:col-span-3">
                                    <Button
                                        type="submit"
                                        size="sm"
                                        disabled={processing}
                                    >
                                        {processing
                                            ? 'Saving…'
                                            : `Save opening stock for ${item.name}`}
                                    </Button>
                                </div>
                            </>
                        )}
                    </Form>

                    {item.openingStock === 'unresolved' && (
                        <Form
                            {...OnboardingController.resolveOpeningStock.form(
                                item.id,
                            )}
                            options={{ preserveScroll: true }}
                            className="lg:pt-[1.625rem]"
                        >
                            {({ processing, errors }) => (
                                <>
                                    <input
                                        type="hidden"
                                        name="resolution"
                                        value="none"
                                    />
                                    <Button
                                        type="submit"
                                        size="sm"
                                        variant="outline"
                                        disabled={processing}
                                        aria-label={`Mark ${item.name} as having no opening stock`}
                                    >
                                        No opening stock
                                    </Button>
                                    <InputError message={errors.resolution} />
                                </>
                            )}
                        </Form>
                    )}
                </div>
            )}
        </li>
    );
}

function SkipForm({
    action,
    label,
}: {
    action: ReturnType<typeof OnboardingController.skipSuppliers.form>;
    label: string;
}) {
    return (
        <Form {...action}>
            {({ processing }) => (
                <Button
                    type="submit"
                    variant="ghost"
                    size="sm"
                    disabled={processing}
                >
                    {processing ? 'Skipping…' : label}
                </Button>
            )}
        </Form>
    );
}

function SuppliersStep({
    steps,
    counts,
    permissions,
    purchasingAvailable,
}: OrganizationSetupProps) {
    const status = steps.find((step) => step.key === 'suppliers')?.status;

    return (
        <div className="flex flex-col gap-4">
            <p className="text-sm">
                Suppliers are optional and never block setup. Adding a supplier
                and the items you buy from them makes purchase orders faster
                later.
            </p>
            <p className="text-sm text-muted-foreground">
                <span className="tabular-nums">{counts.suppliers}</span>{' '}
                {counts.suppliers === 1 ? 'supplier' : 'suppliers'},{' '}
                <span className="tabular-nums">{counts.supplierItems}</span>{' '}
                supplier {counts.supplierItems === 1 ? 'item' : 'items'}.
            </p>

            <div className="flex flex-wrap items-center gap-2">
                {purchasingAvailable && permissions.managePurchasing ? (
                    <Link
                        href={SupplierController.create()}
                        className={buttonVariants({ size: 'sm' })}
                    >
                        <Truck aria-hidden="true" />
                        Add a supplier
                    </Link>
                ) : (
                    <PermissionNote>
                        {purchasingAvailable
                            ? 'Ask your organization owner to add suppliers.'
                            : 'Supplier management is not included in your current plan.'}
                    </PermissionNote>
                )}
                {permissions.manageOrganization &&
                    status !== 'complete' &&
                    status !== 'skipped' && (
                        <SkipForm
                            action={OnboardingController.skipSuppliers.form()}
                            label="Skip suppliers for now"
                        />
                    )}
            </div>
        </div>
    );
}

function TeamStep({
    steps,
    counts,
    permissions,
    organization,
}: OrganizationSetupProps) {
    const status = steps.find((step) => step.key === 'team')?.status;

    if (status === 'locked') {
        return (
            <PermissionNote>
                Team invitations open once the required steps are complete, so
                new members start with a ready workspace.
            </PermissionNote>
        );
    }

    return (
        <div className="flex flex-col gap-4">
            <p className="text-sm">
                Invite people who already have a MiseLedger account and choose
                their role.{' '}
                <span className="text-muted-foreground">
                    <span className="tabular-nums">{counts.members}</span>{' '}
                    {counts.members === 1 ? 'member' : 'members'} so far.
                </span>
            </p>
            <div className="flex flex-wrap items-center gap-2">
                {permissions.manageUsers ? (
                    <Link
                        href={OrganizationMemberController.index(
                            organization.id,
                        )}
                        className={buttonVariants({ size: 'sm' })}
                    >
                        <Users aria-hidden="true" />
                        Invite team members
                    </Link>
                ) : (
                    <PermissionNote>
                        Ask your organization owner to invite team members.
                    </PermissionNote>
                )}
                {permissions.manageOrganization &&
                    status !== 'complete' &&
                    status !== 'skipped' && (
                        <SkipForm
                            action={OnboardingController.skipTeam.form()}
                            label="Skip for now"
                        />
                    )}
            </div>
        </div>
    );
}
