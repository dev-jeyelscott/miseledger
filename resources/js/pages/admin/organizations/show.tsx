import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, MapPin, Users } from 'lucide-react';

import PlatformOrganizationController from '@/actions/App/Http/Controllers/Platform/PlatformOrganizationController';
import PlatformUserController from '@/actions/App/Http/Controllers/Platform/PlatformUserController';
import { EmptyState } from '@/components/empty-state';
import { PageHeader } from '@/components/page-header';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';

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

type OrganizationData = {
    id: number;
    name: string;
    slug: string;
    active: boolean;
    memberCount: number;
    locationCount: number;
    commercialAccess: CommercialAccess;
    createdAt: string | null;
};

type Member = {
    id: number;
    role: string;
    roleLabel: string;
    createdAt: string | null;
    user: {
        id: number;
        name: string;
        email: string;
        emailVerifiedAt: string | null;
    };
};

type Location = {
    id: number;
    name: string;
    code: string;
    active: boolean;
    createdAt: string | null;
};

type Props = {
    organization: OrganizationData;
    members: Member[];
    membersTruncated: boolean;
    locations: Location[];
    locationsTruncated: boolean;
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

/** Present resolver-owned commercial access without reproducing authorization. */
function commercialAccessLabel(access: CommercialAccess): string {
    return access.accessMode === 'writable' ? 'Writable' : 'Read only';
}

/** Present supplemental resolver state for operator context only. */
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

/** Render one read-only platform organization with bounded member and location context. */
export default function PlatformOrganizationShow({
    organization,
    members,
    membersTruncated,
    locations,
    locationsTruncated,
}: Props) {
    return (
        <>
            <Head title={organization.name} />

            <div className="flex flex-1 flex-col gap-6 p-4 sm:p-6">
                <PageHeader
                    title={organization.name}
                    description="Read-only platform organization context. Operational and commercial access remain independent."
                    actions={
                        <Button variant="outline" asChild>
                            <Link href={PlatformOrganizationController.index()}>
                                <ArrowLeft
                                    className="size-4"
                                    aria-hidden="true"
                                />
                                Back to organizations
                            </Link>
                        </Button>
                    }
                />

                <section
                    aria-labelledby="organization-overview-heading"
                    className="rounded-xl border border-border bg-card p-4 sm:p-5"
                >
                    <h2
                        id="organization-overview-heading"
                        className="text-sm font-semibold"
                    >
                        Organization
                    </h2>

                    <dl className="mt-4 grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
                        <div>
                            <dt className="text-xs text-muted-foreground">
                                Slug
                            </dt>
                            <dd className="mt-1 text-sm font-medium break-all">
                                {organization.slug}
                            </dd>
                        </div>

                        <div>
                            <dt className="text-xs text-muted-foreground">
                                Operational status
                            </dt>
                            <dd className="mt-1">
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
                            </dd>
                        </div>

                        <div>
                            <dt className="text-xs text-muted-foreground">
                                Members
                            </dt>
                            <dd className="mt-1 text-sm font-medium tabular-nums">
                                {organization.memberCount.toLocaleString()}
                            </dd>
                        </div>

                        <div>
                            <dt className="text-xs text-muted-foreground">
                                Locations
                            </dt>
                            <dd className="mt-1 text-sm font-medium tabular-nums">
                                {organization.locationCount.toLocaleString()}
                            </dd>
                        </div>

                        <div>
                            <dt className="text-xs text-muted-foreground">
                                Created
                            </dt>
                            <dd className="mt-1 text-sm font-medium">
                                {formatDateTime(organization.createdAt)}
                            </dd>
                        </div>
                    </dl>
                </section>

                <section
                    aria-labelledby="commercial-access-heading"
                    className="rounded-xl border border-border bg-card p-4 sm:p-5"
                >
                    <h2
                        id="commercial-access-heading"
                        className="text-sm font-semibold"
                    >
                        Commercial access
                    </h2>

                    <div className="mt-4 flex flex-wrap items-start gap-6">
                        <div>
                            <p className="text-xs text-muted-foreground">
                                Access mode
                            </p>
                            <div className="mt-1">
                                <StatusBadge
                                    label={commercialAccessLabel(
                                        organization.commercialAccess,
                                    )}
                                    variant={
                                        organization.commercialAccess
                                            .accessMode === 'read_only'
                                            ? 'warning'
                                            : organization.commercialAccess
                                                    .billingWarning
                                              ? 'warning'
                                              : 'success'
                                    }
                                />
                            </div>
                        </div>

                        <div>
                            <p className="text-xs text-muted-foreground">
                                Resolver state
                            </p>
                            <p className="mt-1 text-sm font-medium capitalize">
                                {commercialContextLabel(
                                    organization.commercialAccess,
                                )}
                            </p>
                        </div>

                        <div>
                            <p className="text-xs text-muted-foreground">
                                Plan
                            </p>
                            <p className="mt-1 text-sm font-medium">
                                {organization.commercialAccess.plan ??
                                    'No resolved plan'}
                            </p>
                        </div>

                        {organization.commercialAccess.trialEndsAt ? (
                            <div>
                                <p className="text-xs text-muted-foreground">
                                    Trial ends
                                </p>
                                <p className="mt-1 text-sm font-medium">
                                    {formatDateTime(
                                        organization.commercialAccess
                                            .trialEndsAt,
                                    )}
                                </p>
                            </div>
                        ) : null}

                        {organization.commercialAccess.endsAt ? (
                            <div>
                                <p className="text-xs text-muted-foreground">
                                    Access period ends
                                </p>
                                <p className="mt-1 text-sm font-medium">
                                    {formatDateTime(
                                        organization.commercialAccess.endsAt,
                                    )}
                                </p>
                            </div>
                        ) : null}
                    </div>

                    <p className="mt-4 text-xs text-muted-foreground">
                        This section is derived by the existing commercial
                        access resolver and does not control the organization's
                        administrative active flag.
                    </p>
                </section>

                <section className="overflow-hidden rounded-xl border border-border bg-card">
                    <div className="border-b border-border px-4 py-3">
                        <h2 className="text-sm font-semibold">Members</h2>
                        <p className="mt-1 text-xs text-muted-foreground">
                            Read-only organization membership context.
                        </p>
                    </div>

                    {members.length === 0 ? (
                        <EmptyState
                            className="px-4 py-12"
                            icon={Users}
                            title="No members"
                            description="This organization has no persisted membership records."
                        />
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full min-w-[720px] text-sm">
                                <caption className="sr-only">
                                    Members of {organization.name}
                                </caption>
                                <thead className="border-b border-border bg-muted/30 text-left text-xs text-muted-foreground">
                                    <tr>
                                        <th
                                            scope="col"
                                            className="px-4 py-3 font-medium"
                                        >
                                            User
                                        </th>
                                        <th
                                            scope="col"
                                            className="px-4 py-3 font-medium"
                                        >
                                            Verification
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
                                    {members.map((member) => (
                                        <tr
                                            key={member.id}
                                            className="hover:bg-muted/30"
                                        >
                                            <td className="px-4 py-3">
                                                <Link
                                                    href={PlatformUserController.show(
                                                        member.user.id,
                                                    )}
                                                    className="rounded-sm font-medium hover:underline focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                                >
                                                    {member.user.name}
                                                </Link>
                                                <p className="mt-0.5 text-xs break-all text-muted-foreground">
                                                    {member.user.email}
                                                </p>
                                            </td>

                                            <td className="px-4 py-3">
                                                <StatusBadge
                                                    label={
                                                        member.user
                                                            .emailVerifiedAt
                                                            ? 'Verified'
                                                            : 'Unverified'
                                                    }
                                                    variant={
                                                        member.user
                                                            .emailVerifiedAt
                                                            ? 'success'
                                                            : 'warning'
                                                    }
                                                />
                                            </td>

                                            <td className="px-4 py-3">
                                                {member.roleLabel}
                                            </td>

                                            <td className="px-4 py-3 text-muted-foreground">
                                                {formatDateTime(
                                                    member.createdAt,
                                                )}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}

                    {membersTruncated ? (
                        <p className="border-t border-border px-4 py-3 text-xs text-muted-foreground">
                            Showing the first 50 ordered member records. The
                            complete member count is shown above.
                        </p>
                    ) : null}
                </section>

                <section className="overflow-hidden rounded-xl border border-border bg-card">
                    <div className="border-b border-border px-4 py-3">
                        <h2 className="text-sm font-semibold">Locations</h2>
                        <p className="mt-1 text-xs text-muted-foreground">
                            Read-only location context. No location mutations
                            are available from the platform explorer.
                        </p>
                    </div>

                    {locations.length === 0 ? (
                        <EmptyState
                            className="px-4 py-12"
                            icon={MapPin}
                            title="No locations"
                            description="This organization has no persisted locations."
                        />
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full min-w-[640px] text-sm">
                                <caption className="sr-only">
                                    Locations belonging to {organization.name}
                                </caption>
                                <thead className="border-b border-border bg-muted/30 text-left text-xs text-muted-foreground">
                                    <tr>
                                        <th
                                            scope="col"
                                            className="px-4 py-3 font-medium"
                                        >
                                            Location
                                        </th>
                                        <th
                                            scope="col"
                                            className="px-4 py-3 font-medium"
                                        >
                                            Code
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
                                            Created
                                        </th>
                                    </tr>
                                </thead>

                                <tbody className="divide-y divide-border">
                                    {locations.map((location) => (
                                        <tr key={location.id}>
                                            <td className="px-4 py-3 font-medium">
                                                {location.name}
                                            </td>
                                            <td className="px-4 py-3 font-mono text-xs">
                                                {location.code}
                                            </td>
                                            <td className="px-4 py-3">
                                                <StatusBadge
                                                    label={
                                                        location.active
                                                            ? 'Active'
                                                            : 'Inactive'
                                                    }
                                                    variant={
                                                        location.active
                                                            ? 'success'
                                                            : 'neutral'
                                                    }
                                                />
                                            </td>
                                            <td className="px-4 py-3 text-muted-foreground">
                                                {formatDateTime(
                                                    location.createdAt,
                                                )}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}

                    {locationsTruncated ? (
                        <p className="border-t border-border px-4 py-3 text-xs text-muted-foreground">
                            Showing the first 50 ordered locations. The complete
                            location count is shown above.
                        </p>
                    ) : null}
                </section>
            </div>
        </>
    );
}
