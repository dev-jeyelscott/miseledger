import { Form, Head } from '@inertiajs/react';
import { Search, UserPlus, Users } from 'lucide-react';
import type { ReactNode } from 'react';
import { useMemo, useState } from 'react';
import OrganizationMemberController from '@/actions/App/Http/Controllers/OrganizationMemberController';
import InputError from '@/components/input-error';
import { PageHeader } from '@/components/page-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { NativeSelect } from '@/components/ui/native-select';
import { UsageLimitNotice } from '@/components/usage-limit-notice';
import { useGuardedDialog } from '@/hooks/use-guarded-dialog';
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

type MemberRoleFilter = 'all' | OrganizationRole;

type AddRegisteredUserDialogProps = {
    organization: OrganizationSummary;
    roles: RoleOption[];
    trigger: ReactNode;
};

/** Format the displayed member total using the correct singular or plural form. */
function formatMemberCount(count: number): string {
    return `${count.toLocaleString()} ${count === 1 ? 'member' : 'members'}`;
}

/** Build compact initials for identifying members in dense member lists. */
function getMemberInitials(name: string): string {
    const initials = name
        .trim()
        .split(/\s+/)
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part.charAt(0).toUpperCase())
        .join('');

    return initials || '?';
}

/** Add an existing registered user without leaving the member workspace. */
function AddRegisteredUserDialog({
    organization,
    roles,
    trigger,
}: AddRegisteredUserDialogProps) {
    const dialog = useGuardedDialog(
        'Discard the organization member details you entered?',
    );

    return (
        <Dialog open={dialog.open} onOpenChange={dialog.onOpenChange}>
            <DialogTrigger asChild>{trigger}</DialogTrigger>

            <DialogContent className="max-h-[calc(100vh-2rem)] overflow-y-auto sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>Add registered user</DialogTitle>
                    <DialogDescription>
                        Add an existing MiseLedger account to{' '}
                        {organization.name} and assign its organization role.
                    </DialogDescription>
                </DialogHeader>

                <div onChange={dialog.markDirty}>
                    <Form
                        {...OrganizationMemberController.store.form(
                            organization.id,
                        )}
                        errorBag="addOrganizationMember"
                        className="space-y-5"
                        resetOnSuccess
                        onSuccess={dialog.closeAfterSuccess}
                    >
                        {({ processing, errors }) => (
                            <>
                                <div className="grid gap-2">
                                    <Label htmlFor="member-email">Email</Label>

                                    <Input
                                        id="member-email"
                                        name="email"
                                        type="email"
                                        required
                                        autoFocus
                                        autoComplete="off"
                                        placeholder="user@example.com"
                                        aria-describedby="member-email-help"
                                    />

                                    <p
                                        id="member-email-help"
                                        className="text-xs text-muted-foreground"
                                    >
                                        The user must already have a registered
                                        MiseLedger account.
                                    </p>

                                    <InputError message={errors.email} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="member-role">Role</Label>

                                    <NativeSelect
                                        id="member-role"
                                        name="role"
                                        defaultValue="inventory_staff"
                                        required
                                    >
                                        {roles.map((role) => (
                                            <option
                                                key={role.value}
                                                value={role.value}
                                            >
                                                {role.label}
                                            </option>
                                        ))}
                                    </NativeSelect>

                                    <InputError message={errors.role} />
                                </div>

                                <div className="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
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
                                        <UserPlus
                                            className="size-4"
                                            aria-hidden="true"
                                        />
                                        {processing
                                            ? 'Adding member...'
                                            : 'Add member'}
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

/** Render organization memberships with lightweight local discovery controls. */
export default function OrganizationMembers({
    organization,
    members,
    roles,
}: Props) {
    const [search, setSearch] = useState('');
    const [roleFilter, setRoleFilter] = useState<MemberRoleFilter>('all');

    const roleLabels = useMemo(
        () =>
            new Map<OrganizationRole, string>(
                roles.map((role) => [role.value, role.label]),
            ),
        [roles],
    );

    const filteredMembers = useMemo(() => {
        const normalizedSearch = search.trim().toLowerCase();

        return members.filter((member) => {
            const matchesSearch =
                normalizedSearch === '' ||
                member.name.toLowerCase().includes(normalizedSearch) ||
                member.email.toLowerCase().includes(normalizedSearch);

            const matchesRole =
                roleFilter === 'all' || member.role === roleFilter;

            return matchesSearch && matchesRole;
        });
    }, [members, roleFilter, search]);

    const hasFilters = search.trim() !== '' || roleFilter !== 'all';

    const memberCount =
        filteredMembers.length === members.length
            ? formatMemberCount(members.length)
            : `${formatMemberCount(filteredMembers.length)} of ${formatMemberCount(
                  members.length,
              )}`;

    return (
        <>
            <Head title={`${organization.name} members`} />

            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Organization members"
                    description={
                        <>
                            Manage registered users who can access{' '}
                            {organization.name}.
                        </>
                    }
                    actions={
                        <div className="flex flex-col items-end gap-2">
                            <AddRegisteredUserDialog
                                organization={organization}
                                roles={roles}
                                trigger={
                                    <Button className="w-full sm:w-auto">
                                        <UserPlus
                                            className="size-4"
                                            aria-hidden="true"
                                        />
                                        Add member
                                    </Button>
                                }
                            />

                            <UsageLimitNotice
                                limitKey="seats"
                                resourceLabel="members"
                            />
                        </div>
                    }
                />

                <section
                    aria-label="Organization members"
                    className="overflow-hidden rounded-xl border border-sidebar-border/70 bg-card dark:border-sidebar-border"
                >
                    <div className="flex flex-col gap-4 border-b border-sidebar-border/70 p-4 dark:border-sidebar-border">
                        <div className="flex flex-col justify-between gap-2 sm:flex-row sm:items-center">
                            <div>
                                <h2 className="font-medium">Current members</h2>
                                <p className="mt-1 text-sm text-muted-foreground">
                                    Review who has access and the role assigned
                                    to each account.
                                </p>
                            </div>

                            <div className="flex items-center gap-2 text-sm text-muted-foreground">
                                <Users className="size-4" aria-hidden="true" />
                                <span aria-live="polite">{memberCount}</span>
                            </div>
                        </div>

                        <div className="grid gap-3 sm:grid-cols-[minmax(0,1fr)_13rem_auto] sm:items-center">
                            <div className="relative">
                                <label
                                    htmlFor="member-search"
                                    className="sr-only"
                                >
                                    Search members
                                </label>

                                <Search
                                    className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                                    aria-hidden="true"
                                />

                                <Input
                                    id="member-search"
                                    type="search"
                                    value={search}
                                    onChange={(event) =>
                                        setSearch(event.target.value)
                                    }
                                    placeholder="Search name or email..."
                                    className="pl-9"
                                />
                            </div>

                            <div>
                                <label
                                    htmlFor="member-role-filter"
                                    className="sr-only"
                                >
                                    Filter members by role
                                </label>

                                <NativeSelect
                                    id="member-role-filter"
                                    value={roleFilter}
                                    onChange={(event) =>
                                        setRoleFilter(
                                            event.target
                                                .value as MemberRoleFilter,
                                        )
                                    }
                                >
                                    <option value="all">All roles</option>

                                    {roles.map((role) => (
                                        <option
                                            key={role.value}
                                            value={role.value}
                                        >
                                            {role.label}
                                        </option>
                                    ))}
                                </NativeSelect>
                            </div>

                            <div className="flex sm:justify-end">
                                {hasFilters && (
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="sm"
                                        onClick={() => {
                                            setSearch('');
                                            setRoleFilter('all');
                                        }}
                                    >
                                        Reset
                                    </Button>
                                )}
                            </div>
                        </div>
                    </div>

                    {filteredMembers.length === 0 ? (
                        <div className="px-4 py-12 text-center">
                            <div className="mx-auto max-w-sm">
                                <p className="font-medium">
                                    {hasFilters
                                        ? 'No members match these filters.'
                                        : 'No organization members found.'}
                                </p>

                                <p className="mt-1 text-sm text-muted-foreground">
                                    {hasFilters
                                        ? 'Adjust or reset your search and role filter.'
                                        : 'Add a registered user to start building this organization team.'}
                                </p>

                                {hasFilters && (
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        className="mt-4"
                                        onClick={() => {
                                            setSearch('');
                                            setRoleFilter('all');
                                        }}
                                    >
                                        Reset filters
                                    </Button>
                                )}
                            </div>
                        </div>
                    ) : (
                        <>
                            <div
                                className="divide-y divide-sidebar-border/70 md:hidden dark:divide-sidebar-border"
                                data-testid="mobile-organization-members"
                            >
                                {filteredMembers.map((member) => (
                                    <article
                                        key={member.id}
                                        className="flex items-center gap-3 p-4"
                                    >
                                        <div
                                            className="flex size-9 shrink-0 items-center justify-center rounded-full bg-muted text-xs font-medium text-muted-foreground"
                                            aria-hidden="true"
                                        >
                                            {getMemberInitials(member.name)}
                                        </div>

                                        <div className="min-w-0 flex-1">
                                            <p className="truncate font-medium">
                                                {member.name}
                                            </p>
                                            <p className="truncate text-xs text-muted-foreground">
                                                {member.email}
                                            </p>
                                        </div>

                                        <Badge variant="secondary">
                                            {roleLabels.get(member.role) ??
                                                member.role}
                                        </Badge>
                                    </article>
                                ))}
                            </div>

                            <div className="hidden overflow-x-auto md:block">
                                <table className="w-full min-w-[680px] text-sm">
                                    <thead className="bg-muted/40 text-left text-xs text-muted-foreground">
                                        <tr className="border-b border-sidebar-border/70 dark:border-sidebar-border">
                                            <th
                                                scope="col"
                                                className="px-4 py-3 font-medium"
                                            >
                                                Member
                                            </th>
                                            <th
                                                scope="col"
                                                className="px-4 py-3 font-medium"
                                            >
                                                Email
                                            </th>
                                            <th
                                                scope="col"
                                                className="w-48 px-4 py-3 font-medium"
                                            >
                                                Role
                                            </th>
                                        </tr>
                                    </thead>

                                    <tbody>
                                        {filteredMembers.map((member) => (
                                            <tr
                                                key={member.id}
                                                className="border-b border-sidebar-border/70 transition-colors last:border-b-0 hover:bg-muted/30 dark:border-sidebar-border"
                                            >
                                                <td className="px-4 py-3">
                                                    <div className="flex items-center gap-3">
                                                        <div
                                                            className="flex size-9 shrink-0 items-center justify-center rounded-full bg-muted text-xs font-medium text-muted-foreground"
                                                            aria-hidden="true"
                                                        >
                                                            {getMemberInitials(
                                                                member.name,
                                                            )}
                                                        </div>

                                                        <span className="font-medium">
                                                            {member.name}
                                                        </span>
                                                    </div>
                                                </td>

                                                <td className="px-4 py-3 text-muted-foreground">
                                                    {member.email}
                                                </td>

                                                <td className="px-4 py-3">
                                                    <Badge variant="secondary">
                                                        {roleLabels.get(
                                                            member.role,
                                                        ) ?? member.role}
                                                    </Badge>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </>
                    )}

                    <div className="border-t border-sidebar-border/70 bg-muted/20 px-4 py-3 text-xs text-muted-foreground dark:border-sidebar-border">
                        Only registered MiseLedger users can be added. Access is
                        determined by the organization role assigned to their
                        membership.
                    </div>
                </section>
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
