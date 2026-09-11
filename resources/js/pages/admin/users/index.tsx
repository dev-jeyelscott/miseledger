import { Form, Head, Link } from '@inertiajs/react';
import {
    ArrowDown,
    ArrowUp,
    ArrowUpDown,
    Search,
    UserRoundSearch,
} from 'lucide-react';
import type { ReactNode } from 'react';

import PlatformUserController from '@/actions/App/Http/Controllers/Platform/PlatformUserController';
import { EmptyState } from '@/components/empty-state';
import { FilterToolbar } from '@/components/filter-toolbar';
import { PageHeader } from '@/components/page-header';
import { PaginationControls } from '@/components/pagination-controls';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';

type VerificationFilter = 'verified' | 'unverified';
type SortKey = 'name' | 'email' | 'created_at' | 'memberships';
type SortDirection = 'asc' | 'desc';

type UserListItem = {
    id: number;
    name: string;
    email: string;
    emailVerifiedAt: string | null;
    membershipCount: number;
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
    verification: VerificationFilter | null;
    role: string | null;
    sort: SortKey | null;
    direction: SortDirection;
    perPage: number;
};

type RoleOption = {
    value: string;
    label: string;
};

type Props = {
    users: UserListItem[];
    pagination: Pagination;
    filters: Filters;
    roleOptions: RoleOption[];
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

/** Render the read-only platform user explorer. */
export default function PlatformUsersIndex({
    users,
    pagination,
    filters,
    roleOptions,
}: Props) {
    const hasQueryState =
        filters.search !== '' ||
        filters.verification !== null ||
        filters.role !== null ||
        filters.sort !== null ||
        filters.perPage !== 25;

    /** Preserve current filters while changing the requested sort. */
    const sortUrl = (sort: SortKey): string => {
        const direction: SortDirection =
            filters.sort === sort && filters.direction === 'asc'
                ? 'desc'
                : 'asc';

        return PlatformUserController.index({
            query: {
                search: filters.search || undefined,
                verification: filters.verification ?? undefined,
                role: filters.role ?? undefined,
                sort,
                direction,
                per_page: filters.perPage === 25 ? undefined : filters.perPage,
            },
        }).url;
    };

    return (
        <>
            <Head title="Platform Users" />

            <div className="flex flex-1 flex-col gap-6 p-4 sm:p-6">
                <PageHeader
                    title="Users"
                    description="Read-only platform identity directory with organization membership context."
                />

                <section className="overflow-hidden rounded-xl border border-border bg-card">
                    <Form
                        action={PlatformUserController.index().url}
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

                                <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-[minmax(18rem,1fr)_minmax(10rem,13rem)_minmax(11rem,14rem)_minmax(8rem,10rem)_auto]">
                                    <div className="relative md:col-span-2 xl:col-span-1">
                                        <label
                                            htmlFor="platform-user-search"
                                            className="sr-only"
                                        >
                                            Search users by name or email
                                        </label>
                                        <Search
                                            className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                                            aria-hidden="true"
                                        />
                                        <Input
                                            id="platform-user-search"
                                            type="search"
                                            name="search"
                                            defaultValue={filters.search}
                                            placeholder="Search name or email…"
                                            className="pl-9"
                                        />
                                    </div>

                                    <div>
                                        <label
                                            htmlFor="platform-user-verification"
                                            className="sr-only"
                                        >
                                            Email verification
                                        </label>
                                        <NativeSelect
                                            id="platform-user-verification"
                                            name="verification"
                                            defaultValue={
                                                filters.verification ?? ''
                                            }
                                        >
                                            <option value="">
                                                All verification states
                                            </option>
                                            <option value="verified">
                                                Verified
                                            </option>
                                            <option value="unverified">
                                                Unverified
                                            </option>
                                        </NativeSelect>
                                    </div>

                                    <div>
                                        <label
                                            htmlFor="platform-user-role"
                                            className="sr-only"
                                        >
                                            Organization role
                                        </label>
                                        <NativeSelect
                                            id="platform-user-role"
                                            name="role"
                                            defaultValue={filters.role ?? ''}
                                        >
                                            <option value="">
                                                All organization roles
                                            </option>
                                            {roleOptions.map((role) => (
                                                <option
                                                    key={role.value}
                                                    value={role.value}
                                                >
                                                    {role.label}
                                                </option>
                                            ))}
                                        </NativeSelect>
                                    </div>

                                    <div>
                                        <label
                                            htmlFor="platform-user-per-page"
                                            className="sr-only"
                                        >
                                            Results per page
                                        </label>
                                        <NativeSelect
                                            id="platform-user-per-page"
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
                                                    href={PlatformUserController.index()}
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

                    {users.length === 0 ? (
                        <EmptyState
                            className="px-4 py-14"
                            icon={UserRoundSearch}
                            title={
                                hasQueryState
                                    ? 'No users match these filters'
                                    : 'No users found'
                            }
                            description={
                                hasQueryState
                                    ? 'Adjust or clear the current filters to broaden the results.'
                                    : 'There are no locally persisted user identities to display.'
                            }
                        />
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full min-w-[760px] text-sm">
                                <caption className="sr-only">
                                    Platform user directory
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
                                                User
                                            </SortableHeading>
                                        </th>
                                        <th
                                            scope="col"
                                            aria-sort={
                                                filters.sort === 'email'
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
                                                    filters.sort === 'email'
                                                }
                                                direction={filters.direction}
                                                href={sortUrl('email')}
                                            >
                                                Email
                                            </SortableHeading>
                                        </th>
                                        <th scope="col" className="px-4 py-3">
                                            Verification
                                        </th>
                                        <th
                                            scope="col"
                                            aria-sort={
                                                filters.sort === 'memberships'
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
                                                    filters.sort ===
                                                    'memberships'
                                                }
                                                direction={filters.direction}
                                                href={sortUrl('memberships')}
                                            >
                                                Memberships
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
                                                Joined
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
                                    {users.map((user) => (
                                        <tr
                                            key={user.id}
                                            className="hover:bg-muted/30"
                                        >
                                            <td className="px-4 py-3 font-medium">
                                                <Link
                                                    href={PlatformUserController.show(
                                                        user.id,
                                                    )}
                                                    className="rounded-sm hover:underline focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                                >
                                                    {user.name}
                                                </Link>
                                            </td>
                                            <td className="px-4 py-3">
                                                {user.email}
                                            </td>
                                            <td className="px-4 py-3">
                                                <StatusBadge
                                                    label={
                                                        user.emailVerifiedAt
                                                            ? 'Verified'
                                                            : 'Unverified'
                                                    }
                                                    variant={
                                                        user.emailVerifiedAt
                                                            ? 'success'
                                                            : 'warning'
                                                    }
                                                />
                                            </td>
                                            <td className="px-4 py-3 text-right tabular-nums">
                                                {user.membershipCount.toLocaleString()}
                                            </td>
                                            <td className="px-4 py-3 text-muted-foreground">
                                                {formatDate(user.createdAt)}
                                            </td>
                                            <td className="px-4 py-3 text-right">
                                                <Button
                                                    size="sm"
                                                    variant="outline"
                                                    asChild
                                                >
                                                    <Link
                                                        href={PlatformUserController.show(
                                                            user.id,
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
                        itemLabel="users"
                        preserveScroll
                        preserveState
                    />
                </section>
            </div>
        </>
    );
}
