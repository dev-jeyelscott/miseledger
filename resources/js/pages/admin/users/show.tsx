import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, Building2 } from 'lucide-react';

import PlatformOrganizationController from '@/actions/App/Http/Controllers/Platform/PlatformOrganizationController';
import PlatformUserController from '@/actions/App/Http/Controllers/Platform/PlatformUserController';
import { EmptyState } from '@/components/empty-state';
import { PageHeader } from '@/components/page-header';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';

type UserData = {
    id: number;
    name: string;
    email: string;
    emailVerifiedAt: string | null;
    createdAt: string | null;
    membershipCount: number;
};

type Membership = {
    id: number;
    role: string;
    roleLabel: string;
    createdAt: string | null;
    organization: {
        id: number;
        name: string;
        active: boolean;
    };
};

type Props = {
    user: UserData;
    memberships: Membership[];
    membershipsTruncated: boolean;
};

/** Format a timestamp in the platform operator's local timezone. */
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

/** Render one read-only platform user identity and organization memberships. */
export default function PlatformUserShow({
    user,
    memberships,
    membershipsTruncated,
}: Props) {
    return (
        <>
            <Head title={user.name} />

            <div className="flex flex-1 flex-col gap-6 p-4 sm:p-6">
                <PageHeader
                    title={user.name}
                    description="Read-only platform identity and organization membership context."
                    actions={
                        <Button variant="outline" asChild>
                            <Link href={PlatformUserController.index()}>
                                <ArrowLeft
                                    className="size-4"
                                    aria-hidden="true"
                                />
                                Back to users
                            </Link>
                        </Button>
                    }
                />

                <section
                    aria-labelledby="user-identity-heading"
                    className="rounded-xl border border-border bg-card p-4 sm:p-5"
                >
                    <h2
                        id="user-identity-heading"
                        className="text-sm font-semibold"
                    >
                        Identity
                    </h2>

                    <dl className="mt-4 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                        <div>
                            <dt className="text-xs text-muted-foreground">
                                Email
                            </dt>
                            <dd className="mt-1 text-sm font-medium break-all">
                                {user.email}
                            </dd>
                        </div>

                        <div>
                            <dt className="text-xs text-muted-foreground">
                                Verification
                            </dt>
                            <dd className="mt-1">
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
                            </dd>
                        </div>

                        <div>
                            <dt className="text-xs text-muted-foreground">
                                Organization memberships
                            </dt>
                            <dd className="mt-1 text-sm font-medium tabular-nums">
                                {user.membershipCount.toLocaleString()}
                            </dd>
                        </div>

                        <div>
                            <dt className="text-xs text-muted-foreground">
                                Created
                            </dt>
                            <dd className="mt-1 text-sm font-medium">
                                {formatDateTime(user.createdAt)}
                            </dd>
                        </div>
                    </dl>
                </section>

                <section className="overflow-hidden rounded-xl border border-border bg-card">
                    <div className="border-b border-border px-4 py-3">
                        <h2 className="text-sm font-semibold">
                            Organization memberships
                        </h2>
                        <p className="mt-1 text-xs text-muted-foreground">
                            Read-only membership records linked to this
                            identity.
                        </p>
                    </div>

                    {memberships.length === 0 ? (
                        <EmptyState
                            className="px-4 py-12"
                            icon={Building2}
                            title="No organization memberships"
                            description="This user is not currently represented by any organization membership."
                        />
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full min-w-[640px] text-sm">
                                <caption className="sr-only">
                                    Organization memberships for {user.name}
                                </caption>
                                <thead className="border-b border-border bg-muted/30 text-left text-xs text-muted-foreground">
                                    <tr>
                                        <th
                                            scope="col"
                                            className="px-4 py-3 font-medium"
                                        >
                                            Organization
                                        </th>
                                        <th
                                            scope="col"
                                            className="px-4 py-3 font-medium"
                                        >
                                            Operational status
                                        </th>
                                        <th
                                            scope="col"
                                            className="px-4 py-3 font-medium"
                                        >
                                            Role
                                        </th>
                                        <th
                                            scope="col"
                                            className="px-4 py-3 font-medium"
                                        >
                                            Membership created
                                        </th>
                                    </tr>
                                </thead>

                                <tbody className="divide-y divide-border">
                                    {memberships.map((membership) => (
                                        <tr
                                            key={membership.id}
                                            className="hover:bg-muted/30"
                                        >
                                            <td className="px-4 py-3 font-medium">
                                                <Link
                                                    href={PlatformOrganizationController.show(
                                                        membership.organization
                                                            .id,
                                                    )}
                                                    className="rounded-sm hover:underline focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                                >
                                                    {
                                                        membership.organization
                                                            .name
                                                    }
                                                </Link>
                                            </td>
                                            <td className="px-4 py-3">
                                                <StatusBadge
                                                    label={
                                                        membership.organization
                                                            .active
                                                            ? 'Active'
                                                            : 'Inactive'
                                                    }
                                                    variant={
                                                        membership.organization
                                                            .active
                                                            ? 'success'
                                                            : 'neutral'
                                                    }
                                                />
                                            </td>
                                            <td className="px-4 py-3">
                                                {membership.roleLabel}
                                            </td>
                                            <td className="px-4 py-3 text-muted-foreground">
                                                {formatDateTime(
                                                    membership.createdAt,
                                                )}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}

                    {membershipsTruncated ? (
                        <p className="border-t border-border px-4 py-3 text-xs text-muted-foreground">
                            Showing the 50 newest memberships. The complete
                            membership count is shown above.
                        </p>
                    ) : null}
                </section>
            </div>
        </>
    );
}
