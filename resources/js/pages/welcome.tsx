import { Head, Link, usePage } from '@inertiajs/react';
import {
    ArrowRight,
    Boxes,
    ChefHat,
    ClipboardCheck,
    Eye,
    PackageCheck,
    Plus,
    Scale,
    Truck,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';

import { AiAssistantSection } from '@/components/marketing/ai-assistant-section';
import { AudienceRoleSection } from '@/components/marketing/audience-role-section';
import { FaqSection } from '@/components/marketing/faq-section';
import { FinalCtaSection } from '@/components/marketing/final-cta-section';
import { MarketingFooter } from '@/components/marketing/marketing-footer';
import { MarketingHeader } from '@/components/marketing/marketing-header';
import { MultiLocationStory } from '@/components/marketing/multi-location-story';
import type { WelcomePlan } from '@/components/marketing/pricing-section';
import { PricingSection } from '@/components/marketing/pricing-section';
import { ProblemSection } from '@/components/marketing/problem-section';
import { ProductProofStrip } from '@/components/marketing/product-proof-strip';
import { ProductStory } from '@/components/marketing/product-story';
import { ZoomableImage } from '@/components/marketing/zoomable-image';
import { useHeroParallax, useMarketingReveal } from '@/lib/marketing-motion';
import { dashboard, register } from '@/routes';

type JourneyStepProps = {
    number: number;
    icon: LucideIcon;
    title: string;
    children: ReactNode;
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
        <div className="flex flex-wrap justify-center gap-3">
            <Link
                href={register()}
                className="inline-flex min-h-12 items-center justify-center gap-2 border border-marketing-accent bg-marketing-accent px-6 text-sm font-semibold text-marketing-accent-foreground transition hover:bg-marketing-accent-hover focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-marketing-focus"
            >
                Start free
                <ArrowRight className="size-4" aria-hidden="true" />
            </Link>

            <a
                href="#how-it-works"
                className="inline-flex min-h-12 items-center justify-center border border-marketing-border/40 bg-transparent px-6 text-sm font-semibold text-marketing-ink transition hover:border-marketing-border hover:bg-marketing-surface focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-marketing-focus"
            >
                See how it works
            </a>
        </div>
    );
}

/** Render the real MiseLedger dashboard as the primary hero product visual with a small bounded parallax. */
function HeroDashboardPreview() {
    const parallaxRef = useHeroParallax<HTMLDivElement>();

    return (
        <div className="marketing-enter relative mx-auto w-full max-w-[920px] lg:max-w-[1100px] xl:max-w-[1200px]">
            <div
                className="absolute -inset-3 translate-x-3 translate-y-3 border border-marketing-focus/20 bg-[#ece5d8]"
                aria-hidden="true"
            />

            <figure
                ref={parallaxRef}
                className="relative overflow-hidden border border-marketing-border/18 bg-marketing-surface p-1.5 shadow-[0_24px_65px_rgba(16,40,58,0.16)] sm:p-2"
            >
                <ZoomableImage
                    src="/images/marketing/dashboard.png"
                    alt="MiseLedger dashboard showing inventory value, low-stock alerts, purchase orders, receiving, stock counts, and recent inventory activity."
                    width={1894}
                    height={941}
                    loading="eager"
                    fetchPriority="high"
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
}: JourneyStepProps) {
    return (
        <article className="relative flex h-full flex-col">
            <div className="flex items-center gap-3">
                <span className="flex size-7 shrink-0 items-center justify-center rounded-full bg-marketing-accent text-xs font-bold text-marketing-accent-foreground">
                    {number}
                </span>

                <span className="flex size-9 shrink-0 items-center justify-center rounded-full bg-[#eef1ec] text-[#627367]">
                    <Icon className="size-4" aria-hidden="true" />
                </span>

                <div className="min-w-0">
                    <h3 className="font-serif text-lg leading-tight text-marketing-ink">
                        {title}
                    </h3>
                </div>
            </div>

            <div className="mt-5 flex flex-1 flex-col border border-marketing-border/15 bg-marketing-surface p-4 shadow-[0_9px_24px_rgba(16,40,58,0.08)] transition duration-300 hover:-translate-y-1 hover:shadow-[0_14px_30px_rgba(16,40,58,0.12)] motion-reduce:transform-none">
                {children}
            </div>
        </article>
    );
}

/** Render the complete public MiseLedger marketing landing page. */
export default function Welcome({ trialDays, plans }: WelcomeProps) {
    const { auth } = usePage().props;
    const isAuthenticated = Boolean(auth.user);
    const { ref: workflowRef, isVisible: workflowVisible } =
        useMarketingReveal<HTMLDivElement>();

    return (
        <>
            <Head title="MiseLedger | Restaurant inventory and purchasing software">
                <meta
                    name="description"
                    content="MiseLedger is restaurant and food-operation inventory and purchasing software: stock visibility, purchasing and receiving, counts and waste, recipe costing, and multi-location operations in one workspace."
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
                        <div className="mx-auto max-w-[1280px] px-5 py-14 text-center sm:px-8 sm:py-20 lg:px-12 lg:py-24">
                            <div className="marketing-enter mx-auto flex max-w-2xl flex-col items-center">
                                <p className="inline-flex items-center gap-2 border border-marketing-accent/25 bg-[#eef3ec] px-3 py-2 text-xs font-semibold text-[#315846]">
                                    <ChefHat
                                        className="size-4"
                                        aria-hidden="true"
                                    />
                                    Restaurant inventory and purchasing software
                                </p>

                                <h1 className="mt-7 max-w-[20ch] font-serif text-5xl leading-[0.96] font-semibold tracking-[-0.045em] text-balance text-marketing-ink sm:text-6xl lg:text-[4.6rem]">
                                    Know what you have before you buy more.
                                </h1>

                                <p className="mt-7 max-w-lg text-base leading-7 text-[#526674] sm:text-lg sm:leading-8">
                                    MiseLedger helps food teams stay on top of
                                    stock, review waste, understand recipe
                                    costs, and keep purchasing, receiving, and
                                    locations organized from one clear
                                    workspace.
                                </p>

                                <div className="mt-8">
                                    <HeroActions
                                        isAuthenticated={isAuthenticated}
                                    />
                                </div>
                            </div>

                            <div className="mt-14 lg:mt-16">
                                <HeroDashboardPreview />
                            </div>
                        </div>
                    </section>

                    <ProductProofStrip />

                    <ProblemSection />

                    <ProductStory
                        eyebrow="Inventory Control"
                        title="See what is on hand and where attention is needed"
                        description="Review stock on hand by location, catch low-stock items before they run out, and follow the movement history behind every quantity."
                        bullets={[
                            'Stock on hand by location and storage area',
                            'Low-stock items that need attention',
                            'Full stock movement history, never guessed',
                        ]}
                        imageSrc="/images/marketing/stock-on-hand.png"
                        imageAlt="MiseLedger stock on hand screen showing quantities, low-stock items, and location context for inventory items."
                        tone="canvas"
                    />

                    <ProductStory
                        eyebrow="Purchasing & Receiving"
                        title="Keep buying and receiving connected to inventory"
                        description="Create purchase orders with your suppliers, track approval status, and record what actually arrives so stock updates automatically."
                        bullets={[
                            'Purchase orders with supplier and pricing context',
                            'Approval status from draft to received',
                            'Goods receipts that update stock on hand',
                        ]}
                        imageSrc="/images/marketing/purchase-orders.png"
                        imageAlt="MiseLedger purchase orders screen showing supplier, status, and purchase order totals."
                        tone="muted"
                        reverse
                    />

                    <ProductStory
                        eyebrow="Cost & Waste Control"
                        title="Understand where inventory value is going"
                        description="Record waste with a clear reason, review stock-count variance, and see recipe costs so you know what is actually driving inventory value."
                        bullets={[
                            'Waste records with reasons, not write-offs',
                            'Stock-count variance against system quantities',
                            'Recipe cost built from your inventory items',
                        ]}
                        imageSrc="/images/marketing/waste.png"
                        imageAlt="MiseLedger waste screen showing recorded waste entries with quantities and reasons."
                        tone="canvas"
                    />

                    <section
                        id="how-it-works"
                        className="scroll-mt-24 border-b border-marketing-border/15 bg-marketing-canvas"
                    >
                        <div
                            ref={workflowRef}
                            className={`marketing-reveal mx-auto max-w-[1280px] px-5 py-16 sm:px-8 sm:py-20 lg:px-12 ${workflowVisible ? 'is-visible' : ''}`}
                        >
                            <div className="flex flex-col gap-4 border-b border-dashed border-[#a87a55]/55 pb-6 sm:flex-row sm:items-end sm:justify-between">
                                <div>
                                    <p className="text-xs font-bold tracking-[0.16em] text-[#6c7a72] uppercase">
                                        A connected operating story
                                    </p>
                                    <h2 className="mt-2 font-serif text-4xl tracking-[-0.035em] text-marketing-ink sm:text-5xl">
                                        From delivery to plate
                                    </h2>
                                </div>

                                <div className="flex max-w-md items-start gap-4 border-t border-dashed border-[#a87a55]/45 pt-4 sm:border-t-0 sm:border-l sm:pt-0 sm:pl-6">
                                    <span className="flex size-9 shrink-0 items-center justify-center rounded-full bg-marketing-accent/10 text-marketing-accent">
                                        <Eye
                                            className="size-4"
                                            aria-hidden="true"
                                        />
                                    </span>

                                    <div>
                                        <p className="font-serif text-base leading-tight font-semibold text-marketing-ink">
                                            Complete visibility. Better
                                            decisions.
                                        </p>
                                        <p className="mt-1 text-sm leading-6 text-[#60717c]">
                                            Follow stock from receiving through
                                            movement, counting, waste, and
                                            recipe costing without turning the
                                            day into spreadsheet detective work.
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <div className="mt-10 grid gap-8 md:grid-cols-2 xl:grid-cols-5 xl:gap-5">
                                <JourneyStep
                                    number={1}
                                    icon={PackageCheck}
                                    title="Receive"
                                >
                                    <div
                                        className="flex items-center justify-between gap-2 border-b border-marketing-border/12 pb-3"
                                        aria-hidden="true"
                                    >
                                        <p className="text-sm font-semibold text-marketing-ink">
                                            Receiving
                                        </p>
                                        <span className="inline-flex items-center gap-1 rounded-full bg-marketing-accent px-2 py-1 text-[10px] font-semibold text-marketing-accent-foreground">
                                            <Plus
                                                className="size-3"
                                                aria-hidden="true"
                                            />
                                            New Receiving
                                        </span>
                                    </div>
                                    <p className="mt-3 text-[10px] font-bold tracking-[0.12em] text-marketing-muted uppercase">
                                        Receiving note
                                    </p>
                                    <div className="mt-3 space-y-2 text-xs text-marketing-muted">
                                        <div className="flex justify-between">
                                            <span>Roma Tomatoes</span>
                                            <span>Logged</span>
                                        </div>
                                        <div className="flex justify-between">
                                            <span>Chicken Breast</span>
                                            <span>Logged</span>
                                        </div>
                                        <div className="flex justify-between">
                                            <span>Olive Oil</span>
                                            <span>Logged</span>
                                        </div>
                                    </div>
                                    <p className="mt-auto flex items-center gap-2 pt-4 text-xs font-semibold text-marketing-accent">
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
                                    title="Stock"
                                >
                                    <div
                                        className="flex items-center justify-between gap-2 border-b border-marketing-border/12 pb-3"
                                        aria-hidden="true"
                                    >
                                        <p className="text-sm font-semibold text-marketing-ink">
                                            Inventory
                                        </p>
                                        <span className="inline-flex items-center gap-1 rounded-full bg-marketing-accent px-2 py-1 text-[10px] font-semibold text-marketing-accent-foreground">
                                            <Plus
                                                className="size-3"
                                                aria-hidden="true"
                                            />
                                            New Item
                                        </span>
                                    </div>
                                    <p className="mt-3 text-[10px] font-bold tracking-[0.12em] text-marketing-muted uppercase">
                                        Stock on hand
                                    </p>
                                    <div className="mt-3 space-y-2 text-xs text-marketing-muted">
                                        <div className="flex justify-between">
                                            <span>Roma Tomatoes</span>
                                            <span>In stock</span>
                                        </div>
                                        <div className="flex justify-between">
                                            <span>Chicken Breast</span>
                                            <span>In stock</span>
                                        </div>
                                        <div className="flex justify-between">
                                            <span>Olive Oil</span>
                                            <span className="font-semibold text-[#a67a2e]">
                                                Low Stock
                                            </span>
                                        </div>
                                    </div>
                                    <p className="mt-auto pt-4 text-xs font-semibold text-marketing-accent">
                                        Updated after receiving
                                    </p>
                                </JourneyStep>

                                <JourneyStep
                                    number={3}
                                    icon={Truck}
                                    title="Move / Use"
                                >
                                    <div
                                        className="flex items-center justify-between gap-2 border-b border-marketing-border/12 pb-3"
                                        aria-hidden="true"
                                    >
                                        <p className="text-sm font-semibold text-marketing-ink">
                                            Stock Movement
                                        </p>
                                        <span className="inline-flex items-center gap-1 rounded-full bg-marketing-accent px-2 py-1 text-[10px] font-semibold text-marketing-accent-foreground">
                                            <Plus
                                                className="size-3"
                                                aria-hidden="true"
                                            />
                                            New Movement
                                        </span>
                                    </div>
                                    <p className="mt-3 text-[10px] font-bold tracking-[0.12em] text-marketing-muted uppercase">
                                        Transfer
                                    </p>
                                    <p className="mt-2 text-xs font-semibold text-marketing-ink">
                                        Main Kitchen → Branch A
                                    </p>
                                    <div className="mt-3 space-y-2 text-xs text-marketing-muted">
                                        <div className="flex justify-between">
                                            <span>Roma Tomatoes</span>
                                            <span className="font-semibold text-[#355f87]">
                                                Moved
                                            </span>
                                        </div>
                                        <div className="flex justify-between">
                                            <span>Chicken Breast</span>
                                            <span className="font-semibold text-[#355f87]">
                                                Moved
                                            </span>
                                        </div>
                                    </div>
                                    <p className="mt-auto pt-4 text-xs font-semibold text-[#355f87]">
                                        Shipped
                                    </p>
                                </JourneyStep>

                                <JourneyStep
                                    number={4}
                                    icon={ClipboardCheck}
                                    title="Count / Waste"
                                >
                                    <div
                                        className="flex items-center justify-between gap-2 border-b border-marketing-border/12 pb-3"
                                        aria-hidden="true"
                                    >
                                        <p className="text-sm font-semibold text-marketing-ink">
                                            Stock Count
                                        </p>
                                        <span className="inline-flex items-center gap-1 rounded-full bg-marketing-accent px-2 py-1 text-[10px] font-semibold text-marketing-accent-foreground">
                                            <Plus
                                                className="size-3"
                                                aria-hidden="true"
                                            />
                                            New Count
                                        </span>
                                    </div>
                                    <p className="mt-3 text-[10px] font-bold tracking-[0.12em] text-marketing-muted uppercase">
                                        Stock count
                                    </p>
                                    <div className="mt-3 space-y-2 text-xs text-marketing-muted">
                                        <div className="flex justify-between">
                                            <span>Counted</span>
                                            <span>Reviewed</span>
                                        </div>
                                        <div className="flex justify-between">
                                            <span>Variance</span>
                                            <span className="font-semibold text-[#a64d2e]">
                                                -0.5 kg
                                            </span>
                                        </div>
                                        <div className="flex justify-between">
                                            <span>Waste · Spoilage</span>
                                            <span>Logged</span>
                                        </div>
                                    </div>
                                    <p className="mt-auto pt-4 text-xs font-semibold text-[#a64d2e]">
                                        Recorded
                                    </p>
                                </JourneyStep>

                                <JourneyStep
                                    number={5}
                                    icon={Scale}
                                    title="Cost & Report"
                                >
                                    <p
                                        className="border-b border-marketing-border/12 pb-3 text-sm font-semibold text-marketing-ink"
                                        aria-hidden="true"
                                    >
                                        Recipe Costing
                                    </p>
                                    <p className="mt-3 text-[10px] font-bold tracking-[0.12em] text-marketing-muted uppercase">
                                        Recipe cost
                                    </p>
                                    <p className="mt-2 font-serif text-lg text-marketing-ink">
                                        Margherita Pizza
                                    </p>
                                    <p className="mt-auto pt-5 text-[10px] tracking-[0.1em] text-marketing-muted uppercase">
                                        Total cost
                                    </p>
                                    <p className="mt-1 font-serif text-lg font-semibold text-marketing-ink">
                                        Calculated automatically
                                    </p>
                                </JourneyStep>
                            </div>
                        </div>
                    </section>

                    <MultiLocationStory />

                    <AudienceRoleSection />

                    <AiAssistantSection />

                    <PricingSection trialDays={trialDays} plans={plans} />

                    <FaqSection />

                    <FinalCtaSection isAuthenticated={isAuthenticated} />
                </main>

                <MarketingFooter isAuthenticated={isAuthenticated} />
            </div>
        </>
    );
}
