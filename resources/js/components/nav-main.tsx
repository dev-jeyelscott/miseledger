import { Link } from '@inertiajs/react';
import {
    SidebarGroup,
    SidebarGroupContent,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { cn } from '@/lib/utils';
import type { NavItem } from '@/types';

export type NavGroup = {
    title: string | null;
    items: NavItem[];
};

/**
 * Render grouped application navigation with child-route-aware active states.
 */
export function NavMain({ groups = [] }: { groups: NavGroup[] }) {
    const { isCurrentOrParentUrl } = useCurrentUrl();

    return (
        <>
            {groups
                .filter((group) => group.items.length > 0)
                .map((group) => {
                    const isGroupActive = group.items.some((item) =>
                        isCurrentOrParentUrl(item.href),
                    );

                    return (
                        <SidebarGroup
                            key={group.title ?? 'primary-navigation'}
                            className="px-2 py-1"
                        >
                            {group.title !== null && (
                                <SidebarGroupLabel
                                    className={cn(
                                        isGroupActive &&
                                            'text-sidebar-foreground',
                                    )}
                                >
                                    {group.title}
                                </SidebarGroupLabel>
                            )}

                            <SidebarGroupContent>
                                <SidebarMenu>
                                    {group.items.map((item) => {
                                        const isActive = isCurrentOrParentUrl(
                                            item.href,
                                        );

                                        return (
                                            <SidebarMenuItem key={item.title}>
                                                <SidebarMenuButton
                                                    asChild
                                                    isActive={isActive}
                                                    tooltip={{
                                                        children: item.title,
                                                    }}
                                                >
                                                    <Link
                                                        href={item.href}
                                                        prefetch
                                                        aria-current={
                                                            isActive
                                                                ? 'page'
                                                                : undefined
                                                        }
                                                    >
                                                        {item.icon && (
                                                            <item.icon aria-hidden="true" />
                                                        )}
                                                        <span>
                                                            {item.title}
                                                        </span>
                                                    </Link>
                                                </SidebarMenuButton>
                                            </SidebarMenuItem>
                                        );
                                    })}
                                </SidebarMenu>
                            </SidebarGroupContent>
                        </SidebarGroup>
                    );
                })}
        </>
    );
}
