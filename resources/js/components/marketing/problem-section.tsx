import { Boxes, Network, ShoppingCart, Trash2 } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';

type Problem = {
    icon: LucideIcon;
    title: string;
    description: string;
};

const PROBLEMS: Problem[] = [
    {
        icon: Boxes,
        title: 'Stock uncertainty',
        description:
            'Know what is actually on hand before buying again, instead of guessing from memory or a walk-through.',
    },
    {
        icon: ShoppingCart,
        title: 'Over-ordering and shortages',
        description:
            'Use current stock and low-stock context when purchasing so orders match what operations really need.',
    },
    {
        icon: Trash2,
        title: 'Waste and unexplained variance',
        description:
            'Record counts and waste with clear reasons so discrepancies can be reviewed instead of written off.',
    },
    {
        icon: Network,
        title: 'Disconnected operations',
        description:
            'Connect purchasing, receiving, inventory, locations, and costing in one operational system.',
    },
];

/** Render one editorial problem-to-outcome card. */
function ProblemCard({ icon: Icon, title, description }: Problem) {
    return (
        <article className="rounded-xl border border-marketing-border/22 bg-marketing-surface p-6 shadow-[0_9px_24px_rgba(16,40,58,0.06)] sm:p-7">
            <Icon
                className="size-6 stroke-[1.4] text-marketing-accent"
                aria-hidden="true"
            />
            <h3 className="mt-4 font-serif text-xl leading-tight text-marketing-ink">
                {title}
            </h3>
            <p className="mt-3 text-sm leading-6 text-marketing-muted">
                {description}
            </p>
        </article>
    );
}

/** Render the locked 2x2 operational-problems block that frames the product stories below it. */
export function ProblemSection() {
    return (
        <section
            aria-labelledby="operational-problems-heading"
            className="border-b border-marketing-border/15 bg-marketing-canvas"
        >
            <div className="mx-auto max-w-[1280px] px-5 py-16 sm:px-8 sm:py-20 lg:px-12 lg:py-28">
                <div className="flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <p className="text-xs font-bold tracking-[0.16em] text-marketing-muted uppercase">
                            The challenge
                        </p>
                        <h2
                            id="operational-problems-heading"
                            className="mt-2 max-w-[20ch] font-serif text-4xl tracking-[-0.035em] text-marketing-ink sm:text-5xl"
                        >
                            Why inventory gets hard to control.
                        </h2>
                    </div>

                    <p className="max-w-md text-sm leading-6 text-marketing-muted">
                        Food operations are complex. Between fluctuating demand,
                        multiple suppliers, and multiple locations, it's easy to
                        lose sight of what you have and what you really need.
                    </p>
                </div>

                <div className="mt-10 grid gap-4 sm:grid-cols-2">
                    {PROBLEMS.map((problem) => (
                        <ProblemCard key={problem.title} {...problem} />
                    ))}
                </div>
            </div>
        </section>
    );
}
