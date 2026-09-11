import { Link, router, usePage } from '@inertiajs/react';
import {
    AlertCircle,
    BookOpen,
    LogOut,
    Newspaper,
    Settings,
    ShieldCheck,
} from 'lucide-react';
import {
    DropdownMenuGroup,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
} from '@/components/ui/dropdown-menu';
import { UserInfo } from '@/components/user-info';
import { useMobileNavigation } from '@/hooks/use-mobile-navigation';
import { logout } from '@/routes';
import { dashboard as platformDashboard } from '@/routes/admin';
import { edit } from '@/routes/profile';
import { index as releaseNotesIndex } from '@/routes/release-notes';
import { index as userGuideIndex } from '@/routes/user-guide';
import type { User } from '@/types';

type Props = {
    user: User;
};

/** Render identity-level user navigation and the grant-gated platform entry point. */
export function UserMenuContent({ user }: Props) {
    const cleanup = useMobileNavigation();
    const { auth } = usePage().props;

    // Clear client navigation state before terminating the authenticated session.
    const handleLogout = () => {
        cleanup();
        router.flushAll();
    };

    return (
        <>
            <DropdownMenuLabel className="p-0 font-normal">
                <div className="flex items-center gap-2 px-1 py-1.5 text-left text-sm">
                    <UserInfo user={user} showEmail={true} />
                </div>
            </DropdownMenuLabel>
            <DropdownMenuSeparator />
            <DropdownMenuGroup>
                {auth.isPlatformAdmin ? (
                    <DropdownMenuItem asChild>
                        <Link
                            className="block w-full cursor-pointer"
                            href={platformDashboard()}
                            prefetch
                            onClick={cleanup}
                        >
                            <ShieldCheck className="mr-2" aria-hidden="true" />
                            Platform Console
                        </Link>
                    </DropdownMenuItem>
                ) : null}
                <DropdownMenuItem asChild>
                    <Link
                        className="block w-full cursor-pointer"
                        href={edit()}
                        prefetch
                        onClick={cleanup}
                    >
                        <Settings className="mr-2" />
                        Settings
                    </Link>
                </DropdownMenuItem>
                <DropdownMenuItem asChild>
                    <Link
                        className="block w-full cursor-pointer"
                        href={userGuideIndex()}
                        prefetch
                        onClick={cleanup}
                    >
                        <BookOpen className="mr-2" />
                        User Guide
                    </Link>
                </DropdownMenuItem>
                <DropdownMenuItem asChild>
                    <Link
                        className="block w-full cursor-pointer"
                        href="/problem-reports/create"
                        prefetch
                        onClick={cleanup}
                    >
                        <AlertCircle className="mr-2" />
                        Report a Problem
                    </Link>
                </DropdownMenuItem>
                <DropdownMenuItem asChild>
                    <Link
                        className="block w-full cursor-pointer"
                        href={releaseNotesIndex()}
                        prefetch
                        onClick={cleanup}
                    >
                        <Newspaper className="mr-2" />
                        Release Notes
                    </Link>
                </DropdownMenuItem>
            </DropdownMenuGroup>
            <DropdownMenuSeparator />
            <DropdownMenuItem asChild>
                <Link
                    className="block w-full cursor-pointer"
                    href={logout()}
                    as="button"
                    onClick={handleLogout}
                    data-test="logout-button"
                >
                    <LogOut className="mr-2" />
                    Log out
                </Link>
            </DropdownMenuItem>
        </>
    );
}
