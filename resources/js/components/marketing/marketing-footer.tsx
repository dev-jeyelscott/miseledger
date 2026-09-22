import { Link } from '@inertiajs/react';

import { dashboard, login, register } from '@/routes';

const PRODUCT_LINKS = [
    ['Product', '#product'],
    ['How it works', '#how-it-works'],
    ['For teams', '#for-teams'],
    ['Pricing', '#pricing'],
    ['FAQ', '#faq'],
] as const;

/** Render the spacious three-column marketing footer. */
export function MarketingFooter({
    isAuthenticated,
}: {
    isAuthenticated: boolean;
}) {
    return (
        <footer className="border-t border-marketing-border/15 bg-marketing-canvas">
            <div className="mx-auto grid max-w-[1440px] gap-10 px-5 py-14 sm:px-8 sm:py-16 md:grid-cols-3 lg:px-12">
                <div>
                    <a
                        href="#top"
                        className="font-serif text-xl font-semibold text-marketing-ink"
                    >
                        MiseLedger
                    </a>
                    <p className="mt-3 max-w-xs text-sm leading-6 text-[#697984]">
                        Inventory and purchasing software for restaurants,
                        cafés, and food operations.
                    </p>
                </div>

                <div>
                    <p className="text-xs font-bold tracking-[0.14em] text-marketing-muted uppercase">
                        Product
                    </p>
                    <nav
                        className="mt-4 flex flex-col gap-3 text-sm text-[#536773]"
                        aria-label="Product"
                    >
                        {PRODUCT_LINKS.map(([label, href]) => (
                            <a
                                key={href}
                                href={href}
                                className="hover:text-marketing-accent"
                            >
                                {label}
                            </a>
                        ))}
                    </nav>
                </div>

                <div>
                    <p className="text-xs font-bold tracking-[0.14em] text-marketing-muted uppercase">
                        Account
                    </p>
                    <nav
                        className="mt-4 flex flex-col gap-3 text-sm text-[#536773]"
                        aria-label="Account"
                    >
                        {isAuthenticated ? (
                            <Link
                                href={dashboard()}
                                className="hover:text-marketing-accent"
                            >
                                Dashboard
                            </Link>
                        ) : (
                            <>
                                <Link
                                    href={login()}
                                    className="hover:text-marketing-accent"
                                >
                                    Log in
                                </Link>
                                <Link
                                    href={register()}
                                    className="hover:text-marketing-accent"
                                >
                                    Start free
                                </Link>
                            </>
                        )}
                    </nav>
                </div>
            </div>
        </footer>
    );
}
