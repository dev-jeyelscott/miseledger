import { Head, Link, router, usePage } from '@inertiajs/react';
import {
    BookOpen,
    ChevronRight,
    LogOut,
    MapPin,
    Newspaper,
    UserRound,
} from 'lucide-react';
import type { ComponentType } from 'react';

import { roleLabel } from '@/components/organization-switcher';
import { logout } from '@/routes';
import mobile from '@/routes/mobile';
import { edit as editProfile } from '@/routes/profile';
import { index as releaseNotesIndex } from '@/routes/release-notes';
import { index as userGuideIndex } from '@/routes/user-guide';
import type {
    MobileActiveLocation,
    MobileOrganizationSummary,
} from '@/types/mobile';

type MoreIndexProps = {
    activeLocation: MobileActiveLocation;
    organization: MobileOrganizationSummary | null;
};

type MoreMenuItem = {
    key: string;
    label: string;
    icon: ComponentType<{ className?: string }>;
    href: string;
    method?: 'get' | 'post';
};

/** The five operational-only entries from decision #39.A, in priority order. */
function buildMenuItems(): MoreMenuItem[] {
    return [
        {
            key: 'switch-location',
            label: 'Switch location',
            icon: MapPin,
            href: mobile.location.index.url({
                query: { next: mobile.more.index.url() },
            }),
        },
        {
            key: 'profile',
            label: 'Profile / account',
            icon: UserRound,
            href: editProfile().url,
        },
        {
            key: 'help',
            label: 'Help',
            icon: BookOpen,
            href: userGuideIndex().url,
        },
        {
            key: 'release-notes',
            label: 'Release notes',
            icon: Newspaper,
            href: releaseNotesIndex().url,
        },
    ];
}

/**
 * Operational-only "More" tab (Spec 9): location switcher, profile/account,
 * help, release notes, and log out. Deliberately narrow — no admin or
 * configuration screens are reachable from here.
 */
export default function MoreIndex({
    activeLocation,
    organization,
}: MoreIndexProps) {
    const { organizationContext } = usePage().props;

    const activeMembership = organizationContext.memberships.find(
        (membership) =>
            organization !== null &&
            membership.organization.id === organization.id,
    );

    const menuItems = buildMenuItems();

    return (
        <>
            <Head title="More" />

            <div className="space-y-6">
                <div>
                    <h1 className="text-lg font-semibold">More</h1>

                    {organization !== null ? (
                        <div className="mt-1 space-y-0.5 text-sm text-muted-foreground">
                            <p className="truncate font-medium text-foreground">
                                {organization.name}
                            </p>
                            <p className="truncate">
                                {activeLocation
                                    ? activeLocation.name
                                    : 'No location selected'}
                                {activeMembership ? (
                                    <span className="ml-1 inline-flex items-center rounded-full border px-2 py-0.5 text-xs capitalize">
                                        {roleLabel(activeMembership.role)}
                                    </span>
                                ) : null}
                            </p>
                        </div>
                    ) : (
                        <p className="mt-1 text-sm text-muted-foreground">
                            No organization yet.
                        </p>
                    )}
                </div>

                <ul className="divide-y rounded-md border">
                    {menuItems.map((item) => {
                        const Icon = item.icon;

                        return (
                            <li key={item.key}>
                                <Link
                                    href={item.href}
                                    className="flex min-h-[44px] items-center gap-3 px-4 py-3 hover:bg-accent"
                                >
                                    <Icon
                                        className="size-4 shrink-0 text-muted-foreground"
                                        aria-hidden="true"
                                    />
                                    <span className="flex-1">{item.label}</span>
                                    <ChevronRight
                                        className="size-4 shrink-0 text-muted-foreground"
                                        aria-hidden="true"
                                    />
                                </Link>
                            </li>
                        );
                    })}

                    <li>
                        <Link
                            href={logout()}
                            as="button"
                            onClick={() => router.flushAll()}
                            className="flex min-h-[44px] w-full items-center gap-3 px-4 py-3 text-left text-destructive hover:bg-accent"
                        >
                            <LogOut
                                className="size-4 shrink-0"
                                aria-hidden="true"
                            />
                            <span className="flex-1">Log out</span>
                        </Link>
                    </li>
                </ul>
            </div>
        </>
    );
}
