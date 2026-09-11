import { Form, Head, Link } from '@inertiajs/react';
import {
    ArrowDown,
    ArrowUp,
    ArrowUpDown,
    Building2,
    Search,
} from 'lucide-react';
import type { ReactNode } from 'react';

import PlatformOrganizationController from '@/actions/App/Http/Controllers/Platform/PlatformOrganizationController';
import { EmptyState } from '@/components/empty-state';
import { FilterToolbar } from '@/components/filter-toolbar';
import { PageHeader } from '@/components/page-header';
import { PaginationControls } from '@/components/pagination-controls';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';

type OrganizationStatus = 'active' | 'inactive';
type SortKey = 'name' | 'status' | 'created_at' | 'members' | 'locations';
type SortDirection = 'asc' | 'desc';

type CommercialAccess = {
    accessMode: 'writable' | 'read_only';
    subscriptionStatus: string | null;
    plan: string | null;
    onTrial: boolean;
    onGracePeriod: boolean;
    billingWarning: boolean;
    trialEndsAt: string | null;
    endsAt: string | null;
};

type OrganizationListItem = {
    id: number;
    name: string;
    slug: string;
    active: boolean;
    memberCount: number;
    locationCount: number;
    commercialAccess: CommercialAccess;
    createdAt: string | null;
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
    status: OrganizationStatus | null;
    sort: SortKey | null;
    direction: SortDirection;
    perPage: number;
};

type Props = {
    organizations: OrganizationListItem[];
    pagination: Pagination;
    filters: Filters;
};

type SortableHeadingProps = {
    active: boolean;
    children: ReactNode;
    direction: SortDirection;
    href: string;
};

/** Format a server timestamp in the platform operator's local timezone. */
function formatDate(value: string | null): string {
    if (value === null) {
        return 'Unavailable';
    }

    return new Intl.DateTimeFormat(undefined, {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    }).format(new Date(value));
}

/** Present resolver-owned commercial access without changing its semantics. */
function commercialAccessLabel(access: CommercialAccess): string {
    return access.accessMode === 'writable' ? 'Writable' : 'Read only';
}

/** Present supplemental resolver state without deriving authorization locally. */
function commercialContextLabel(access: CommercialAccess): string {
    if (access.onTrial) {
        return 'Trial';
    }

    if (access.onGracePeriod) {
        return 'Grace period';
    }

    if (access.subscriptionStatus !== null) {
        return access.subscriptionStatus.replaceAll('_', ' ');
    }

    return 'No subscription status';
}

/** Render an accessible sortable table heading link. */
function SortableHeading({
    active,
    children,
    direction,
    href,
}: SortableHeadingProps) {
    const Icon = active
        ? direction === 'asc'
            ? ArrowUp
            : ArrowDown
        : ArrowUpDown;

    return (
        <Link
            href={href}
            preserveScroll
            preserveState
            className="inline-flex items-center gap-1 rounded-sm font-medium hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
        >
            {children}
            <Icon className="size-3.5" aria-hidden="true" />
        </Link>
    );
}

/** Render the read-only platform organization explorer. */
export default function PlatformOrganizationsIndex({
    organizations,
    pagination,
    filters,
}: Props) {
    const hasQueryState =
        filters.search !== '' ||
        filters.status !== null ||
        filters.sort !== null ||
        filters.perPage !== 25;

    /** Preserve active filters while changing the requested sort. */
    const sortUrl = (sort: SortKey): string => {
        const direction: SortDirection =
            filters.sort === sort && filters.direction === 'asc'
                ? 'desc'
                : 'asc';

        return PlatformOrganizationController.index({
            query: {
                search: filters.search || undefined,
                status: filters.status ?? undefined,
                sort,
                direction,
                per_page: filters.perPage === 25 ? undefined : filters.perPage,
            },
        }).url;
    };

    return (
        <>
            <Head title="Platform Organizations" />

            <div className="flex flex-1 flex-col gap-6 p-4 sm:p-6">
                <PageHeader
                    title="Organizations"
                    description="Read-only platform organization directory with operational and commercial state kept separate."
                />

                <section className="overflow-hidden rounded-xl border border-border bg-card">
                    <Form
                        action={PlatformOrganizationController.index().url}
                        method="get"
                    >
                        {({ processing }) => (
                            <FilterToolbar className="rounded-b-none border-x-0 border-t-0 shadow-none">
                                {filters.sort !== null ? (
                                    <>
                                        <input
                                            type="hidden"
                                            name="sort"
                                            value={filters.sort}
                                        />
                                        <input
                                            type="hidden"
                                            name="direction"
                                            value={filters.direction}
                                        />
                                    </>
                                ) : null}

                                <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-[minmax(18rem,1fr)_minmax(11rem,14rem)_minmax(8rem,10rem)_auto]">
                                    <div className="relative md:col-span-2 xl:col-span-1">
                                        <label
                                            htmlFor="platform-organization-search"
                                            className="sr-only"
                                        >
                                            Search organizations by name or slug
                                        </label>
                                        <Search
                                            className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                                            aria-hidden="true"
                                        />
                                        <Input
                                            id="platform-organization-search"
                                            type="search"
                                            name="search"
                                            defaultValue={filters.search}
                                            placeholder="Search name or slug…"
                                            className="pl-9"
                                        />
                                    </div>

                                    <div>
                                        <label
                                            htmlFor="platform-organization-status"
                                            className="sr-only"
                                        >
                                            Operational status
                                        </label>
                                        <NativeSelect
                                            id="platform-organization-status"
                                            name="status"
                                            defaultValue={filters.status ?? ''}
                                        >
                                            <option value="">
                                                All operational statuses
                                            </option>
                                            <option value="active">
                                                Active
                                            </option>
                                            <option value="inactive">
                                                Inactive
                                            </option>
                                        </NativeSelect>
                                    </div>

                                    <div>
                                        <label
                                            htmlFor="platform-organization-per-page"
                                            className="sr-only"
                                        >
                                            Results per page
                                        </label>
                                        <NativeSelect
                                            id="platform-organization-per-page"
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
                                                    href={PlatformOrganizationController.index()}
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

                    {organizations.length === 0 ? (
                        <EmptyState
                            className="px-4 py-14"
                            icon={Building2}
                            title={
                                hasQueryState
                                    ? 'No organizations match these filters'
                                    : 'No organizations found'
                            }
                            description={
                                hasQueryState
                                    ? 'Adjust or clear the current filters to broaden the results.'
                                    : 'There are no locally persisted organizations to display.'
                            }
                        />
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full min-w-[920px] text-sm">
                                <caption className="sr-only">
                                    Platform organization directory
                                </caption>
                                <thead className="border-b border-border bg-muted/30 text-left text-xs text-muted-foreground">
                                    <tr>
                                        <th
                                            scope="col"
                                            aria-sort={
                                                filters.sort === 'name'
                                                    ? filters.direction ===
                                                      'asc'
                                                        ? 'ascending'
                                                        : 'descending'
                                                    : 'none'
                                            }
                                            className="px-4 py-3"
                                        >
                                            <SortableHeading
                                                active={filters.sort === 'name'}
                                                direction={filters.direction}
                                                href={sortUrl('name')}
                                            >
                                                Organization
                                            </SortableHeading>
                                        </th>
                                        <th
                                            scope="col"
                                            aria-sort={
                                                filters.sort === 'status'
                                                    ? filters.direction ===
                                                      'asc'
                                                        ? 'ascending'
                                                        : 'descending'
                                                    : 'none'
                                            }
                                            className="px-4 py-3"
                                        >
                                            <SortableHeading
                                                active={
                                                    filters.sort === 'status'
                                                }
                                                direction={filters.direction}
                                                href={sortUrl('status')}
                                            >
                                                Operational
                                            </SortableHeading>
                                        </th>
                                        <th scope="col" className="px-4 py-3">
                                            Commercial
                                        </th>
                                        <th
                                            scope="col"
                                            aria-sort={
                                                filters.sort === 'members'
                                                    ? filters.direction ===
                                                      'asc'
                                                        ? 'ascending'
                                                        : 'descending'
                                                    : 'none'
                                            }
                                            className="px-4 py-3 text-right"
                                        >
                                            <SortableHeading
                                                active={
                                                    filters.sort === 'members'
                                                }
                                                direction={filters.direction}
                                                href={sortUrl('members')}
                                            >
                                                Members
                                            </SortableHeading>
                                        </th>
                                        <th
                                            scope="col"
                                            aria-sort={
                                                filters.sort === 'locations'
                                                    ? filters.direction ===
                                                      'asc'
                                                        ? 'ascending'
                                                        : 'descending'
                                                    : 'none'
                                            }
                                            className="px-4 py-3 text-right"
                                        >
                                            <SortableHeading
                                                active={
                                                    filters.sort === 'locations'
                                                }
                                                direction={filters.direction}
                                                href={sortUrl('locations')}
                                            >
                                                Locations
                                            </SortableHeading>
                                        </th>
                                        <th
                                            scope="col"
                                            aria-sort={
                                                filters.sort === 'created_at'
                                                    ? filters.direction ===
                                                      'asc'
                                                        ? 'ascending'
                                                        : 'descending'
                                                    : 'none'
                                            }
                                            className="px-4 py-3"
                                        >
                                            <SortableHeading
                                                active={
                                                    filters.sort ===
                                                    'created_at'
                                                }
                                                direction={filters.direction}
                                                href={sortUrl('created_at')}
                                            >
                                                Created
                                            </SortableHeading>
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
                                    {organizations.map((organization) => (
                                        <tr
                                            key={organization.id}
                                            className="hover:bg-muted/30"
                                        >
                                            <td className="px-4 py-3">
                                                <Link
                                                    href={PlatformOrganizationController.show(
                                                        organization.id,
                                                    )}
                                                    className="rounded-sm font-medium hover:underline focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                                >
                                                    {organization.name}
                                                </Link>
                                                <p className="mt-0.5 text-xs text-muted-foreground">
                                                    {organization.slug}
                                                </p>
                                            </td>

                                            <td className="px-4 py-3">
                                                <StatusBadge
                                                    label={
                                                        organization.active
                                                            ? 'Active'
                                                            : 'Inactive'
                                                    }
                                                    variant={
                                                        organization.active
                                                            ? 'success'
                                                            : 'neutral'
                                                    }
                                                />
                                            </td>

                                            <td className="px-4 py-3">
                                                <StatusBadge
                                                    label={commercialAccessLabel(
                                                        organization.commercialAccess,
                                                    )}
                                                    variant={
                                                        organization
                                                            .commercialAccess
                                                            .accessMode ===
                                                        'read_only'
                                                            ? 'warning'
                                                            : organization
                                                                    .commercialAccess
                                                                    .billingWarning
                                                              ? 'warning'
                                                              : 'success'
                                                    }
                                                />
                                                <p className="mt-1 text-xs text-muted-foreground capitalize">
                                                    {commercialContextLabel(
                                                        organization.commercialAccess,
                                                    )}
                                                </p>
                                            </td>

                                            <td className="px-4 py-3 text-right tabular-nums">
                                                {organization.memberCount.toLocaleString()}
                                            </td>

                                            <td className="px-4 py-3 text-right tabular-nums">
                                                {organization.locationCount.toLocaleString()}
                                            </td>

                                            <td className="px-4 py-3 text-muted-foreground">
                                                {formatDate(
                                                    organization.createdAt,
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
                                                            organization.id,
                                                        )}
                                                    >
                                                        View
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
                        itemLabel="organizations"
                        preserveScroll
                        preserveState
                    />
                </section>
            </div>
        </>
    );
}
