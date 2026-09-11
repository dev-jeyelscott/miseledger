import { Form, Head, Link } from '@inertiajs/react';
import { CreditCard, Search } from 'lucide-react';

import PlatformBillingController from '@/actions/App/Http/Controllers/Platform/PlatformBillingController';
import PlatformOrganizationController from '@/actions/App/Http/Controllers/Platform/PlatformOrganizationController';
import { EmptyState } from '@/components/empty-state';
import { FilterToolbar } from '@/components/filter-toolbar';
import { PageHeader } from '@/components/page-header';
import { PaginationControls } from '@/components/pagination-controls';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';

type FilterOption = {
    value: string;
    label: string;
};

type SubscriptionListItem = {
    id: number;
    organization: {
        id: number;
        name: string;
    };
    provider: string;
    providerLabel: string;
    type: string | null;
    planCode: string | null;
    planLabel: string;
    interval: string | null;
    collectionMethod: string;
    collectionMethodLabel: string;
    providerStatus: string | null;
    livemode: boolean;
    currentPeriodEndsAt: string | null;
    endsAt: string | null;
    updatedAt: string | null;
};

type Pagination = {
    current_page: number;
    from: number | null;
    last_page: number;
    next_page_url: string | null;
    per_page: number;
    prev_page_url: string | null;
    to: number | null;
    total: number;
};

type Filters = {
    search: string;
    provider: string | null;
    plan: string | null;
    mode: 'live' | 'test' | null;
    perPage: number;
};

type Props = {
    subscriptions: SubscriptionListItem[];
    pagination: Pagination;
    filters: Filters;
    filterOptions: {
        providers: FilterOption[];
        plans: FilterOption[];
    };
};

/** Format an absolute server timestamp in the platform operator's local timezone. */
function formatDateTime(value: string | null): string {
    if (value === null) {
        return 'Not available';
    }

    return new Intl.DateTimeFormat(undefined, {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
        timeZoneName: 'short',
    }).format(new Date(value));
}

/** Convert a provider-owned status token into readable projection copy only. */
function providerStatusLabel(value: string | null): string {
    return value === null || value === ''
        ? 'Unavailable'
        : value.replaceAll('_', ' ');
}

/** Render the bounded read-only subscription projection directory. */
export default function PlatformBillingSubscriptions({
    subscriptions,
    pagination,
    filters,
    filterOptions,
}: Props) {
    const hasQueryState =
        filters.search !== '' ||
        filters.provider !== null ||
        filters.plan !== null ||
        filters.mode !== null ||
        filters.perPage !== 25;

    return (
        <>
            <Head title="Platform Subscriptions" />

            <div className="flex flex-1 flex-col gap-6 p-4 sm:p-6">
                <PageHeader
                    title="Subscriptions"
                    description="Read-only locally synchronized subscription projections, preserving each row's persisted provider ownership."
                    actions={
                        <>
                            <Button variant="outline" asChild>
                                <Link href={PlatformBillingController.index()}>
                                    Billing overview
                                </Link>
                            </Button>
                            <Button variant="outline" asChild>
                                <Link
                                    href={PlatformBillingController.payments()}
                                >
                                    Payments
                                </Link>
                            </Button>
                        </>
                    }
                />

                <div className="rounded-xl border border-info-border bg-info-subtle px-4 py-3 text-sm text-info-foreground">
                    <p className="font-medium">
                        Provider projection is not commercial access.
                    </p>
                    <p className="mt-1 leading-6">
                        Open the organization detail to inspect MiseLedger's
                        authoritative commercial access context. This page never
                        calls a billing provider during rendering.
                    </p>
                </div>

                <section className="overflow-hidden rounded-xl border border-border bg-card">
                    <Form
                        action={PlatformBillingController.subscriptions().url}
                        method="get"
                    >
                        {({ processing }) => (
                            <FilterToolbar className="rounded-b-none border-x-0 border-t-0 shadow-none">
                                <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-[minmax(18rem,1fr)_minmax(10rem,12rem)_minmax(11rem,14rem)_minmax(9rem,10rem)_minmax(8rem,10rem)_auto]">
                                    <div className="relative md:col-span-2 xl:col-span-1">
                                        <label
                                            htmlFor="platform-subscription-search"
                                            className="sr-only"
                                        >
                                            Search subscriptions by organization
                                            name
                                        </label>
                                        <Search
                                            className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                                            aria-hidden="true"
                                        />
                                        <Input
                                            id="platform-subscription-search"
                                            type="search"
                                            name="search"
                                            defaultValue={filters.search}
                                            placeholder="Search organization…"
                                            className="pl-9"
                                        />
                                    </div>

                                    <div>
                                        <label
                                            htmlFor="platform-subscription-provider"
                                            className="sr-only"
                                        >
                                            Billing provider
                                        </label>
                                        <NativeSelect
                                            id="platform-subscription-provider"
                                            name="provider"
                                            defaultValue={
                                                filters.provider ?? ''
                                            }
                                        >
                                            <option value="">
                                                All providers
                                            </option>
                                            {filterOptions.providers.map(
                                                (option) => (
                                                    <option
                                                        key={option.value}
                                                        value={option.value}
                                                    >
                                                        {option.label}
                                                    </option>
                                                ),
                                            )}
                                        </NativeSelect>
                                    </div>

                                    <div>
                                        <label
                                            htmlFor="platform-subscription-plan"
                                            className="sr-only"
                                        >
                                            Internal plan code
                                        </label>
                                        <NativeSelect
                                            id="platform-subscription-plan"
                                            name="plan"
                                            defaultValue={filters.plan ?? ''}
                                        >
                                            <option value="">All plans</option>
                                            {filterOptions.plans.map(
                                                (option) => (
                                                    <option
                                                        key={option.value}
                                                        value={option.value}
                                                    >
                                                        {option.label}
                                                    </option>
                                                ),
                                            )}
                                        </NativeSelect>
                                    </div>

                                    <div>
                                        <label
                                            htmlFor="platform-subscription-mode"
                                            className="sr-only"
                                        >
                                            Billing mode
                                        </label>
                                        <NativeSelect
                                            id="platform-subscription-mode"
                                            name="mode"
                                            defaultValue={filters.mode ?? ''}
                                        >
                                            <option value="">All modes</option>
                                            <option value="live">Live</option>
                                            <option value="test">Test</option>
                                        </NativeSelect>
                                    </div>

                                    <div>
                                        <label
                                            htmlFor="platform-subscription-per-page"
                                            className="sr-only"
                                        >
                                            Results per page
                                        </label>
                                        <NativeSelect
                                            id="platform-subscription-per-page"
                                            name="per_page"
                                            defaultValue={String(
                                                filters.perPage,
                                            )}
                                        >
                                            <option value="15">
                                                15 per page
                                            </option>
                                            <option value="25">
                                                25 per page
                                            </option>
                                            <option value="50">
                                                50 per page
                                            </option>
                                        </NativeSelect>
                                    </div>

                                    <div className="flex flex-wrap items-center gap-2 md:col-span-2 xl:col-span-1 xl:justify-end">
                                        <Button
                                            type="submit"
                                            disabled={processing}
                                        >
                                            {processing
                                                ? 'Applying…'
                                                : 'Apply filters'}
                                        </Button>

                                        {hasQueryState ? (
                                            <Button
                                                type="button"
                                                variant="outline"
                                                asChild
                                            >
                                                <Link
                                                    href={PlatformBillingController.subscriptions()}
                                                >
                                                    Reset
                                                </Link>
                                            </Button>
                                        ) : null}
                                    </div>
                                </div>
                            </FilterToolbar>
                        )}
                    </Form>

                    {subscriptions.length === 0 ? (
                        <EmptyState
                            className="px-4 py-14"
                            icon={CreditCard}
                            title={
                                hasQueryState
                                    ? 'No subscriptions match these filters'
                                    : 'No subscription projections found'
                            }
                            description={
                                hasQueryState
                                    ? 'Adjust or clear the current filters to broaden the results.'
                                    : 'There are no locally synchronized subscription projections to display.'
                            }
                        />
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full min-w-[1040px] text-sm">
                                <caption className="sr-only">
                                    Local billing subscription projections
                                </caption>
                                <thead className="border-b border-border bg-muted/30 text-left text-xs text-muted-foreground">
                                    <tr>
                                        <th scope="col" className="px-4 py-3">
                                            Organization
                                        </th>
                                        <th scope="col" className="px-4 py-3">
                                            Plan
                                        </th>
                                        <th scope="col" className="px-4 py-3">
                                            Provider
                                        </th>
                                        <th scope="col" className="px-4 py-3">
                                            Mode
                                        </th>
                                        <th scope="col" className="px-4 py-3">
                                            Provider projection
                                        </th>
                                        <th scope="col" className="px-4 py-3">
                                            Billing context
                                        </th>
                                        <th scope="col" className="px-4 py-3">
                                            Updated
                                        </th>
                                        <th
                                            scope="col"
                                            className="px-4 py-3 text-right"
                                        >
                                            <span className="sr-only">
                                                Actions
                                            </span>
                                        </th>
                                    </tr>
                                </thead>

                                <tbody className="divide-y divide-border">
                                    {subscriptions.map((subscription) => (
                                        <tr
                                            key={subscription.id}
                                            className="hover:bg-muted/30"
                                        >
                                            <td className="px-4 py-3">
                                                <Link
                                                    href={PlatformOrganizationController.show(
                                                        subscription
                                                            .organization.id,
                                                    )}
                                                    className="rounded-sm font-medium hover:underline focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                                >
                                                    {
                                                        subscription
                                                            .organization.name
                                                    }
                                                </Link>
                                                <p className="mt-0.5 text-xs text-muted-foreground">
                                                    {subscription.type ??
                                                        'Default subscription type'}
                                                </p>
                                            </td>

                                            <td className="px-4 py-3">
                                                <p className="font-medium">
                                                    {subscription.planLabel}
                                                </p>
                                                <p className="mt-0.5 font-mono text-xs text-muted-foreground">
                                                    {subscription.planCode ??
                                                        'No plan code'}
                                                </p>
                                            </td>

                                            <td className="px-4 py-3">
                                                {subscription.providerLabel}
                                            </td>

                                            <td className="px-4 py-3">
                                                <StatusBadge
                                                    label={
                                                        subscription.livemode
                                                            ? 'Live'
                                                            : 'Test'
                                                    }
                                                    variant={
                                                        subscription.livemode
                                                            ? 'success'
                                                            : 'neutral'
                                                    }
                                                />
                                            </td>

                                            <td className="px-4 py-3">
                                                <StatusBadge
                                                    label={providerStatusLabel(
                                                        subscription.providerStatus,
                                                    )}
                                                    variant={
                                                        subscription.providerStatus ===
                                                        null
                                                            ? 'neutral'
                                                            : 'info'
                                                    }
                                                    className="capitalize"
                                                />
                                                <p className="mt-1 text-xs text-muted-foreground">
                                                    Projection only
                                                </p>
                                            </td>

                                            <td className="px-4 py-3">
                                                <p className="capitalize">
                                                    {subscription.interval ??
                                                        'Interval unavailable'}
                                                    {' · '}
                                                    {
                                                        subscription.collectionMethodLabel
                                                    }
                                                </p>
                                                <p className="mt-1 text-xs text-muted-foreground">
                                                    Period ends{' '}
                                                    {formatDateTime(
                                                        subscription.currentPeriodEndsAt,
                                                    )}
                                                </p>
                                                {subscription.endsAt !==
                                                null ? (
                                                    <p className="mt-1 text-xs text-muted-foreground">
                                                        Ends{' '}
                                                        {formatDateTime(
                                                            subscription.endsAt,
                                                        )}
                                                    </p>
                                                ) : null}
                                            </td>

                                            <td className="px-4 py-3 text-muted-foreground">
                                                {formatDateTime(
                                                    subscription.updatedAt,
                                                )}
                                            </td>

                                            <td className="px-4 py-3 text-right">
                                                <Button
                                                    size="sm"
                                                    variant="outline"
                                                    asChild
                                                >
                                                    <Link
                                                        href={PlatformOrganizationController.show(
                                                            subscription
                                                                .organization
                                                                .id,
                                                        )}
                                                    >
                                                        View access
                                                    </Link>
                                                </Button>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}

                    <PaginationControls
                        currentPage={pagination.current_page}
                        from={pagination.from}
                        lastPage={pagination.last_page}
                        nextPageUrl={pagination.next_page_url}
                        previousPageUrl={pagination.prev_page_url}
                        to={pagination.to}
                        total={pagination.total}
                        itemLabel="subscriptions"
                        preserveScroll
                        preserveState
                    />
                </section>
            </div>
        </>
    );
}
