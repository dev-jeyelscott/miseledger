import { Head, Link, usePage } from '@inertiajs/react';
import {
    AlertTriangle,
    ArrowRight,
    BarChart3,
    Boxes,
    ChefHat,
    ClipboardCheck,
    ClipboardList,
    MapPin,
    Menu,
    PackageCheck,
    ReceiptText,
    Scale,
    Store,
    Trash2,
    Truck,
    Users,
    Warehouse,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';

import { dashboard, login, register } from '@/routes';

type AuthActionsProps = {
    isAuthenticated: boolean;
    inverse?: boolean;
};

type EditorialBenefitProps = {
    icon: LucideIcon;
    title: string;
    description: string;
};

type JourneyStepProps = {
    number: number;
    icon: LucideIcon;
    title: string;
    children: ReactNode;
    showConnector?: boolean;
};

type FeatureStoryProps = {
    eyebrow: string;
    title: string;
    bullets: string[];
    children: ReactNode;
};

/** Render authentication-aware actions without duplicating route decisions. */
function AuthActions({ isAuthenticated, inverse = false }: AuthActionsProps) {
    const secondaryClassName = inverse
        ? 'border-white/45 text-white hover:border-white hover:bg-white/10'
        : 'border-[#173247]/35 text-[#10283a] hover:border-[#173247] hover:bg-white';

    const primaryClassName = inverse
        ? 'border-[#2d7b5f] bg-[#2d7b5f] text-white hover:border-[#3d8c6f] hover:bg-[#3d8c6f]'
        : 'border-[#0f5a43] bg-[#0f5a43] text-white hover:border-[#0b4936] hover:bg-[#0b4936]';

    if (isAuthenticated) {
        return (
            <Link
                href={dashboard()}
                className={`inline-flex min-h-11 items-center justify-center border px-5 text-sm font-semibold transition focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#b87949] ${primaryClassName}`}
            >
                Dashboard
            </Link>
        );
    }

    return (
        <div className="flex flex-wrap items-center gap-3">
            <Link
                href={login()}
                className={`inline-flex min-h-11 items-center justify-center border px-5 text-sm font-semibold transition focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#b87949] ${secondaryClassName}`}
            >
                Log in
            </Link>

            <Link
                href={register()}
                className={`inline-flex min-h-11 items-center justify-center border px-5 text-sm font-semibold transition focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#b87949] ${primaryClassName}`}
            >
                Register
            </Link>
        </div>
    );
}

/** Render the hero-specific primary conversion actions. */
function HeroActions({ isAuthenticated }: { isAuthenticated: boolean }) {
    if (isAuthenticated) {
        return (
            <Link
                href={dashboard()}
                className="inline-flex min-h-12 items-center justify-center gap-2 border border-[#0f5a43] bg-[#0f5a43] px-6 text-sm font-semibold text-white transition hover:bg-[#0b4936] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#b87949]"
            >
                Open dashboard
                <ArrowRight className="size-4" aria-hidden="true" />
            </Link>
        );
    }

    return (
        <div className="flex flex-wrap gap-3">
            <Link
                href={register()}
                className="inline-flex min-h-12 items-center justify-center gap-2 border border-[#0f5a43] bg-[#0f5a43] px-6 text-sm font-semibold text-white transition hover:bg-[#0b4936] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#b87949]"
            >
                Create an account
                <ArrowRight className="size-4" aria-hidden="true" />
            </Link>

            <Link
                href={login()}
                className="inline-flex min-h-12 items-center justify-center border border-[#173247]/40 bg-transparent px-6 text-sm font-semibold text-[#10283a] transition hover:border-[#173247] hover:bg-white focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#b87949]"
            >
                Log in
            </Link>
        </div>
    );
}

/** Render the responsive public marketing header and anchor navigation. */
function MarketingHeader({ isAuthenticated }: { isAuthenticated: boolean }) {
    const navItems = [
        ['Why MiseLedger', '#why-miseledger'],
        ['How It Works', '#how-it-works'],
        ['What You Can See', '#what-you-can-see'],
        ['For Your Team', '#for-your-team'],
    ] as const;

    return (
        <header className="sticky top-0 z-50 border-b border-[#183247]/15 bg-[#f7f3ea]/95 backdrop-blur-sm">
            <div className="mx-auto flex min-h-18 max-w-[1440px] items-center justify-between gap-5 px-5 sm:px-8 lg:px-12">
                <a
                    href="#top"
                    className="font-serif text-2xl font-semibold tracking-[-0.03em] text-[#10283a] focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-[#b87949] sm:text-[1.7rem]"
                >
                    MiseLedger
                </a>

                <nav
                    className="hidden items-center gap-7 text-sm font-medium text-[#294154] lg:flex"
                    aria-label="Primary navigation"
                >
                    {navItems.map(([label, href]) => (
                        <a
                            key={href}
                            href={href}
                            className="transition hover:text-[#0f5a43] focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-[#b87949]"
                        >
                            {label}
                        </a>
                    ))}
                </nav>

                <div className="hidden md:block">
                    <AuthActions isAuthenticated={isAuthenticated} />
                </div>

                <details className="relative md:hidden">
                    <summary className="flex min-h-11 cursor-pointer list-none items-center gap-2 border border-[#173247]/30 bg-white/60 px-3 text-sm font-semibold text-[#10283a] [&::-webkit-details-marker]:hidden">
                        <Menu className="size-4" aria-hidden="true" />
                        Menu
                    </summary>

                    <nav
                        className="absolute right-0 mt-3 w-[min(19rem,calc(100vw-2.5rem))] border border-[#173247]/20 bg-[#fbf8f1] p-4 shadow-[0_18px_50px_rgba(16,40,58,0.16)]"
                        aria-label="Mobile navigation"
                    >
                        <div className="grid gap-1">
                            {navItems.map(([label, href]) => (
                                <a
                                    key={href}
                                    href={href}
                                    className="px-3 py-3 text-sm font-medium text-[#294154] hover:bg-[#efe8db] hover:text-[#0f5a43] focus-visible:outline-2 focus-visible:outline-[#b87949]"
                                >
                                    {label}
                                </a>
                            ))}
                        </div>

                        <div className="mt-4 border-t border-[#173247]/15 pt-4">
                            <AuthActions isAuthenticated={isAuthenticated} />
                        </div>
                    </nav>
                </details>
            </div>
        </header>
    );
}

/** Render the layered operational paper composition shown beside the hero copy. */
function HeroProductComposition() {
    const stockRows = [
        ['Roma Tomatoes', 'Main Kitchen', '18.6', 'kg', 'Low'],
        ['Extra Virgin Olive Oil', 'Main Kitchen', '12', 'L', 'OK'],
        ['Chicken Breast', 'Main Kitchen', '5.2', 'kg', 'Low'],
        ['House Syrup', 'Cafe Counter', '2.1', 'L', 'Low'],
        ['Parmesan Grated', 'Stock Room', '4.5', 'kg', 'OK'],
    ];

    return (
        <div
            aria-hidden="true"
            className="landing-ledger-grid relative mx-auto min-h-[560px] w-full max-w-[720px] overflow-hidden border border-[#173247]/15 bg-[#eee8dc] shadow-[0_24px_65px_rgba(16,40,58,0.12)] sm:min-h-[620px] lg:min-h-[660px]"
        >
            <div className="absolute inset-x-[8%] top-[5%] h-[82%] rotate-[0.8deg] border border-[#a8b5ae]/35 bg-[#efe8d8]/75" />

            <div className="absolute inset-y-[9%] right-[3%] w-8 border border-[#9a704d]/45 bg-[#6d5b3f] text-center text-[10px] font-semibold tracking-[0.18em] text-[#f6efdf] [writing-mode:vertical-rl]">
                MAIN KITCHEN
            </div>

            <section className="landing-enter absolute top-[8%] left-[7%] w-[71%] border border-[#173247]/18 bg-[#fffdf8] p-4 shadow-[0_16px_38px_rgba(16,40,58,0.14)] sm:p-5">
                <div className="mb-3 flex items-center justify-between gap-3">
                    <div>
                        <p className="text-xs font-semibold uppercase tracking-[0.14em] text-[#61717c]">
                            Stock overview
                        </p>
                        <p className="mt-1 font-serif text-xl text-[#10283a]">
                            Main Kitchen
                        </p>
                    </div>

                    <span className="border border-[#0f5a43]/25 bg-[#edf4ef] px-2 py-1 text-[10px] font-semibold text-[#0f5a43]">
                        Illustrative
                    </span>
                </div>

                <div className="mb-3 flex flex-wrap gap-1.5 text-[9px] text-[#576a77]">
                    {[
                        'All locations',
                        'Main Kitchen',
                        'Branch A',
                        'Cafe Counter',
                    ].map((label, index) => (
                        <span
                            key={label}
                            className={`border px-2 py-1 ${
                                index === 1
                                    ? 'border-[#0f5a43]/25 bg-[#eaf2ed] text-[#0f5a43]'
                                    : 'border-[#173247]/12 bg-[#faf7f0]'
                            }`}
                        >
                            {label}
                        </span>
                    ))}
                </div>

                <div className="overflow-hidden border-t border-[#173247]/12 text-[9px] text-[#415565] sm:text-[10px]">
                    <div className="grid grid-cols-[1.5fr_1.15fr_.55fr_.45fr_.55fr] gap-2 py-2 font-semibold uppercase tracking-[0.08em] text-[#74818a]">
                        <span>Item</span>
                        <span>Location</span>
                        <span>On hand</span>
                        <span>Unit</span>
                        <span>Status</span>
                    </div>

                    {stockRows.map(
                        ([item, location, onHand, unit, status]) => (
                            <div
                                key={item}
                                className="grid grid-cols-[1.5fr_1.15fr_.55fr_.45fr_.55fr] gap-2 border-t border-[#173247]/8 py-2"
                            >
                                <span className="truncate font-medium text-[#10283a]">
                                    {item}
                                </span>
                                <span className="truncate">{location}</span>
                                <span>{onHand}</span>
                                <span>{unit}</span>
                                <span
                                    className={
                                        status === 'Low'
                                            ? 'text-[#a64d2e]'
                                            : 'text-[#0f5a43]'
                                    }
                                >
                                    {status}
                                </span>
                            </div>
                        ),
                    )}
                </div>
            </section>

            <aside className="landing-enter landing-enter-delay-1 absolute top-[5%] right-[5%] z-20 w-[27%] min-w-[132px] border border-[#b87949]/25 bg-[#fffaf2] p-3 shadow-[0_15px_35px_rgba(16,40,58,0.16)] sm:p-4">
                <div className="flex items-center gap-2 text-[#a64d2e]">
                    <AlertTriangle className="size-4" aria-hidden="true" />
                    <span className="text-[10px] font-semibold uppercase tracking-[0.1em]">
                        Low stock
                    </span>
                </div>

                <p className="mt-3 text-sm font-semibold text-[#10283a]">
                    Chicken Breast
                </p>
                <p className="mt-1 text-xs text-[#5d6a73]">5.2 kg on hand</p>
                <p className="mt-2 border-t border-[#173247]/10 pt-2 text-[10px] text-[#7d5f49]">
                    Review low-stock report
                </p>
            </aside>

            <aside className="landing-enter landing-enter-delay-2 absolute top-[32%] right-[5%] z-20 w-[28%] min-w-[140px] border border-[#173247]/18 bg-[#fffdf8] p-3 shadow-[0_15px_35px_rgba(16,40,58,0.14)] sm:p-4">
                <p className="text-[10px] font-semibold uppercase tracking-[0.1em] text-[#63727d]">
                    Recipe cost
                </p>

                <p className="mt-1 font-serif text-base text-[#10283a] sm:text-lg">
                    Margherita Pizza
                </p>

                <div className="mt-3 space-y-1.5 text-[9px] text-[#52636e] sm:text-[10px]">
                    {[
                        ['Flour', '$0.13'],
                        ['Tomato Sauce', '$0.28'],
                        ['Mozzarella', '$0.47'],
                        ['Olive Oil', '$0.07'],
                        ['Basil', '$0.03'],
                    ].map(([label, value]) => (
                        <div key={label} className="flex justify-between gap-3">
                            <span>{label}</span>
                            <span>{value}</span>
                        </div>
                    ))}
                </div>

                <div className="mt-3 flex items-end justify-between border-t border-[#173247]/12 pt-3">
                    <span className="text-[10px] font-semibold text-[#63727d]">
                        Total cost
                    </span>
                    <span className="font-serif text-xl font-semibold text-[#10283a]">
                        $0.98
                    </span>
                </div>
            </aside>

            <section className="landing-enter landing-enter-delay-2 absolute bottom-[6%] left-[4%] z-10 w-[48%] -rotate-[1.4deg] border border-[#917d66]/30 bg-[#f7f0e3] p-4 shadow-[0_14px_28px_rgba(16,40,58,0.12)] sm:p-5">
                <div className="flex items-start justify-between gap-3 border-b border-[#6e6559]/20 pb-3">
                    <div>
                        <p className="font-serif text-lg text-[#10283a]">
                            Purchase Order
                        </p>
                        <p className="mt-1 text-[9px] uppercase tracking-[0.12em] text-[#697680]">
                            Green Valley Produce
                        </p>
                    </div>

                    <div className="text-right text-[9px] text-[#5d6a73]">
                        <p>PO-0524</p>
                        <p>May 20, 2026</p>
                    </div>
                </div>

                <div className="mt-3 text-[9px] text-[#52636e] sm:text-[10px]">
                    <div className="grid grid-cols-[1.5fr_.45fr_.55fr_.7fr] gap-2 pb-2 font-semibold uppercase tracking-[0.07em] text-[#7b858b]">
                        <span>Item</span>
                        <span>Qty</span>
                        <span>Unit</span>
                        <span>Total</span>
                    </div>

                    {[
                        ['Roma Tomatoes', '25', 'kg', '$52.50'],
                        ['Chicken Breast', '20', 'kg', '$108.00'],
                        ['Olive Oil', '4', 'L', '$51.60'],
                    ].map(([item, qty, unit, total]) => (
                        <div
                            key={item}
                            className="grid grid-cols-[1.5fr_.45fr_.55fr_.7fr] gap-2 border-t border-[#6e6559]/15 py-2"
                        >
                            <span className="truncate text-[#10283a]">
                                {item}
                            </span>
                            <span>{qty}</span>
                            <span>{unit}</span>
                            <span>{total}</span>
                        </div>
                    ))}
                </div>

                <div className="mt-3 flex items-center justify-between border-t border-[#6e6559]/20 pt-3">
                    <span className="border border-[#0f5a43]/45 px-2 py-1 text-[9px] font-bold uppercase tracking-[0.12em] text-[#0f5a43]">
                        Approved
                    </span>
                    <span className="font-serif text-base font-semibold text-[#10283a]">
                        $212.10
                    </span>
                </div>
            </section>

            <section className="landing-enter landing-enter-delay-3 absolute right-[18%] bottom-[5%] z-20 w-[34%] rotate-[0.7deg] border border-[#917d66]/30 bg-[#fffdf8] p-4 shadow-[0_16px_32px_rgba(16,40,58,0.16)] sm:p-5">
                <div className="flex items-start justify-between gap-2 border-b border-[#6e6559]/18 pb-3">
                    <div>
                        <p className="font-serif text-base text-[#10283a] sm:text-lg">
                            Receiving Note
                        </p>
                        <p className="text-[9px] uppercase tracking-[0.12em] text-[#697680]">
                            RN-0524
                        </p>
                    </div>
                    <span className="text-[9px] text-[#697680]">
                        May 20, 2026
                    </span>
                </div>

                <p className="mt-3 text-[9px] text-[#697680]">From</p>
                <p className="text-xs font-semibold text-[#10283a]">
                    Green Valley Produce
                </p>

                <div className="mt-3 space-y-2 text-[9px] text-[#52636e] sm:text-[10px]">
                    {[
                        ['Roma Tomatoes', '25 kg'],
                        ['Chicken Breast', '20 kg'],
                        ['Olive Oil', '4 L'],
                    ].map(([item, quantity]) => (
                        <div
                            key={item}
                            className="flex justify-between gap-3 border-t border-[#6e6559]/12 pt-2"
                        >
                            <span>{item}</span>
                            <span>{quantity}</span>
                        </div>
                    ))}
                </div>

                <div className="mt-4 flex items-center gap-2 text-[#0f5a43]">
                    <PackageCheck className="size-4" aria-hidden="true" />
                    <span className="text-[10px] font-semibold">Received</span>
                </div>
            </section>
        </div>
    );
}

/** Render one editorial problem-to-outcome statement. */
function EditorialBenefit({
    icon: Icon,
    title,
    description,
}: EditorialBenefitProps) {
    return (
        <article className="border-t border-[#173247]/15 px-0 py-7 sm:px-5 lg:border-t-0 lg:border-l lg:px-8 lg:py-3 first:lg:border-l-0">
            <Icon
                className="size-6 stroke-[1.4] text-[#526a5f]"
                aria-hidden="true"
            />
            <h3 className="mt-5 max-w-[14rem] font-serif text-xl leading-tight text-[#10283a]">
                {title}
            </h3>
            <p className="mt-3 max-w-[17rem] text-sm leading-6 text-[#5b6a74]">
                {description}
            </p>
        </article>
    );
}

/** Render one step in the delivery-to-plate operational story. */
function JourneyStep({
    number,
    icon: Icon,
    title,
    children,
    showConnector = true,
}: JourneyStepProps) {
    return (
        <article className="relative">
            {showConnector && (
                <div
                    className="absolute top-6 left-[calc(100%-0.5rem)] hidden w-[calc(100%-0.75rem)] border-t border-dashed border-[#a87a55]/65 xl:block"
                    aria-hidden="true"
                >
                    <span className="absolute -top-1.5 right-0 block size-3 rotate-45 border-t border-r border-[#a87a55]/65" />
                </div>
            )}

            <div className="flex items-start gap-3">
                <span className="flex size-7 shrink-0 items-center justify-center rounded-full bg-[#0f5a43] text-xs font-bold text-white">
                    {number}
                </span>

                <div className="min-w-0">
                    <Icon
                        className="size-4 text-[#627367]"
                        aria-hidden="true"
                    />
                    <h3 className="mt-2 font-serif text-lg leading-tight text-[#10283a]">
                        {title}
                    </h3>
                </div>
            </div>

            <div className="mt-5 border border-[#173247]/15 bg-[#fffdf8] p-4 shadow-[0_9px_24px_rgba(16,40,58,0.08)] transition duration-300 hover:-translate-y-1 hover:shadow-[0_14px_30px_rgba(16,40,58,0.12)] motion-reduce:transform-none">
                {children}
            </div>
        </article>
    );
}

/** Render a large benefit story with customer-facing copy and a compact product preview. */
function FeatureStory({
    eyebrow,
    title,
    bullets,
    children,
}: FeatureStoryProps) {
    return (
        <article className="grid gap-7 border border-[#173247]/14 bg-[#f8f3e9] p-6 sm:p-8 lg:grid-cols-[0.85fr_1.15fr] lg:items-center">
            <div>
                <p className="text-xs font-bold uppercase tracking-[0.16em] text-[#6d7b73]">
                    {eyebrow}
                </p>
                <h3 className="mt-3 font-serif text-2xl leading-tight text-[#10283a] sm:text-[1.8rem]">
                    {title}
                </h3>
                <ul className="mt-5 space-y-2 text-sm leading-6 text-[#596b76]">
                    {bullets.map((bullet) => (
                        <li key={bullet} className="flex gap-2">
                            <span className="mt-2 size-1.5 shrink-0 rounded-full bg-[#0f5a43]" />
                            <span>{bullet}</span>
                        </li>
                    ))}
                </ul>
            </div>

            <div className="border border-[#173247]/15 bg-[#fffdf8] p-4 shadow-[0_12px_30px_rgba(16,40,58,0.08)]">
                {children}
            </div>
        </article>
    );
}

/** Render the multi-location product preview using repository-supported organization concepts. */
function LocationsPreview() {
    const locations = [
        ['Main Kitchen', 'Kitchen', 'Dry Store, Walk-in', 'Active'],
        ['Cafe Counter', 'Outlet', 'Counter, Fridge', 'Active'],
        ['Stock Room', 'Storage', 'Dry Store, Freezer', 'Active'],
        ['Branch A', 'Outlet', 'Kitchen, Freezer', 'Active'],
    ];

    const menuItems: Array<{ label: string; icon: LucideIcon }> = [
        { label: 'Dashboard', icon: BarChart3 },
        { label: 'Stock', icon: Boxes },
        { label: 'Purchase Orders', icon: ClipboardList },
        { label: 'Receiving', icon: PackageCheck },
        { label: 'Transfers', icon: Truck },
        { label: 'Stock Counts', icon: ClipboardCheck },
        { label: 'Waste', icon: Trash2 },
        { label: 'Recipes', icon: ChefHat },
        { label: 'Suppliers', icon: Store },
        { label: 'Reports', icon: ReceiptText },
        { label: 'Team', icon: Users },
        { label: 'Locations', icon: MapPin },
    ];

    return (
        <div className="grid overflow-hidden border border-white/15 bg-[#fffdf8] shadow-[0_18px_50px_rgba(5,20,31,0.2)] md:grid-cols-[150px_minmax(0,1fr)]">
            <aside className="hidden bg-[#10283a] p-4 text-white md:block">
                <p className="font-serif text-lg">MiseLedger</p>

                <div className="mt-6 space-y-1 text-[11px] text-white/65">
                    {menuItems.map(({ label, icon: MenuIcon }, index) => (
                        <div
                            key={label}
                            className={`flex items-center gap-2 px-2 py-2 ${
                                index === 0 ? 'bg-white/10 text-white' : ''
                            }`}
                        >
                            <MenuIcon
                                className="size-3.5"
                                aria-hidden="true"
                            />
                            <span>{label}</span>
                        </div>
                    ))}
                </div>
            </aside>

            <div className="min-w-0 p-4 sm:p-6">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <p className="text-[10px] font-bold uppercase tracking-[0.14em] text-[#75818a]">
                            Illustrative workspace
                        </p>
                        <h3 className="mt-1 font-serif text-2xl text-[#10283a]">
                            Locations
                        </h3>
                    </div>

                    <span className="border border-[#0f5a43]/25 bg-[#edf4ef] px-3 py-2 text-[10px] font-semibold text-[#0f5a43]">
                        Multi-location view
                    </span>
                </div>

                <div className="mt-5 overflow-x-auto">
                    <div className="min-w-[560px] text-[10px] text-[#596b76]">
                        <div className="grid grid-cols-[1.15fr_.8fr_1.45fr_.55fr] gap-3 border-y border-[#173247]/12 py-2 font-bold uppercase tracking-[0.08em] text-[#75818a]">
                            <span>Location</span>
                            <span>Type</span>
                            <span>Storage areas</span>
                            <span>Status</span>
                        </div>

                        {locations.map(([name, type, storage, status]) => (
                            <div
                                key={name}
                                className="grid grid-cols-[1.15fr_.8fr_1.45fr_.55fr] gap-3 border-b border-[#173247]/8 py-3"
                            >
                                <span className="font-semibold text-[#10283a]">
                                    {name}
                                </span>
                                <span>{type}</span>
                                <span>{storage}</span>
                                <span className="text-[#0f5a43]">
                                    {status}
                                </span>
                            </div>
                        ))}
                    </div>
                </div>

                <div className="mt-6 grid gap-3 sm:grid-cols-4">
                    {[
                        ['Low-stock items', '12'],
                        ['Open purchase orders', '3'],
                        ['Pending receiving', '2'],
                        ['Open stock counts', '1'],
                    ].map(([label, value]) => (
                        <div
                            key={label}
                            className="border-t border-[#173247]/15 pt-3"
                        >
                            <p className="font-serif text-2xl text-[#10283a]">
                                {value}
                            </p>
                            <p className="mt-1 text-[10px] leading-4 text-[#65747d]">
                                {label}
                            </p>
                        </div>
                    ))}
                </div>
            </div>
        </div>
    );
}

/** Render the complete public MiseLedger marketing landing page. */
export default function Welcome() {
    const { auth } = usePage().props;
    const isAuthenticated = Boolean(auth.user);

    const benefits: EditorialBenefitProps[] = [
        {
            icon: Boxes,
            title: 'Stop guessing what is on hand',
            description:
                'Review stock on hand by location so buying decisions start with a clearer picture.',
        },
        {
            icon: AlertTriangle,
            title: 'Catch low stock before service feels it',
            description:
                'Use the low-stock view to see which items need attention before the next busy service.',
        },
        {
            icon: Trash2,
            title: 'See where ingredients are being wasted',
            description:
                'Record waste with reasons so recurring losses are easier to review and discuss.',
        },
        {
            icon: Scale,
            title: 'Understand what every recipe really costs',
            description:
                'Review recipe costs using current ingredient information and make better-informed decisions.',
        },
    ];

    const teamHighlights: Array<{
        label: string;
        icon: LucideIcon;
    }> = [
        { label: 'Defined team access', icon: Users },
        { label: 'Organized storage areas', icon: Warehouse },
        { label: 'Clear location context', icon: MapPin },
    ];

    return (
        <>
            <Head title="MiseLedger | Inventory clarity for food operations">
                <meta
                    name="description"
                    content="MiseLedger helps food operations organize stock, purchasing, receiving, waste, recipe costs, and locations from one clear workspace."
                />
            </Head>

            <style>{`
                html {
                    scroll-behavior: smooth;
                }

                .landing-ledger-grid {
                    background-image:
                        linear-gradient(
                            rgba(51, 86, 76, 0.09) 1px,
                            transparent 1px
                        ),
                        linear-gradient(
                            90deg,
                            rgba(51, 86, 76, 0.09) 1px,
                            transparent 1px
                        );
                    background-size: 26px 26px;
                }

                @keyframes landing-rise {
                    from {
                        opacity: 0;
                        transform: translateY(18px);
                    }

                    to {
                        opacity: 1;
                        transform: translateY(0);
                    }
                }

                .landing-enter {
                    animation: landing-rise 650ms ease-out both;
                }

                .landing-enter-delay-1 {
                    animation-delay: 90ms;
                }

                .landing-enter-delay-2 {
                    animation-delay: 180ms;
                }

                .landing-enter-delay-3 {
                    animation-delay: 270ms;
                }

                @media (prefers-reduced-motion: reduce) {
                    html {
                        scroll-behavior: auto;
                    }

                    .landing-enter,
                    .landing-enter-delay-1,
                    .landing-enter-delay-2,
                    .landing-enter-delay-3 {
                        animation: none;
                    }
                }
            `}</style>

            <div
                id="top"
                className="min-h-screen overflow-x-clip bg-[#f7f3ea] text-[#10283a]"
            >
                <MarketingHeader isAuthenticated={isAuthenticated} />

                <main>
                    <section className="border-b border-[#173247]/15">
                        <div className="mx-auto grid max-w-[1440px] gap-12 px-5 py-14 sm:px-8 sm:py-20 lg:grid-cols-[0.82fr_1.18fr] lg:items-center lg:px-12 lg:py-24">
                            <div className="landing-enter max-w-xl">
                                <p className="inline-flex items-center gap-2 border border-[#0f5a43]/25 bg-[#eef3ec] px-3 py-2 text-xs font-semibold text-[#315846]">
                                    <ChefHat
                                        className="size-4"
                                        aria-hidden="true"
                                    />
                                    Made for restaurants, cafés, kitchens, and
                                    food teams
                                </p>

                                <h1 className="mt-7 max-w-[10ch] text-balance font-serif text-5xl leading-[0.96] font-semibold tracking-[-0.045em] text-[#10283a] sm:text-6xl lg:text-[4.6rem]">
                                    Know what you have before you buy more.
                                </h1>

                                <p className="mt-7 max-w-lg text-base leading-7 text-[#526674] sm:text-lg sm:leading-8">
                                    MiseLedger helps food teams stay on top of
                                    stock, review waste, understand recipe
                                    costs, and keep purchasing and locations
                                    organized from one clear workspace.
                                </p>

                                <div className="mt-8">
                                    <HeroActions
                                        isAuthenticated={isAuthenticated}
                                    />
                                </div>
                            </div>

                            <HeroProductComposition />
                        </div>
                    </section>

                    <section
                        id="why-miseledger"
                        className="scroll-mt-24 border-b border-[#173247]/15 bg-[#fbf8f1]"
                    >
                        <div className="mx-auto grid max-w-[1440px] px-5 sm:grid-cols-2 sm:px-8 lg:grid-cols-4 lg:px-12 lg:py-10">
                            {benefits.map((benefit) => (
                                <EditorialBenefit
                                    key={benefit.title}
                                    {...benefit}
                                />
                            ))}
                        </div>
                    </section>

                    <section
                        id="how-it-works"
                        className="scroll-mt-24 border-b border-[#173247]/15 bg-[#f7f3ea]"
                    >
                        <div className="mx-auto max-w-[1440px] px-5 py-16 sm:px-8 sm:py-20 lg:px-12">
                            <div className="flex flex-col gap-4 border-b border-dashed border-[#a87a55]/55 pb-6 sm:flex-row sm:items-end sm:justify-between">
                                <div>
                                    <p className="text-xs font-bold uppercase tracking-[0.16em] text-[#6c7a72]">
                                        A connected operating story
                                    </p>
                                    <h2 className="mt-2 font-serif text-4xl tracking-[-0.035em] text-[#10283a] sm:text-5xl">
                                        From delivery to plate
                                    </h2>
                                </div>

                                <p className="max-w-md text-sm leading-6 text-[#60717c]">
                                    Follow stock from receiving through
                                    movement, counting, waste, and recipe
                                    costing without turning the day into
                                    spreadsheet detective work.
                                </p>
                            </div>

                            <div className="mt-10 grid gap-8 md:grid-cols-2 xl:grid-cols-6 xl:gap-5">
                                <JourneyStep
                                    number={1}
                                    icon={PackageCheck}
                                    title="Receive what arrives"
                                >
                                    <p className="text-[10px] font-bold uppercase tracking-[0.12em] text-[#71808a]">
                                        Receiving Note · RN-0524
                                    </p>
                                    <div className="mt-3 space-y-2 text-xs text-[#566975]">
                                        <div className="flex justify-between">
                                            <span>Roma Tomatoes</span>
                                            <span>25 kg</span>
                                        </div>
                                        <div className="flex justify-between">
                                            <span>Chicken Breast</span>
                                            <span>20 kg</span>
                                        </div>
                                        <div className="flex justify-between">
                                            <span>Olive Oil</span>
                                            <span>4 L</span>
                                        </div>
                                    </div>
                                    <p className="mt-4 flex items-center gap-2 text-xs font-semibold text-[#0f5a43]">
                                        <PackageCheck
                                            className="size-4"
                                            aria-hidden="true"
                                        />
                                        Received
                                    </p>
                                </JourneyStep>

                                <JourneyStep
                                    number={2}
                                    icon={Boxes}
                                    title="Keep stock quantities current"
                                >
                                    <p className="text-[10px] font-bold uppercase tracking-[0.12em] text-[#71808a]">
                                        Stock on hand
                                    </p>
                                    <div className="mt-3 space-y-2 text-xs text-[#566975]">
                                        <div className="flex justify-between">
                                            <span>Roma Tomatoes</span>
                                            <span>25 kg</span>
                                        </div>
                                        <div className="flex justify-between">
                                            <span>Chicken Breast</span>
                                            <span>20 kg</span>
                                        </div>
                                        <div className="flex justify-between">
                                            <span>Olive Oil</span>
                                            <span>4 L</span>
                                        </div>
                                    </div>
                                    <p className="mt-4 text-xs font-semibold text-[#0f5a43]">
                                        Updated after receiving
                                    </p>
                                </JourneyStep>

                                <JourneyStep
                                    number={3}
                                    icon={Truck}
                                    title="Move stock between locations"
                                >
                                    <p className="text-[10px] font-bold uppercase tracking-[0.12em] text-[#71808a]">
                                        Transfer · TR-0148
                                    </p>
                                    <p className="mt-2 text-xs font-semibold text-[#10283a]">
                                        Main Kitchen → Branch A
                                    </p>
                                    <div className="mt-3 space-y-2 text-xs text-[#566975]">
                                        <div className="flex justify-between">
                                            <span>Roma Tomatoes</span>
                                            <span>10 kg</span>
                                        </div>
                                        <div className="flex justify-between">
                                            <span>Chicken Breast</span>
                                            <span>5 kg</span>
                                        </div>
                                    </div>
                                    <p className="mt-4 text-xs font-semibold text-[#355f87]">
                                        Shipped
                                    </p>
                                </JourneyStep>

                                <JourneyStep
                                    number={4}
                                    icon={ClipboardCheck}
                                    title="Count what is really on hand"
                                >
                                    <p className="text-[10px] font-bold uppercase tracking-[0.12em] text-[#71808a]">
                                        Stock Count · May 26
                                    </p>
                                    <div className="mt-3 space-y-2 text-xs text-[#566975]">
                                        <div className="flex justify-between">
                                            <span>System</span>
                                            <span>5.2 kg</span>
                                        </div>
                                        <div className="flex justify-between">
                                            <span>Counted</span>
                                            <span>5.1 kg</span>
                                        </div>
                                    </div>
                                    <p className="mt-4 text-xs font-semibold text-[#a64d2e]">
                                        Variance · -0.1 kg
                                    </p>
                                </JourneyStep>

                                <JourneyStep
                                    number={5}
                                    icon={Trash2}
                                    title="Track what is wasted"
                                >
                                    <p className="text-[10px] font-bold uppercase tracking-[0.12em] text-[#71808a]">
                                        Waste Record · WR-0303
                                    </p>
                                    <div className="mt-3 space-y-2 text-xs text-[#566975]">
                                        <div className="flex justify-between">
                                            <span>Tomatoes</span>
                                            <span>1.2 kg</span>
                                        </div>
                                        <div className="flex justify-between">
                                            <span>Reason</span>
                                            <span>Spoilage</span>
                                        </div>
                                    </div>
                                    <p className="mt-4 text-xs font-semibold text-[#8d5d44]">
                                        Recorded
                                    </p>
                                </JourneyStep>

                                <JourneyStep
                                    number={6}
                                    icon={Scale}
                                    title="See recipe cost clearly"
                                    showConnector={false}
                                >
                                    <p className="text-[10px] font-bold uppercase tracking-[0.12em] text-[#71808a]">
                                        Recipe Cost
                                    </p>
                                    <p className="mt-2 font-serif text-lg text-[#10283a]">
                                        Margherita Pizza
                                    </p>
                                    <p className="mt-5 text-[10px] uppercase tracking-[0.1em] text-[#71808a]">
                                        Total cost
                                    </p>
                                    <p className="mt-1 font-serif text-3xl font-semibold text-[#10283a]">
                                        $0.98
                                    </p>
                                </JourneyStep>
                            </div>
                        </div>
                    </section>

                    <section
                        id="what-you-can-see"
                        className="scroll-mt-24 border-b border-[#173247]/15 bg-[#fbf8f1]"
                    >
                        <div className="mx-auto max-w-[1440px] px-5 py-16 sm:px-8 sm:py-20 lg:px-12">
                            <div className="max-w-2xl">
                                <p className="text-xs font-bold uppercase tracking-[0.16em] text-[#6d7b73]">
                                    What you can see
                                </p>
                                <h2 className="mt-3 font-serif text-4xl tracking-[-0.035em] text-[#10283a] sm:text-5xl">
                                    The everyday details that keep operations
                                    moving
                                </h2>
                            </div>

                            <div className="mt-10 grid gap-5 xl:grid-cols-2">
                                <FeatureStory
                                    eyebrow="Inventory visibility"
                                    title="See your stock clearly across every location"
                                    bullets={[
                                        'Review stock on hand by location',
                                        'Check low-stock items that need attention',
                                        'Review stock movement history',
                                    ]}
                                >
                                    <div className="flex flex-wrap gap-1.5 text-[9px] text-[#61717b]">
                                        {[
                                            'All',
                                            'Main Kitchen',
                                            'Stock Room',
                                            'Branch A',
                                        ].map((label, index) => (
                                            <span
                                                key={label}
                                                className={`border px-2 py-1 ${
                                                    index === 0
                                                        ? 'border-[#0f5a43]/25 bg-[#edf4ef] text-[#0f5a43]'
                                                        : 'border-[#173247]/12'
                                                }`}
                                            >
                                                {label}
                                            </span>
                                        ))}
                                    </div>

                                    <div className="mt-4 text-[10px] text-[#5b6c77]">
                                        {[
                                            [
                                                'Milk',
                                                '6 L',
                                                'Main Kitchen',
                                            ],
                                            [
                                                'Basil',
                                                '0.3 kg',
                                                'Main Kitchen',
                                            ],
                                            [
                                                'Parmesan',
                                                '4.5 kg',
                                                'Stock Room',
                                            ],
                                        ].map(([item, qty, location]) => (
                                            <div
                                                key={item}
                                                className="grid grid-cols-[1fr_.65fr_1fr] gap-2 border-t border-[#173247]/10 py-2.5"
                                            >
                                                <span className="font-semibold text-[#10283a]">
                                                    {item}
                                                </span>
                                                <span>{qty}</span>
                                                <span>{location}</span>
                                            </div>
                                        ))}
                                    </div>
                                </FeatureStory>

                                <FeatureStory
                                    eyebrow="Purchasing and receiving"
                                    title="Simpler buying, receiving, and supplier tracking"
                                    bullets={[
                                        'Manage purchase orders',
                                        'Record received goods',
                                        'Review supplier pricing over time',
                                    ]}
                                >
                                    <div className="flex items-start justify-between gap-4 border-b border-[#173247]/12 pb-3">
                                        <div>
                                            <p className="font-serif text-lg text-[#10283a]">
                                                Purchase Order
                                            </p>
                                            <p className="text-[10px] text-[#71808a]">
                                                Green Valley Produce
                                            </p>
                                        </div>
                                        <span className="text-[10px] text-[#71808a]">
                                            PO-0524
                                        </span>
                                    </div>

                                    <div className="mt-4 flex items-end justify-between gap-4">
                                        <div>
                                            <p className="text-[10px] uppercase tracking-[0.1em] text-[#71808a]">
                                                Total
                                            </p>
                                            <p className="mt-1 font-serif text-2xl text-[#10283a]">
                                                $212.10
                                            </p>
                                        </div>
                                        <span className="border border-[#315f82]/20 bg-[#edf3f8] px-2 py-1 text-[10px] font-semibold text-[#315f82]">
                                            Approved
                                        </span>
                                    </div>
                                </FeatureStory>

                                <FeatureStory
                                    eyebrow="Physical control"
                                    title="Counts and waste made easier to review"
                                    bullets={[
                                        'Compare counted stock with system quantities',
                                        'Review stock-count variance',
                                        'Record waste with clear reasons',
                                    ]}
                                >
                                    <div className="flex items-start justify-between gap-4 border-b border-[#173247]/12 pb-3">
                                        <div>
                                            <p className="font-serif text-lg text-[#10283a]">
                                                Stock Count
                                            </p>
                                            <p className="text-[10px] text-[#71808a]">
                                                Main Kitchen · May 26
                                            </p>
                                        </div>
                                        <ClipboardCheck
                                            className="size-5 text-[#0f5a43]"
                                            aria-hidden="true"
                                        />
                                    </div>

                                    <div className="mt-3 text-[10px] text-[#5b6c77]">
                                        {[
                                            [
                                                'Chicken Breast',
                                                '5.2 kg',
                                                '5.1 kg',
                                                '-0.1 kg',
                                            ],
                                            [
                                                'Roma Tomatoes',
                                                '18.6 kg',
                                                '18.0 kg',
                                                '-0.6 kg',
                                            ],
                                            [
                                                'Olive Oil',
                                                '12 L',
                                                '12 L',
                                                '0 L',
                                            ],
                                        ].map(
                                            ([
                                                item,
                                                system,
                                                counted,
                                                variance,
                                            ]) => (
                                                <div
                                                    key={item}
                                                    className="grid grid-cols-[1.25fr_.75fr_.75fr_.65fr] gap-2 border-t border-[#173247]/10 py-2.5"
                                                >
                                                    <span className="font-semibold text-[#10283a]">
                                                        {item}
                                                    </span>
                                                    <span>{system}</span>
                                                    <span>{counted}</span>
                                                    <span
                                                        className={
                                                            variance.startsWith(
                                                                '-',
                                                            )
                                                                ? 'text-[#a64d2e]'
                                                                : 'text-[#0f5a43]'
                                                        }
                                                    >
                                                        {variance}
                                                    </span>
                                                </div>
                                            ),
                                        )}
                                    </div>
                                </FeatureStory>

                                <FeatureStory
                                    eyebrow="Costs and reporting"
                                    title="Know your costs and review the numbers that matter"
                                    bullets={[
                                        'See recipe costs',
                                        'Review inventory value',
                                        'Export operational reports',
                                    ]}
                                >
                                    <div className="flex items-start justify-between gap-4 border-b border-[#173247]/12 pb-3">
                                        <div>
                                            <p className="font-serif text-lg text-[#10283a]">
                                                Recipe Cost
                                            </p>
                                            <p className="text-[10px] text-[#71808a]">
                                                Chicken Teriyaki Bowl
                                            </p>
                                        </div>
                                        <span className="font-serif text-2xl text-[#10283a]">
                                            $2.31
                                        </span>
                                    </div>

                                    <div className="mt-3 space-y-2 text-[10px] text-[#5b6c77]">
                                        {[
                                            ['Chicken Thigh', '$0.76'],
                                            ['Teriyaki Sauce', '$0.48'],
                                            ['Rice', '$0.39'],
                                            ['Green Onion', '$0.05'],
                                            ['Sesame Seeds', '$0.03'],
                                        ].map(([item, cost]) => (
                                            <div
                                                key={item}
                                                className="flex justify-between gap-3"
                                            >
                                                <span>{item}</span>
                                                <span>{cost}</span>
                                            </div>
                                        ))}
                                    </div>
                                </FeatureStory>
                            </div>
                        </div>
                    </section>

                    <section
                        id="for-your-team"
                        className="scroll-mt-24 bg-[#f7f3ea]"
                    >
                        <div className="mx-auto grid max-w-[1440px] gap-10 px-5 py-16 sm:px-8 sm:py-20 lg:grid-cols-[0.34fr_0.66fr] lg:items-center lg:px-12">
                            <div>
                                <p className="text-xs font-bold uppercase tracking-[0.16em] text-[#6d7b73]">
                                    For your team
                                </p>

                                <h2 className="mt-3 max-w-[12ch] font-serif text-4xl tracking-[-0.035em] text-[#10283a] sm:text-5xl">
                                    Keep every location on the same page
                                </h2>

                                <p className="mt-5 max-w-md text-base leading-7 text-[#5b6c77]">
                                    Organize locations, storage areas, and team
                                    access so day-to-day stock work stays
                                    connected to the right place and people.
                                </p>

                                <div className="mt-7 grid gap-4 sm:grid-cols-3 lg:grid-cols-1 xl:grid-cols-3">
                                    {teamHighlights.map(
                                        ({
                                            label,
                                            icon: BenefitIcon,
                                        }) => (
                                            <div
                                                key={label}
                                                className="border-t border-[#173247]/15 pt-4"
                                            >
                                                <BenefitIcon
                                                    className="size-5 text-[#526a5f]"
                                                    aria-hidden="true"
                                                />
                                                <p className="mt-2 text-xs font-semibold leading-5 text-[#405666]">
                                                    {label}
                                                </p>
                                            </div>
                                        ),
                                    )}
                                </div>
                            </div>

                            <LocationsPreview />
                        </div>
                    </section>

                    <section className="bg-[#0d2a3f] text-white">
                        <div className="mx-auto grid max-w-[1440px] gap-8 px-5 py-14 sm:px-8 sm:py-16 lg:grid-cols-[1fr_auto] lg:items-center lg:px-12">
                            <div className="relative border border-dashed border-[#bb7e51]/65 p-6 sm:p-8">
                                <div
                                    className="absolute top-4 right-5 opacity-20"
                                    aria-hidden="true"
                                >
                                    <ChefHat className="size-16 stroke-[1] text-[#d6a06e]" />
                                </div>

                                <p className="text-xs font-bold uppercase tracking-[0.16em] text-[#d6a06e]">
                                    MiseLedger
                                </p>

                                <h2 className="mt-3 max-w-[18ch] font-serif text-3xl leading-tight tracking-[-0.03em] sm:text-4xl">
                                    Run a more organized kitchen with better
                                    visibility every day.
                                </h2>

                                <p className="mt-4 max-w-xl text-sm leading-6 text-white/70 sm:text-base">
                                    Keep stock, purchasing, receiving, waste,
                                    recipe costs, and locations easier to
                                    review from one place.
                                </p>
                            </div>

                            <AuthActions
                                isAuthenticated={isAuthenticated}
                                inverse
                            />
                        </div>
                    </section>
                </main>

                <footer className="border-t border-[#173247]/15 bg-[#f7f3ea]">
                    <div className="mx-auto flex max-w-[1440px] flex-col gap-6 px-5 py-8 sm:px-8 lg:flex-row lg:items-center lg:justify-between lg:px-12">
                        <div>
                            <a
                                href="#top"
                                className="font-serif text-xl font-semibold text-[#10283a]"
                            >
                                MiseLedger
                            </a>
                            <p className="mt-1 text-xs text-[#697984]">
                                Inventory clarity for food operations.
                            </p>
                        </div>

                        <nav
                            className="flex flex-wrap gap-x-5 gap-y-3 text-xs font-medium text-[#536773]"
                            aria-label="Footer navigation"
                        >
                            <a
                                href="#why-miseledger"
                                className="hover:text-[#0f5a43]"
                            >
                                Why MiseLedger
                            </a>
                            <a
                                href="#how-it-works"
                                className="hover:text-[#0f5a43]"
                            >
                                How It Works
                            </a>
                            <a
                                href="#what-you-can-see"
                                className="hover:text-[#0f5a43]"
                            >
                                What You Can See
                            </a>
                            <a
                                href="#for-your-team"
                                className="hover:text-[#0f5a43]"
                            >
                                For Your Team
                            </a>
                        </nav>

                        <AuthActions isAuthenticated={isAuthenticated} />
                    </div>
                </footer>
            </div>
        </>
    );
}
