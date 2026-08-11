import { Form, Head } from '@inertiajs/react';
import OrganizationMemberController from '@/actions/App/Http/Controllers/OrganizationMemberController';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { dashboard } from '@/routes';
import type { OrganizationRole, OrganizationSummary } from '@/types';

type Member = {
    id: number;
    name: string;
    email: string;
    role: OrganizationRole;
};

type RoleOption = {
    value: OrganizationRole;
    label: string;
};

type Props = {
    organization: OrganizationSummary;
    members: Member[];
    roles: RoleOption[];
};

export default function OrganizationMembers({
    organization,
    members,
    roles,
}: Props) {
    return (
        <>
            <Head title={`${organization.name} members`} />

            <div className="flex flex-1 flex-col gap-6 p-4">
                <div>
                    <h1 className="text-2xl font-semibold">
                        Organization members
                    </h1>
                    <p className="mt-1 text-sm text-muted-foreground">
                        {organization.name}
                    </p>
                </div>

                <div className="grid gap-6 lg:grid-cols-[minmax(0,1fr)_360px]">
                    <div className="rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                        <div className="border-b border-sidebar-border/70 px-5 py-4 dark:border-sidebar-border">
                            <h2 className="font-medium">Current members</h2>
                        </div>

                        <div className="divide-y divide-sidebar-border/70 dark:divide-sidebar-border">
                            {members.map((member) => (
                                <div
                                    key={member.id}
                                    className="flex items-center justify-between gap-4 px-5 py-4"
                                >
                                    <div className="min-w-0">
                                        <p className="truncate font-medium">
                                            {member.name}
                                        </p>
                                        <p className="truncate text-sm text-muted-foreground">
                                            {member.email}
                                        </p>
                                    </div>

                                    <span className="shrink-0 rounded-md border px-2 py-1 text-xs capitalize">
                                        {member.role.replaceAll('_', ' ')}
                                    </span>
                                </div>
                            ))}
                        </div>
                    </div>

                    <div className="h-fit rounded-xl border border-sidebar-border/70 p-5 dark:border-sidebar-border">
                        <div className="mb-5">
                            <h2 className="font-medium">Add registered user</h2>
                            <p className="mt-1 text-sm text-muted-foreground">
                                The user must already have a MiseLedger account.
                            </p>
                        </div>

                        <Form
                            {...OrganizationMemberController.store.form(
                                organization.id,
                            )}
                            className="space-y-5"
                            resetOnSuccess
                        >
                            {({ processing, errors }) => (
                                <>
                                    <div className="grid gap-2">
                                        <Label htmlFor="email">Email</Label>

                                        <Input
                                            id="email"
                                            name="email"
                                            type="email"
                                            required
                                            autoComplete="off"
                                            placeholder="user@example.com"
                                        />

                                        <InputError message={errors.email} />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="role">Role</Label>

                                        <select
                                            id="role"
                                            name="role"
                                            defaultValue="inventory_staff"
                                            className="h-9 rounded-md border border-input bg-background px-3 text-sm outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                        >
                                            {roles.map((role) => (
                                                <option
                                                    key={role.value}
                                                    value={role.value}
                                                >
                                                    {role.label}
                                                </option>
                                            ))}
                                        </select>

                                        <InputError message={errors.role} />
                                    </div>

                                    <Button type="submit" disabled={processing}>
                                        Add member
                                    </Button>
                                </>
                            )}
                        </Form>
                    </div>
                </div>
            </div>
        </>
    );
}

OrganizationMembers.layout = {
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: dashboard(),
        },
    ],
};
