import { Link } from '@inertiajs/react';
import { Menu } from 'lucide-react';
import { useRef } from 'react';

import { dashboard, login, register } from '@/routes';

type AuthActionsProps = {
    isAuthenticated: boolean;
    inverse?: boolean;
};

const NAV_ITEMS = [
    ['Product', '#product'],
    ['How it works', '#how-it-works'],
    ['For teams', '#for-teams'],
    ['Pricing', '#pricing'],
    ['FAQ', '#faq'],
] as const;

/** Render authentication-aware acquisition actions shared across marketing sections. */
export function AuthActions({
    isAuthenticated,
    inverse = false,
}: AuthActionsProps) {
    const secondaryClassName = inverse
        ? 'border-marketing-dark-foreground/45 text-marketing-dark-foreground hover:border-marketing-dark-foreground hover:bg-marketing-dark-foreground/10'
        : 'border-marketing-border/35 text-marketing-ink hover:border-marketing-border hover:bg-marketing-surface';

    const primaryClassName = inverse
        ? 'border-[#2d7b5f] bg-[#2d7b5f] text-marketing-accent-foreground hover:border-[#3d8c6f] hover:bg-[#3d8c6f]'
        : 'border-marketing-accent bg-marketing-accent text-marketing-accent-foreground hover:border-marketing-accent-hover hover:bg-marketing-accent-hover';

    if (isAuthenticated) {
        return (
            <Link
                href={dashboard()}
                className={`inline-flex min-h-11 items-center justify-center border px-5 text-sm font-semibold transition focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-marketing-focus ${primaryClassName}`}
            >
                Dashboard
            </Link>
        );
    }

    return (
        <div className="flex flex-wrap items-center gap-3">
            <Link
                href={login()}
                className={`inline-flex min-h-11 items-center justify-center border px-5 text-sm font-semibold transition focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-marketing-focus ${secondaryClassName}`}
            >
                Log in
            </Link>

            <Link
                href={register()}
                className={`inline-flex min-h-11 items-center justify-center border px-5 text-sm font-semibold transition focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-marketing-focus ${primaryClassName}`}
            >
                Start free
            </Link>
        </div>
    );
}

/** Render the responsive public marketing header and anchor navigation. */
export function MarketingHeader({
    isAuthenticated,
}: {
    isAuthenticated: boolean;
}) {
    const mobileMenuRef = useRef<HTMLDetailsElement>(null);
    const mobileMenuSummaryRef = useRef<HTMLElement>(null);

    /** Close the mobile menu and return focus to its trigger. */
    const closeMobileMenu = (): void => {
        if (mobileMenuRef.current !== null) {
            mobileMenuRef.current.open = false;
        }

        mobileMenuSummaryRef.current?.focus();
    };

    return (
        <header className="sticky top-0 z-50 border-b border-[#183247]/15 bg-marketing-canvas/95 backdrop-blur-sm">
            <div className="mx-auto flex min-h-18 max-w-[1440px] items-center justify-between gap-5 px-5 sm:px-8 lg:px-12">
                <a
                    href="#top"
                    className="font-serif text-2xl font-semibold tracking-[-0.03em] text-marketing-ink focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-marketing-focus sm:text-[1.7rem]"
                >
                    MiseLedger
                </a>

                <nav
                    className="hidden items-center gap-7 text-sm font-medium text-[#294154] lg:flex"
                    aria-label="Primary navigation"
                >
                    {NAV_ITEMS.map(([label, href]) => (
                        <a
                            key={href}
                            href={href}
                            className="transition hover:text-marketing-accent focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-marketing-focus"
                        >
                            {label}
                        </a>
                    ))}
                </nav>

                <div className="hidden md:block">
                    <AuthActions isAuthenticated={isAuthenticated} />
                </div>

                <details
                    ref={mobileMenuRef}
                    className="relative md:hidden"
                    onKeyDown={(event) => {
                        if (event.key === 'Escape') {
                            event.preventDefault();
                            closeMobileMenu();
                        }
                    }}
                >
                    <summary
                        ref={mobileMenuSummaryRef}
                        className="flex min-h-11 cursor-pointer list-none items-center gap-2 border border-marketing-border/30 bg-marketing-surface/60 px-3 text-sm font-semibold text-marketing-ink focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-marketing-focus [&::-webkit-details-marker]:hidden"
                    >
                        <Menu className="size-4" aria-hidden="true" />
                        Menu
                    </summary>

                    <nav
                        className="absolute right-0 mt-3 w-[min(19rem,calc(100vw-2.5rem))] border border-marketing-border/20 bg-marketing-surface-muted p-4 shadow-[0_18px_50px_rgba(16,40,58,0.16)]"
                        aria-label="Mobile navigation"
                    >
                        <div className="grid gap-1">
                            {NAV_ITEMS.map(([label, href]) => (
                                <a
                                    key={href}
                                    href={href}
                                    onClick={closeMobileMenu}
                                    className="px-3 py-3 text-sm font-medium text-[#294154] hover:bg-[#efe8db] hover:text-marketing-accent focus-visible:outline-2 focus-visible:outline-marketing-focus"
                                >
                                    {label}
                                </a>
                            ))}
                        </div>

                        <div className="mt-4 border-t border-marketing-border/15 pt-4">
                            <AuthActions isAuthenticated={isAuthenticated} />
                        </div>
                    </nav>
                </details>
            </div>
        </header>
    );
}
