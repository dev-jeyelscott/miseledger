import { Head, Link, usePage } from '@inertiajs/react';
import {
    ArrowRight,
    Boxes,
    ChefHat,
    ClipboardCheck,
    PackageCheck,
    Scale,
    Trash2,
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
import { useHeroParallax, useMarketingReveal } from '@/lib/marketing-motion';
import { dashboard, login, register } from '@/routes';

type JourneyStepProps = {
    number: number;
    icon: LucideIcon;
    title: string;
    children: ReactNode;
    showConnector?: boolean;
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

/** Render the real MiseLedger dashboard as the primary hero product visual with a small bounded parallax. */
function HeroDashboardPreview() {
    const parallaxRef = useHeroParallax<HTMLDivElement>();

    return (
        <div className="marketing-enter relative mx-auto w-full max-w-[920px] lg:w-[112%] lg:max-w-none xl:w-[118%]">
            <div
                className="absolute -inset-3 translate-x-3 translate-y-3 border border-marketing-focus/20 bg-[#ece5d8]"
                aria-hidden="true"
            />

            <figure
                ref={parallaxRef}
                className="relative overflow-hidden border border-marketing-border/18 bg-marketing-surface p-1.5 shadow-[0_24px_65px_rgba(16,40,58,0.16)] sm:p-2"
            >
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
                        <div className="mx-auto grid max-w-[1440px] gap-12 px-5 py-14 sm:px-8 sm:py-20 lg:grid-cols-[0.72fr_1.28fr] lg:items-center lg:gap-10 lg:px-12 lg:py-24 xl:gap-14">
                            <div className="marketing-enter max-w-xl">
                                <p className="inline-flex items-center gap-2 border border-marketing-accent/25 bg-[#eef3ec] px-3 py-2 text-xs font-semibold text-[#315846]">
                                    <ChefHat
                                        className="size-4"
                                        aria-hidden="true"
                                    />
                                    Restaurant inventory and purchasing software
                                </p>

                                <h1 className="mt-7 max-w-[10ch] font-serif text-5xl leading-[0.96] font-semibold tracking-[-0.045em] text-balance text-marketing-ink sm:text-6xl lg:text-[4.6rem]">
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
                                    <a
                                        href="#how-it-works"
                                        className="mt-4 inline-flex text-sm font-semibold text-marketing-ink underline decoration-marketing-accent/50 underline-offset-4 hover:decoration-marketing-accent focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-marketing-focus"
                                    >
                                        See how it works
                                    </a>
                                </div>
                            </div>

                            <HeroDashboardPreview />
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
                            className={`marketing-reveal mx-auto max-w-[1440px] px-5 py-16 sm:px-8 sm:py-20 lg:px-12 ${workflowVisible ? 'is-visible' : ''}`}
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
