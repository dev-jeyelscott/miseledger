import { Form, Head, Link } from '@inertiajs/react';
import { History, Pencil, Plus } from 'lucide-react';
import type { ReactNode } from 'react';

import PlatformProductCatalogController from '@/actions/App/Http/Controllers/Platform/PlatformProductCatalogController';
import { EmptyState } from '@/components/empty-state';
import { PreviousPageButton } from '@/components/navigation/previous-page-button';
import { PageHeader } from '@/components/page-header';
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
import { useGuardedDialog } from '@/hooks/use-guarded-dialog';

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

type Actor = {
    name: string;
    email: string;
};

type VersionStatus = 'draft' | 'published' | 'superseded';

type PlanVersion = {
    id: number;
    version: number;
    name: string;
    tier: number;
    status: VersionStatus;
    featureCodes: string[];
    limits: Record<string, number | null>;
    prices: Price[];
    subscriberCount: number;
    createdBy: Actor | null;
    createdAt: string | null;
    publishedBy: Actor | null;
    publishedAt: string | null;
    supersededAt: string | null;
};

type Props = {
    plan: {
        planCode: string;
        name: string;
        tier: number;
    };
    versions: PlanVersion[];
    hasDraft: boolean;
    featureOptions: Option[];
    limitOptions: Option[];
};

/** Format a server timestamp in the platform operator's local timezone. */
function formatDateTime(value: string | null): string {
    if (value === null) {
        return 'Unavailable';
    }

    return new Intl.DateTimeFormat(undefined, {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
    }).format(new Date(value));
}

function versionStatusVariant(
    status: VersionStatus,
): 'success' | 'warning' | 'neutral' {
    if (status === 'published') {
        return 'success';
    }

    if (status === 'draft') {
        return 'warning';
    }

    return 'neutral';
}

function versionStatusLabel(status: VersionStatus): string {
    if (status === 'published') {
        return 'Published';
    }

    if (status === 'draft') {
        return 'Draft';
    }

    return 'Superseded';
}

function formatPrice(price: Price): string {
    const amount = (Number(price.amountMinor) / 100).toFixed(2);

    return `${price.currency} ${amount} / ${price.interval}`;
}

/** Create a new draft version for this plan code without leaving the page. */
function CreateDraftDialog({
    planCode,
    trigger,
}: {
    planCode: string;
    trigger: ReactNode;
}) {
    const dialog = useGuardedDialog(
        'Discard the new draft version you entered?',
    );

    return (
        <Dialog open={dialog.open} onOpenChange={dialog.onOpenChange}>
            <DialogTrigger asChild>{trigger}</DialogTrigger>

            <DialogContent className="max-h-[calc(100vh-2rem)] overflow-y-auto sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>Create draft version</DialogTitle>
                    <DialogDescription>
                        Start a new draft commercial version for this plan.
                        Feature entitlements, limits, and prices are set here
                        and on the edit page before publishing.
                    </DialogDescription>
                </DialogHeader>

                <div onChange={dialog.markDirty}>
                    <Form
                        {...PlatformProductCatalogController.store.form(
                            planCode,
                        )}
                        className="space-y-5"
                        onSuccess={dialog.closeAfterSuccess}
                    >
                        {({ processing, errors }) => (
                            <>
                                <Field
                                    id="draft-name"
                                    label="Plan name"
                                    error={errors.name}
                                >
                                    <Input
                                        name="name"
                                        required
                                        autoFocus
                                        maxLength={160}
                                    />
                                </Field>

                                <Field
                                    id="draft-tier"
                                    label="Tier"
                                    helper="The sole precedence signal for upgrade eligibility. Higher numbers outrank lower ones."
                                    error={errors.tier}
                                >
                                    <Input
                                        name="tier"
                                        type="number"
                                        min="1"
                                        step="1"
                                        required
                                        defaultValue={1}
                                    />
                                </Field>

                                <p className="text-sm text-muted-foreground">
                                    Feature entitlements, usage limits, and
                                    prices are configured on the edit page after
                                    creating this draft.
                                </p>

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
                                        <Plus
                                            className="size-4"
                                            aria-hidden="true"
                                        />
                                        {processing
                                            ? 'Creating…'
                                            : 'Create draft'}
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

/** Render one version's read-only entitlement and pricing summary. */
function VersionDetails({
    version,
    featureOptions,
    limitOptions,
}: {
    version: PlanVersion;
    featureOptions: Option[];
    limitOptions: Option[];
}) {
    const featureLabels = new Map(
        featureOptions.map((option) => [option.value, option.label]),
    );
    const limitLabels = new Map(
        limitOptions.map((option) => [option.value, option.label]),
    );

    return (
        <div className="grid gap-4 sm:grid-cols-2">
            <div>
                <dt className="text-xs text-muted-foreground">Features</dt>
                <dd className="mt-1">
                    {version.featureCodes.length === 0 ? (
                        <span className="text-sm text-muted-foreground">
                            None
                        </span>
                    ) : (
                        <ul className="flex flex-wrap gap-1.5">
                            {version.featureCodes.map((code) => (
                                <li key={code}>
                                    <StatusBadge
                                        label={featureLabels.get(code) ?? code}
                                        variant="info"
                                    />
                                </li>
                            ))}
                        </ul>
                    )}
                </dd>
            </div>

            <div>
                <dt className="text-xs text-muted-foreground">Usage limits</dt>
                <dd className="mt-1 space-y-1 text-sm">
                    {Object.entries(version.limits).map(([key, value]) => (
                        <div key={key} className="flex justify-between gap-4">
                            <span>{limitLabels.get(key) ?? key}</span>
                            <span className="tabular-nums">
                                {value === null
                                    ? 'Unlimited'
                                    : value.toLocaleString()}
                            </span>
                        </div>
                    ))}
                </dd>
            </div>

            <div className="sm:col-span-2">
                <dt className="text-xs text-muted-foreground">
                    Recurring prices
                </dt>
                <dd className="mt-1">
                    {version.prices.length === 0 ? (
                        <span className="text-sm text-muted-foreground">
                            No prices recorded
                        </span>
                    ) : (
                        <ul className="flex flex-wrap gap-1.5">
                            {version.prices.map((price) => (
                                <li key={price.id}>
                                    <StatusBadge
                                        label={formatPrice(price)}
                                        variant="neutral"
                                    />
                                </li>
                            ))}
                        </ul>
                    )}
                </dd>
            </div>
        </div>
    );
}

/** Render one plan code's complete, append-only version history. */
export default function PlatformProductCatalogShow({
    plan,
    versions,
    hasDraft,
    featureOptions,
    limitOptions,
}: Props) {
    return (
        <>
            <Head title={plan.name} />

            <div className="flex flex-1 flex-col gap-6 p-4 sm:p-6">
                <PageHeader
                    title={plan.name}
                    description={
                        <>
                            Plan code{' '}
                            <span className="font-mono">{plan.planCode}</span> ·
                            Tier {plan.tier}
                        </>
                    }
                    actions={
                        <>
                            {!hasDraft ? (
                                <CreateDraftDialog
                                    planCode={plan.planCode}
                                    trigger={
                                        <Button>
                                            <Plus
                                                className="size-4"
                                                aria-hidden="true"
                                            />
                                            Create draft version
                                        </Button>
                                    }
                                />
                            ) : null}

                            <PreviousPageButton
                                variant="outline"
                                fallback={
                                    PlatformProductCatalogController.index().url
                                }
                            >
                                Back to catalog
                            </PreviousPageButton>
                        </>
                    }
                />

                {versions.length === 0 ? (
                    <div className="rounded-xl border border-border bg-card">
                        <EmptyState
                            className="px-4 py-12"
                            icon={History}
                            title="No versions yet"
                            description="Create a draft version to start this plan's commercial configuration."
                        />
                    </div>
                ) : (
                    <div className="space-y-4">
                        {versions.map((version) => (
                            <section
                                key={version.id}
                                aria-labelledby={`version-${version.id}-heading`}
                                className="rounded-xl border border-border bg-card p-4 sm:p-5"
                            >
                                <div className="flex flex-wrap items-start justify-between gap-3">
                                    <div>
                                        <h2
                                            id={`version-${version.id}-heading`}
                                            className="text-sm font-semibold"
                                        >
                                            Version {version.version}
                                        </h2>
                                        <p className="mt-1 text-sm text-muted-foreground">
                                            {version.name} · Tier {version.tier}{' '}
                                            ·{' '}
                                            {version.subscriberCount.toLocaleString()}{' '}
                                            subscriber
                                            {version.subscriberCount === 1
                                                ? ''
                                                : 's'}{' '}
                                            pinned
                                        </p>
                                    </div>

                                    <div className="flex items-center gap-2">
                                        <StatusBadge
                                            label={versionStatusLabel(
                                                version.status,
                                            )}
                                            variant={versionStatusVariant(
                                                version.status,
                                            )}
                                        />

                                        {version.status === 'draft' ? (
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                asChild
                                            >
                                                <Link
                                                    href={PlatformProductCatalogController.edit(
                                                        version.id,
                                                    )}
                                                >
                                                    <Pencil
                                                        className="size-3.5"
                                                        aria-hidden="true"
                                                    />
                                                    Edit draft
                                                </Link>
                                            </Button>
                                        ) : null}
                                    </div>
                                </div>

                                <dl className="mt-4">
                                    <VersionDetails
                                        version={version}
                                        featureOptions={featureOptions}
                                        limitOptions={limitOptions}
                                    />
                                </dl>

                                <div className="mt-4 flex flex-wrap gap-x-6 gap-y-1 border-t border-border pt-3 text-xs text-muted-foreground">
                                    <span>
                                        Created by{' '}
                                        {version.createdBy?.name ?? 'System'} on{' '}
                                        {formatDateTime(version.createdAt)}
                                    </span>

                                    {version.publishedAt ? (
                                        <span>
                                            Published by{' '}
                                            {version.publishedBy?.name ??
                                                'Unknown'}{' '}
                                            on{' '}
                                            {formatDateTime(
                                                version.publishedAt,
                                            )}
                                        </span>
                                    ) : null}

                                    {version.supersededAt ? (
                                        <span>
                                            Superseded on{' '}
                                            {formatDateTime(
                                                version.supersededAt,
                                            )}
                                        </span>
                                    ) : null}
                                </div>
                            </section>
                        ))}
                    </div>
                )}
            </div>
        </>
    );
}
