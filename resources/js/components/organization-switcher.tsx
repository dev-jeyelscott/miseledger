import { Form, router } from '@inertiajs/react';
import { Building2, Check, ChevronsUpDown } from 'lucide-react';
import OrganizationController from '@/actions/App/Http/Controllers/OrganizationController';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    useSidebar,
} from '@/components/ui/sidebar';
import { useIsMobile } from '@/hooks/use-mobile';
import type { OrganizationContext, OrganizationMembership } from '@/types';

type OrganizationSwitcherProps = {
    organizationContext: OrganizationContext;
};

/**
 * Convert the persisted organization role into a compact human-readable label.
 */
function roleLabel(role: OrganizationMembership['role']): string {
    return role.replaceAll('_', ' ');
}

/**
 * Render the active tenant and switch only between authorized memberships.
 */
export function OrganizationSwitcher({
    organizationContext,
}: OrganizationSwitcherProps) {
    const { state } = useSidebar();
    const isMobile = useIsMobile();

    const activeOrganization = organizationContext.active;

    if (activeOrganization === null) {
        return null;
    }

    const activeMembership = organizationContext.memberships.find(
        (membership) => membership.organization.id === activeOrganization.id,
    );

    const activeRole = activeMembership
        ? roleLabel(activeMembership.role)
        : 'organization';

    const identity = (
        <>
            <div className="flex aspect-square size-8 items-center justify-center rounded-lg bg-sidebar-accent text-sidebar-accent-foreground">
                <Building2 className="size-4" aria-hidden="true" />
            </div>

            <div className="grid min-w-0 flex-1 text-left text-sm leading-tight">
                <span className="truncate font-medium">
                    {activeOrganization.name}
                </span>
                <span className="truncate text-xs text-sidebar-foreground/60 capitalize">
                    {activeRole}
                </span>
            </div>
        </>
    );

    if (organizationContext.memberships.length <= 1) {
        return (
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton
                        type="button"
                        size="lg"
                        aria-label={`Current organization: ${activeOrganization.name}`}
                        title={`Current organization: ${activeOrganization.name}`}
                        className="cursor-default hover:bg-transparent"
                    >
                        {identity}
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        );
    }

    return (
        <SidebarMenu>
            <SidebarMenuItem>
                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <SidebarMenuButton
                            type="button"
                            size="lg"
                            aria-label={`Switch organization. Current organization: ${activeOrganization.name}`}
                            title={`Current organization: ${activeOrganization.name}`}
                            className="data-[state=open]:bg-sidebar-accent data-[state=open]:text-sidebar-accent-foreground"
                        >
                            {identity}
                            <ChevronsUpDown
                                className="ml-auto size-4"
                                aria-hidden="true"
                            />
                        </SidebarMenuButton>
                    </DropdownMenuTrigger>

                    <DropdownMenuContent
                        className="max-h-80 w-(--radix-dropdown-menu-trigger-width) min-w-64 overflow-y-auto rounded-lg"
                        align="start"
                        side={
                            isMobile
                                ? 'bottom'
                                : state === 'collapsed'
                                  ? 'right'
                                  : 'bottom'
                        }
                        sideOffset={8}
                    >
                        <DropdownMenuLabel className="text-xs text-muted-foreground">
                            Organizations
                        </DropdownMenuLabel>

                        <DropdownMenuSeparator />

                        {organizationContext.memberships.map((membership) => {
                            const organization = membership.organization;
                            const isActive =
                                organization.id === activeOrganization.id;

                            return (
                                <Form
                                    key={organization.id}
                                    {...OrganizationController.activate.form(
                                        organization.id,
                                    )}
                                    onSuccess={() => {
                                        router.flushAll();
                                    }}
                                >
                                    {({ processing }) => (
                                        <DropdownMenuItem
                                            asChild
                                            disabled={processing || isActive}
                                        >
                                            <button
                                                type="submit"
                                                disabled={
                                                    processing || isActive
                                                }
                                                aria-current={
                                                    isActive
                                                        ? 'true'
                                                        : undefined
                                                }
                                                className="w-full"
                                            >
                                                <div className="flex size-8 shrink-0 items-center justify-center rounded-md bg-muted">
                                                    <Building2
                                                        className="size-4"
                                                        aria-hidden="true"
                                                    />
                                                </div>

                                                <span className="min-w-0 flex-1 text-left">
                                                    <span className="block truncate font-medium">
                                                        {organization.name}
                                                    </span>
                                                    <span className="block truncate text-xs text-muted-foreground capitalize">
                                                        {roleLabel(
                                                            membership.role,
                                                        )}
                                                    </span>
                                                </span>

                                                {isActive && (
                                                    <Check
                                                        className="ml-auto size-4"
                                                        aria-hidden="true"
                                                    />
                                                )}
                                            </button>
                                        </DropdownMenuItem>
                                    )}
                                </Form>
                            );
                        })}
                    </DropdownMenuContent>
                </DropdownMenu>
            </SidebarMenuItem>
        </SidebarMenu>
    );
}
