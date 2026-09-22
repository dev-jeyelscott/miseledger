import { Link } from '@inertiajs/react';
import { Check } from 'lucide-react';

import { useMarketingReveal } from '@/lib/marketing-motion';
import { register } from '@/routes';

export type WelcomePlan = {
    code: string;
    name: string;
    features: string[];
    limits: Record<string, number | null>;
};

const FEATURE_LABELS: Record<string, string> = {
    purchasing: 'Purchasing',
    recipes: 'Recipes & costing',
    'reports.export': 'Report exports',
    'locations.multi': 'Multi-location operations',
    'ai.assistant': 'AI Assistant',
};

const LIMIT_LABELS: Record<string, string> = {
    seats: 'Team members',
    locations: 'Locations',
    inventory_items: 'Inventory items',
};

const LIMIT_ORDER = ['seats', 'locations', 'inventory_items'];

const PLAN_FIT_STATEMENTS: Record<string, string> = {
    starter:
        'A single-location kitchen getting stock, purchasing basics, and waste tracking under control.',
    growth: 'A growing operation that needs purchasing, recipe costing, and more than one location.',
    business:
        'A multi-location business that needs every operational feature without seat or item caps.',
};

/** Render one limit as its display label and value, with an explicit `null` shown as unlimited. */
function formatLimit(key: string, value: number | null): string {
    const label = LIMIT_LABELS[key] ?? key;

    return value === null ? `${label}: Unlimited` : `${label}: ${value}`;
}

/** Render one plan card with its safe, backend-sourced features and limits. */
function PlanCard({
    plan,
    emphasize,
}: {
    plan: WelcomePlan;
    emphasize: boolean;
}) {
    return (
        <article
            className={`flex flex-col rounded-xl border bg-marketing-surface p-6 transition duration-200 ease-out hover:-translate-y-1 hover:scale-[1.02] hover:shadow-[0_24px_56px_rgba(16,40,58,0.16)] sm:p-7 ${
                emphasize
                    ? 'border-marketing-accent shadow-[0_18px_44px_rgba(15,90,67,0.16)]'
                    : 'border-marketing-border/22 shadow-[0_9px_24px_rgba(16,40,58,0.06)]'
            }`}
        >
            <h3 className="font-serif text-2xl text-marketing-ink">
                {plan.name}
            </h3>
            <p className="mt-2 min-h-12 text-sm leading-6 text-marketing-muted">
                {PLAN_FIT_STATEMENTS[plan.code] ??
                    'Available from your organization billing settings.'}
            </p>

            <p className="mt-5 text-[11px] font-bold tracking-[0.12em] text-marketing-muted uppercase">
                Includes core inventory, stock on hand, receiving, and waste
                tracking
            </p>

            {plan.features.length > 0 && (
                <ul className="mt-3 space-y-2 text-sm leading-6 text-marketing-ink">
                    {plan.features.map((feature) => (
                        <li key={feature} className="flex items-start gap-2">
                            <Check
                                className="mt-0.5 size-4 shrink-0 text-marketing-accent"
                                aria-hidden="true"
                            />
                            <span>{FEATURE_LABELS[feature] ?? feature}</span>
                        </li>
                    ))}
                </ul>
            )}

            <div className="mt-auto space-y-1.5 border-t border-marketing-border/15 pt-5 text-xs text-marketing-muted">
                {LIMIT_ORDER.filter((key) =>
                    Object.hasOwn(plan.limits, key),
                ).map((key) => (
                    <p key={key}>{formatLimit(key, plan.limits[key])}</p>
                ))}
            </div>

            <div className="mt-7">
                <Link
                    href={register()}
                    className={`inline-flex min-h-11 w-full items-center justify-center border px-5 text-sm font-semibold transition focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-marketing-focus ${
                        emphasize
                            ? 'border-marketing-accent bg-marketing-accent text-marketing-accent-foreground hover:bg-marketing-accent-hover'
                            : 'border-marketing-border/35 text-marketing-ink hover:border-marketing-border hover:bg-marketing-surface-muted'
                    }`}
                >
                    Start free
                </Link>
            </div>
        </article>
    );
}

/** Render the pricing and trial section using only backend-sourced plan data. */
export function PricingSection({
    trialDays,
    plans,
}: {
    trialDays: number | null;
    plans: WelcomePlan[];
}) {
    const { ref, isVisible } = useMarketingReveal<HTMLDivElement>();

    return (
        <section
            id="pricing"
            className="scroll-mt-24 border-b border-marketing-border/15 bg-marketing-surface-muted"
        >
            <div
                ref={ref}
                className={`marketing-reveal mx-auto max-w-[1280px] px-5 py-16 sm:px-8 sm:py-20 lg:px-12 lg:py-28 ${isVisible ? 'is-visible' : ''}`}
            >
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
                    <div className="mt-10 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                        {plans.map((plan) => (
                            <PlanCard
                                key={plan.code}
                                plan={plan}
                                emphasize={plan.code === 'growth'}
                            />
                        ))}
                    </div>
                )}
            </div>
        </section>
    );
}
