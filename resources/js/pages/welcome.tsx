import { Head, Link, usePage } from '@inertiajs/react';
import {
    ArrowRight,
    BarChart3,
    Boxes,
    ChefHat,
    ClipboardCheck,
    ClipboardList,
    MapPin,
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

import {
    AuthActions,
    MarketingHeader,
} from '@/components/marketing/marketing-header';
import { ProblemSection } from '@/components/marketing/problem-section';
import { ProductProofStrip } from '@/components/marketing/product-proof-strip';
import { dashboard, login, register } from '@/routes';

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

type WelcomePlan = {
    code: string;
    name: string;
};

type WelcomeProps = {
    trialDays: number | null;
    plans: WelcomePlan[];
};

/** Render the hero-specific primary conversion actions. */
function HeroActions({ isAuthenticated }: { isAuthenticated: boolean }) {
    if (isAuthenticated) {
        return (
            <Link
                href={dashboard()}
                className="inline-flex min-h-12 items-center justify-center gap-2 border border-marketing-accent bg-marketing-accent px-6 text-sm font-semibold text-marketing-accent-foreground transition hover:bg-marketing-accent-hover focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-marketing-focus"
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
                className="inline-flex min-h-12 items-center justify-center gap-2 border border-marketing-accent bg-marketing-accent px-6 text-sm font-semibold text-marketing-accent-foreground transition hover:bg-marketing-accent-hover focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-marketing-focus"
            >
                Create an account
                <ArrowRight className="size-4" aria-hidden="true" />
            </Link>

            <Link
                href={login()}
                className="inline-flex min-h-12 items-center justify-center border border-marketing-border/40 bg-transparent px-6 text-sm font-semibold text-marketing-ink transition hover:border-marketing-border hover:bg-marketing-surface focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-marketing-focus"
            >
                Log in
            </Link>
        </div>
    );
}

/** Render the real MiseLedger dashboard as the primary hero product visual. */
function HeroDashboardPreview() {
    return (
        <div className="marketing-enter relative mx-auto w-full max-w-[920px] lg:w-[112%] lg:max-w-none xl:w-[118%]">
            <div
                className="absolute -inset-3 translate-x-3 translate-y-3 border border-marketing-focus/20 bg-[#ece5d8]"
                aria-hidden="true"
            />

            <figure className="relative overflow-hidden border border-marketing-border/18 bg-marketing-surface p-1.5 shadow-[0_24px_65px_rgba(16,40,58,0.16)] sm:p-2">
                <img
                    src="/images/hero-image.png"
                    alt="MiseLedger dashboard showing inventory value, low-stock alerts, purchase orders, receiving, stock counts, and recent inventory activity."
                    width={1894}
                    height={941}
                    loading="eager"
                    fetchPriority="high"
                    decoding="async"
                    className="block h-auto w-full border border-marketing-border/10 bg-marketing-surface"
                />
            </figure>
        </div>
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
                <span className="flex size-7 shrink-0 items-center justify-center rounded-full bg-marketing-accent text-xs font-bold text-marketing-accent-foreground">
                    {number}
                </span>

                <div className="min-w-0">
                    <Icon
                        className="size-4 text-[#627367]"
                        aria-hidden="true"
                    />
                    <h3 className="mt-2 font-serif text-lg leading-tight text-marketing-ink">
                        {title}
                    </h3>
                </div>
            </div>

            <div className="mt-5 border border-marketing-border/15 bg-marketing-surface p-4 shadow-[0_9px_24px_rgba(16,40,58,0.08)] transition duration-300 hover:-translate-y-1 hover:shadow-[0_14px_30px_rgba(16,40,58,0.12)] motion-reduce:transform-none">
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
        <article className="grid gap-7 border border-marketing-border/14 bg-marketing-surface-muted p-6 sm:p-8 lg:grid-cols-[0.85fr_1.15fr] lg:items-center">
            <div>
                <p className="text-xs font-bold tracking-[0.16em] text-marketing-muted uppercase">
                    {eyebrow}
                </p>
                <h3 className="mt-3 font-serif text-2xl leading-tight text-marketing-ink sm:text-[1.8rem]">
                    {title}
                </h3>
                <ul className="mt-5 space-y-2 text-sm leading-6 text-[#596b76]">
                    {bullets.map((bullet) => (
                        <li key={bullet} className="flex gap-2">
                            <span className="mt-2 size-1.5 shrink-0 rounded-full bg-marketing-accent" />
                            <span>{bullet}</span>
                        </li>
                    ))}
                </ul>
            </div>

            <div className="border border-marketing-border/15 bg-marketing-surface p-4 shadow-[0_12px_30px_rgba(16,40,58,0.08)]">
                {children}
            </div>
        </article>
    );
}

/** Render the auth-aware trial and subscription section using only approved billing configuration. */
function PricingAndTrial({
    trialDays,
    plans,
    isAuthenticated,
}: {
    trialDays: number | null;
    plans: WelcomePlan[];
    isAuthenticated: boolean;
}) {
    return (
        <section
            id="pricing"
            className="scroll-mt-24 border-b border-marketing-border/15 bg-marketing-surface-muted"
        >
            <div className="mx-auto max-w-[1440px] px-5 py-16 sm:px-8 sm:py-20 lg:px-12">
                <div className="max-w-2xl">
                    <p className="text-xs font-bold tracking-[0.16em] text-marketing-muted uppercase">
                        Trial and subscription
                    </p>
                    <h2 className="mt-3 font-serif text-4xl tracking-[-0.035em] text-marketing-ink sm:text-5xl">
                        Try MiseLedger, then subscribe when you are ready
                    </h2>
                    <p className="mt-5 max-w-xl text-base leading-7 text-marketing-muted">
                        {trialDays !== null
                            ? `Every new organization starts with a ${trialDays}-day trial. Subscribe from your organization's billing settings whenever you are ready to continue.`
                            : "Create an organization to start using MiseLedger. Subscription plans are managed from your organization's billing settings."}
                    </p>
                </div>

                {plans.length > 0 && (
                    <div className="mt-10 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        {plans.map((plan) => (
                            <div
                                key={plan.code}
                                className="border border-marketing-border/15 bg-marketing-surface p-5 shadow-[0_9px_24px_rgba(16,40,58,0.08)]"
                            >
                                <h3 className="font-serif text-xl text-marketing-ink">
                                    {plan.name}
                                </h3>
                                <p className="mt-2 text-sm leading-6 text-[#5b6a74]">
                                    Available from your organization's billing
                                    settings once you are signed in.
                                </p>
                            </div>
                        ))}
                    </div>
                )}

                <div className="mt-8">
                    <HeroActions isAuthenticated={isAuthenticated} />
                </div>
            </div>
        </section>
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
        <div className="grid overflow-hidden border border-marketing-border/15 bg-marketing-surface shadow-[0_18px_50px_rgba(5,20,31,0.2)] md:grid-cols-[150px_minmax(0,1fr)]">
            <aside className="hidden bg-marketing-ink p-4 text-marketing-dark-foreground md:block">
                <p className="font-serif text-lg">MiseLedger</p>

                <div className="mt-6 space-y-1 text-[11px] text-marketing-dark-muted">
                    {menuItems.map(({ label, icon: MenuIcon }, index) => (
                        <div
                            key={label}
                            className={`flex items-center gap-2 px-2 py-2 ${
                                index === 0
                                    ? 'bg-marketing-dark-foreground/10 text-marketing-dark-foreground'
                                    : ''
                            }`}
                        >
                            <MenuIcon className="size-3.5" aria-hidden="true" />
                            <span>{label}</span>
                        </div>
                    ))}
                </div>
            </aside>

            <div className="min-w-0 p-4 sm:p-6">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <p className="text-[10px] font-bold tracking-[0.14em] text-[#75818a] uppercase">
                            Illustrative workspace
                        </p>
                        <h3 className="mt-1 font-serif text-2xl text-marketing-ink">
                            Locations
                        </h3>
                    </div>

                    <span className="border border-marketing-accent/25 bg-[#edf4ef] px-3 py-2 text-[10px] font-semibold text-marketing-accent">
                        Multi-location view
                    </span>
                </div>

                <div className="mt-5 overflow-x-auto">
                    <div className="min-w-[560px] text-[10px] text-[#596b76]">
                        <div className="grid grid-cols-[1.15fr_.8fr_1.45fr_.55fr] gap-3 border-y border-marketing-border/12 py-2 font-bold tracking-[0.08em] text-[#75818a] uppercase">
                            <span>Location</span>
                            <span>Type</span>
                            <span>Storage areas</span>
                            <span>Status</span>
                        </div>

                        {locations.map(([name, type, storage, status]) => (
                            <div
                                key={name}
                                className="grid grid-cols-[1.15fr_.8fr_1.45fr_.55fr] gap-3 border-b border-marketing-border/8 py-3"
                            >
                                <span className="font-semibold text-marketing-ink">
                                    {name}
                                </span>
                                <span>{type}</span>
                                <span>{storage}</span>
                                <span className="text-marketing-accent">
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
                            className="border-t border-marketing-border/15 pt-3"
                        >
                            <p className="font-serif text-2xl text-marketing-ink">
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
export default function Welcome({ trialDays, plans }: WelcomeProps) {
    const { auth } = usePage().props;
    const isAuthenticated = Boolean(auth.user);

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

                @media (prefers-reduced-motion: reduce) {
                    html {
                        scroll-behavior: auto;
                    }
                }
            `}</style>

            <div
                id="top"
                className="marketing-theme min-h-screen overflow-x-clip bg-marketing-canvas text-marketing-ink"
            >
                <MarketingHeader isAuthenticated={isAuthenticated} />

                <main>
                    <section className="border-b border-marketing-border/15">
                        <div className="mx-auto grid max-w-[1440px] gap-12 px-5 py-14 sm:px-8 sm:py-20 lg:grid-cols-[0.72fr_1.28fr] lg:items-center lg:gap-10 lg:px-12 lg:py-24 xl:gap-14">
                            <div className="marketing-enter max-w-xl">
                                <p className="inline-flex items-center gap-2 border border-marketing-accent/25 bg-[#eef3ec] px-3 py-2 text-xs font-semibold text-[#315846]">
                                    <ChefHat
                                        className="size-4"
                                        aria-hidden="true"
                                    />
                                    Made for restaurants, cafés, kitchens, and
                                    food teams
                                </p>

                                <h1 className="mt-7 max-w-[10ch] font-serif text-5xl leading-[0.96] font-semibold tracking-[-0.045em] text-balance text-marketing-ink sm:text-6xl lg:text-[4.6rem]">
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

                            <HeroDashboardPreview />
                        </div>
                    </section>

                    <ProductProofStrip />

                    <ProblemSection />

                    <section
                        id="how-it-works"
                        className="scroll-mt-24 border-b border-marketing-border/15 bg-marketing-canvas"
                    >
                        <div className="mx-auto max-w-[1440px] px-5 py-16 sm:px-8 sm:py-20 lg:px-12">
                            <div className="flex flex-col gap-4 border-b border-dashed border-[#a87a55]/55 pb-6 sm:flex-row sm:items-end sm:justify-between">
                                <div>
                                    <p className="text-xs font-bold tracking-[0.16em] text-[#6c7a72] uppercase">
                                        A connected operating story
                                    </p>
                                    <h2 className="mt-2 font-serif text-4xl tracking-[-0.035em] text-marketing-ink sm:text-5xl">
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
                                    <p className="text-[10px] font-bold tracking-[0.12em] text-marketing-muted uppercase">
                                        Receiving Note · RN-0524
                                    </p>
                                    <div className="mt-3 space-y-2 text-xs text-marketing-muted">
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
                                    <p className="mt-4 flex items-center gap-2 text-xs font-semibold text-marketing-accent">
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
                                    <p className="text-[10px] font-bold tracking-[0.12em] text-marketing-muted uppercase">
                                        Stock on hand
                                    </p>
                                    <div className="mt-3 space-y-2 text-xs text-marketing-muted">
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
                                    <p className="mt-4 text-xs font-semibold text-marketing-accent">
                                        Updated after receiving
                                    </p>
                                </JourneyStep>

                                <JourneyStep
                                    number={3}
                                    icon={Truck}
                                    title="Move stock between locations"
                                >
                                    <p className="text-[10px] font-bold tracking-[0.12em] text-marketing-muted uppercase">
                                        Transfer · TR-0148
                                    </p>
                                    <p className="mt-2 text-xs font-semibold text-marketing-ink">
                                        Main Kitchen → Branch A
                                    </p>
                                    <div className="mt-3 space-y-2 text-xs text-marketing-muted">
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
                                    <p className="text-[10px] font-bold tracking-[0.12em] text-marketing-muted uppercase">
                                        Stock Count · May 26
                                    </p>
                                    <div className="mt-3 space-y-2 text-xs text-marketing-muted">
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
                                    <p className="text-[10px] font-bold tracking-[0.12em] text-marketing-muted uppercase">
                                        Waste Record · WR-0303
                                    </p>
                                    <div className="mt-3 space-y-2 text-xs text-marketing-muted">
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
                                    <p className="text-[10px] font-bold tracking-[0.12em] text-marketing-muted uppercase">
                                        Recipe Cost
                                    </p>
                                    <p className="mt-2 font-serif text-lg text-marketing-ink">
                                        Margherita Pizza
                                    </p>
                                    <p className="mt-5 text-[10px] tracking-[0.1em] text-marketing-muted uppercase">
                                        Total cost
                                    </p>
                                    <p className="mt-1 font-serif text-3xl font-semibold text-marketing-ink">
                                        $0.98
                                    </p>
                                </JourneyStep>
                            </div>
                        </div>
                    </section>

                    <section
                        id="what-you-can-see"
                        className="scroll-mt-24 border-b border-marketing-border/15 bg-marketing-surface-muted"
                    >
                        <div className="mx-auto max-w-[1440px] px-5 py-16 sm:px-8 sm:py-20 lg:px-12">
                            <div className="max-w-2xl">
                                <p className="text-xs font-bold tracking-[0.16em] text-marketing-muted uppercase">
                                    What you can see
                                </p>
                                <h2 className="mt-3 font-serif text-4xl tracking-[-0.035em] text-marketing-ink sm:text-5xl">
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
                                                        ? 'border-marketing-accent/25 bg-[#edf4ef] text-marketing-accent'
                                                        : 'border-marketing-border/12'
                                                }`}
                                            >
                                                {label}
                                            </span>
                                        ))}
                                    </div>

                                    <div className="mt-4 text-[10px] text-marketing-muted">
                                        {[
                                            ['Milk', '6 L', 'Main Kitchen'],
                                            ['Basil', '0.3 kg', 'Main Kitchen'],
                                            [
                                                'Parmesan',
                                                '4.5 kg',
                                                'Stock Room',
                                            ],
                                        ].map(([item, qty, location]) => (
                                            <div
                                                key={item}
                                                className="grid grid-cols-[1fr_.65fr_1fr] gap-2 border-t border-marketing-border/10 py-2.5"
                                            >
                                                <span className="font-semibold text-marketing-ink">
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
                                    <div className="flex items-start justify-between gap-4 border-b border-marketing-border/12 pb-3">
                                        <div>
                                            <p className="font-serif text-lg text-marketing-ink">
                                                Purchase Order
                                            </p>
                                            <p className="text-[10px] text-marketing-muted">
                                                Green Valley Produce
                                            </p>
                                        </div>
                                        <span className="text-[10px] text-marketing-muted">
                                            PO-0524
                                        </span>
                                    </div>

                                    <div className="mt-4 flex items-end justify-between gap-4">
                                        <div>
                                            <p className="text-[10px] tracking-[0.1em] text-marketing-muted uppercase">
                                                Total
                                            </p>
                                            <p className="mt-1 font-serif text-2xl text-marketing-ink">
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
                                    <div className="flex items-start justify-between gap-4 border-b border-marketing-border/12 pb-3">
                                        <div>
                                            <p className="font-serif text-lg text-marketing-ink">
                                                Stock Count
                                            </p>
                                            <p className="text-[10px] text-marketing-muted">
                                                Main Kitchen · May 26
                                            </p>
                                        </div>
                                        <ClipboardCheck
                                            className="size-5 text-marketing-accent"
                                            aria-hidden="true"
                                        />
                                    </div>

                                    <div className="mt-3 text-[10px] text-marketing-muted">
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
                                                    className="grid grid-cols-[1.25fr_.75fr_.75fr_.65fr] gap-2 border-t border-marketing-border/10 py-2.5"
                                                >
                                                    <span className="font-semibold text-marketing-ink">
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
                                                                : 'text-marketing-accent'
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
                                    <div className="flex items-start justify-between gap-4 border-b border-marketing-border/12 pb-3">
                                        <div>
                                            <p className="font-serif text-lg text-marketing-ink">
                                                Recipe Cost
                                            </p>
                                            <p className="text-[10px] text-marketing-muted">
                                                Chicken Teriyaki Bowl
                                            </p>
                                        </div>
                                        <span className="font-serif text-2xl text-marketing-ink">
                                            $2.31
                                        </span>
                                    </div>

                                    <div className="mt-3 space-y-2 text-[10px] text-marketing-muted">
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
                        className="scroll-mt-24 bg-marketing-canvas"
                    >
                        <div className="mx-auto grid max-w-[1440px] gap-10 px-5 py-16 sm:px-8 sm:py-20 lg:grid-cols-[0.34fr_0.66fr] lg:items-center lg:px-12">
                            <div>
                                <p className="text-xs font-bold tracking-[0.16em] text-marketing-muted uppercase">
                                    For your team
                                </p>

                                <h2 className="mt-3 max-w-[12ch] font-serif text-4xl tracking-[-0.035em] text-marketing-ink sm:text-5xl">
                                    Keep every location on the same page
                                </h2>

                                <p className="mt-5 max-w-md text-base leading-7 text-marketing-muted">
                                    Organize locations, storage areas, and team
                                    access so day-to-day stock work stays
                                    connected to the right place and people.
                                </p>

                                <div className="mt-7 grid gap-4 sm:grid-cols-3 lg:grid-cols-1 xl:grid-cols-3">
                                    {teamHighlights.map(
                                        ({ label, icon: BenefitIcon }) => (
                                            <div
                                                key={label}
                                                className="border-t border-marketing-border/15 pt-4"
                                            >
                                                <BenefitIcon
                                                    className="size-5 text-[#526a5f]"
                                                    aria-hidden="true"
                                                />
                                                <p className="mt-2 text-xs leading-5 font-semibold text-[#405666]">
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

                    <PricingAndTrial
                        trialDays={trialDays}
                        plans={plans}
                        isAuthenticated={isAuthenticated}
                    />

                    <section className="bg-marketing-dark text-marketing-dark-foreground">
                        <div className="mx-auto grid max-w-[1440px] gap-8 px-5 py-14 sm:px-8 sm:py-16 lg:grid-cols-[1fr_auto] lg:items-center lg:px-12">
                            <div className="relative border border-dashed border-[#bb7e51]/65 p-6 sm:p-8">
                                <div
                                    className="absolute top-4 right-5 opacity-20"
                                    aria-hidden="true"
                                >
                                    <ChefHat className="size-16 stroke-[1] text-[#d6a06e]" />
                                </div>

                                <p className="text-xs font-bold tracking-[0.16em] text-[#d6a06e] uppercase">
                                    MiseLedger
                                </p>

                                <h2 className="mt-3 max-w-[18ch] font-serif text-3xl leading-tight tracking-[-0.03em] sm:text-4xl">
                                    Run a more organized kitchen with better
                                    visibility every day.
                                </h2>

                                <p className="mt-4 max-w-xl text-sm leading-6 text-marketing-dark-muted sm:text-base">
                                    Keep stock, purchasing, receiving, waste,
                                    recipe costs, and locations easier to review
                                    from one place.
                                </p>
                            </div>

                            <AuthActions
                                isAuthenticated={isAuthenticated}
                                inverse
                            />
                        </div>
                    </section>
                </main>

                <footer className="border-t border-marketing-border/15 bg-marketing-canvas">
                    <div className="mx-auto flex max-w-[1440px] flex-col gap-6 px-5 py-8 sm:px-8 lg:flex-row lg:items-center lg:justify-between lg:px-12">
                        <div>
                            <a
                                href="#top"
                                className="font-serif text-xl font-semibold text-marketing-ink"
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
                                href="#product"
                                className="hover:text-marketing-accent"
                            >
                                Product
                            </a>
                            <a
                                href="#how-it-works"
                                className="hover:text-marketing-accent"
                            >
                                How It Works
                            </a>
                            <a
                                href="#what-you-can-see"
                                className="hover:text-marketing-accent"
                            >
                                What You Can See
                            </a>
                            <a
                                href="#for-your-team"
                                className="hover:text-marketing-accent"
                            >
                                For Your Team
                            </a>
                            <a
                                href="#pricing"
                                className="hover:text-marketing-accent"
                            >
                                Pricing
                            </a>
                        </nav>

                        <AuthActions isAuthenticated={isAuthenticated} />
                    </div>
                </footer>
            </div>
        </>
    );
}
