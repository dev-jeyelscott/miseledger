import { Form, Head, Link, router, usePage } from '@inertiajs/react';
import OrganizationController from '@/actions/App/Http/Controllers/OrganizationController';
import OrganizationMemberController from '@/actions/App/Http/Controllers/OrganizationMemberController';
import OrganizationLocationController from '@/actions/App/Http/Controllers/OrganizationLocationController';
import { Button } from '@/components/ui/button';
import { dashboard } from '@/routes';
import type { OrganizationContext } from '@/types';

type PageProps = {
    organizationContext: OrganizationContext;
};

export default function Dashboard() {
    const { organizationContext } = usePage<PageProps>().props;

    const activeMembership = organizationContext.memberships.find(
        (membership) =>
            membership.organization.id === organizationContext.active?.id,
    );

    if (organizationContext.active === null) {
        return (
            <>
                <Head title="Dashboard" />

                <div className="flex flex-1 items-start justify-center p-4">
                    <div className="w-full max-w-xl rounded-xl border border-sidebar-border/70 p-6 dark:border-sidebar-border">
                        <h1 className="text-2xl font-semibold">
                            Create your organization
                        </h1>

                        <p className="mt-2 text-sm text-muted-foreground">
                            An organization is required before
                            organization-scoped inventory data can be created.
                        </p>

                        <Button className="mt-6" asChild>
                            <Link href={OrganizationController.create()}>
                                Create organization
                            </Link>
                        </Button>
                    </div>
                </div>
            </>
        );
    }

    return (
        <>
            <Head title="Dashboard" />

            <div className="flex flex-1 flex-col gap-4 p-4">
                <div className="flex flex-col justify-between gap-4 rounded-xl border border-sidebar-border/70 p-5 sm:flex-row sm:items-center dark:border-sidebar-border">
                    <div>
                        <p className="text-sm text-muted-foreground">
                            Active organization
                        </p>
                        <h1 className="mt-1 text-2xl font-semibold">
                            {organizationContext.active.name}
                        </h1>
                        <p className="mt-1 text-sm text-muted-foreground capitalize">
                            Role:{' '}
                            {activeMembership?.role.replaceAll('_', ' ') ??
                                'Unknown'}
                        </p>
                    </div>

                    <div className="flex flex-wrap gap-2">
                        {activeMembership?.permissions.includes(
                            'locations.manage',
                        ) && (
                            <Button variant="outline" asChild>
                                <Link
                                    href={OrganizationLocationController.index(
                                        organizationContext.active.id,
                                    )}
                                >
                                    Manage locations
                                </Link>
                            </Button>
                        )}

                        {activeMembership?.permissions.includes(
                            'users.manage',
                        ) && (
                            <Button variant="outline" asChild>
                                <Link
                                    href={OrganizationMemberController.index(
                                        organizationContext.active.id,
                                    )}
                                >
                                    Manage members
                                </Link>
                            </Button>
                        )}

                        <Button variant="outline" asChild>
                            <Link href={OrganizationController.create()}>
                                New organization
                            </Link>
                        </Button>
                    </div>
                </div>

                <div className="rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                    <div className="border-b border-sidebar-border/70 px-5 py-4 dark:border-sidebar-border">
                        <h2 className="font-medium">Your organizations</h2>
                    </div>

                    <div className="divide-y divide-sidebar-border/70 dark:divide-sidebar-border">
                        {organizationContext.memberships.map((membership) => {
                            const isActive =
                                membership.organization.id ===
                                organizationContext.active?.id;

                            return (
                                <div
                                    key={membership.organization.id}
                                    className="flex items-center justify-between gap-4 px-5 py-4"
                                >
                                    <div>
                                        <p className="font-medium">
                                            {membership.organization.name}
                                        </p>
                                        <p className="text-sm text-muted-foreground capitalize">
                                            {membership.role.replaceAll(
                                                '_',
                                                ' ',
                                            )}
                                        </p>
                                    </div>

                                    <Form
                                        {...OrganizationController.activate.form(
                                            membership.organization.id,
                                        )}
                                        onSuccess={() => {
                                            router.flushAll();
                                        }}
                                    >
                                        {({ processing }) => (
                                            <Button
                                                type="submit"
                                                size="sm"
                                                variant={
                                                    isActive
                                                        ? 'secondary'
                                                        : 'outline'
                                                }
                                                disabled={
                                                    processing || isActive
                                                }
                                            >
                                                {isActive ? 'Active' : 'Switch'}
                                            </Button>
                                        )}
                                    </Form>
                                </div>
                            );
                        })}
                    </div>
                </div>
            </div>
        </>
    );
}

Dashboard.layout = {
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: dashboard(),
        },
    ],
};
