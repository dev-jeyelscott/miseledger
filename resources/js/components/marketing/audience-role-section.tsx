import {
    ChefHat,
    ClipboardCheck,
    ShoppingCart,
    Store,
    Users,
    Utensils,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';

import { useMarketingReveal } from '@/lib/marketing-motion';

type Audience = {
    icon: LucideIcon;
    title: string;
    description: string;
};

type RoleOutcome = {
    icon: LucideIcon;
    title: string;
    description: string;
};

const BUSINESS_TYPES: Audience[] = [
    {
        icon: Utensils,
        title: 'Restaurants',
        description:
            'Keep stock, purchasing, and recipe costs organized for one busy kitchen.',
    },
    {
        icon: ChefHat,
        title: 'Cafés & bakeries',
        description:
            'Track ingredient stock and waste for fast-moving, recipe-driven menus.',
    },
    {
        icon: Store,
        title: 'Multi-location food operations',
        description:
            'Give every location its own stock context under one connected organization.',
    },
];

const ROLE_OUTCOMES: RoleOutcome[] = [
    {
        icon: Users,
        title: 'Owner / Manager',
        description:
            'See stock, exceptions, cost, and reports across locations without chasing updates.',
    },
    {
        icon: ClipboardCheck,
        title: 'Inventory staff',
        description:
            'Receive goods, run counts, record transfers and waste, and check stock on hand.',
    },
    {
        icon: ShoppingCart,
        title: 'Purchasing',
        description:
            'Manage suppliers, purchase orders, and receiving status with visible history.',
    },
];

/** Render the business-type and team-role audience section at `#for-teams`. */
export function AudienceRoleSection() {
    const { ref, isVisible } = useMarketingReveal<HTMLDivElement>();

    return (
        <section
            id="for-teams"
            className="scroll-mt-24 border-b border-marketing-border/15 bg-marketing-canvas"
        >
            <div
                ref={ref}
                className={`marketing-reveal mx-auto max-w-[1280px] px-5 py-16 sm:px-8 sm:py-20 lg:px-12 lg:py-28 ${isVisible ? 'is-visible' : ''}`}
            >
                <div className="max-w-2xl">
                    <p className="text-xs font-bold tracking-[0.16em] text-marketing-muted uppercase">
                        For your team
                    </p>
                    <h2 className="mt-3 font-serif text-4xl tracking-[-0.035em] text-marketing-ink sm:text-5xl">
                        Built for food operations and the people who run them
                    </h2>
                </div>

                <div className="mt-10 grid gap-4 sm:grid-cols-3">
                    {BUSINESS_TYPES.map(
                        ({ icon: Icon, title, description }) => (
                            <article
                                key={title}
                                className="rounded-xl border border-marketing-border/22 bg-marketing-surface p-6 shadow-[0_9px_24px_rgba(16,40,58,0.06)]"
                            >
                                <Icon
                                    className="size-6 stroke-[1.4] text-marketing-accent"
                                    aria-hidden="true"
                                />
                                <h3 className="mt-4 font-serif text-lg text-marketing-ink">
                                    {title}
                                </h3>
                                <p className="mt-2 text-sm leading-6 text-marketing-muted">
                                    {description}
                                </p>
                            </article>
                        ),
                    )}
                </div>

                <div className="mt-6 border-t border-dashed border-[#a87a55]/45 pt-10">
                    <p className="text-xs font-bold tracking-[0.16em] text-marketing-muted uppercase">
                        By role
                    </p>

                    <div className="mt-6 grid gap-4 sm:grid-cols-3">
                        {ROLE_OUTCOMES.map(
                            ({ icon: Icon, title, description }) => (
                                <article
                                    key={title}
                                    className="border-t border-marketing-border/15 pt-4"
                                >
                                    <Icon
                                        className="size-5 text-[#526a5f]"
                                        aria-hidden="true"
                                    />
                                    <h3 className="mt-3 text-sm font-semibold text-marketing-ink">
                                        {title}
                                    </h3>
                                    <p className="mt-2 text-sm leading-6 text-marketing-muted">
                                        {description}
                                    </p>
                                </article>
                            ),
                        )}
                    </div>
                </div>
            </div>
        </section>
    );
}
