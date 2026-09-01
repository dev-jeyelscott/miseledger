import { Link } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';
import type { ComponentProps, ReactNode } from 'react';

import { cn } from '@/lib/utils';

type InertiaLinkHref = ComponentProps<typeof Link>['href'];

type DashboardMetricCardProps = {
    title: string;
    value: ReactNode;
    description: string;
    icon: LucideIcon;
    href?: InertiaLinkHref;
    tone?: 'emerald' | 'amber' | 'blue' | 'teal' | 'violet';
};

const toneClasses = {
    emerald:
        'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300',
    amber: 'bg-amber-50 text-amber-700 dark:bg-amber-950/40 dark:text-amber-300',
    blue: 'bg-blue-50 text-blue-700 dark:bg-blue-950/40 dark:text-blue-300',
    teal: 'bg-teal-50 text-teal-700 dark:bg-teal-950/40 dark:text-teal-300',
    violet: 'bg-violet-50 text-violet-700 dark:bg-violet-950/40 dark:text-violet-300',
} satisfies Record<NonNullable<DashboardMetricCardProps['tone']>, string>;

/** Render one aligned operational metric with an optional permission-safe drill-down route. */
export function DashboardMetricCard({
    title,
    value,
    description,
    icon: Icon,
    href,
    tone = 'emerald',
}: DashboardMetricCardProps) {
    const cardClassName =
        'flex h-full min-h-32 flex-col rounded-xl bg-card p-4 shadow-sm';

    const content = (
        <>
            <div className="flex items-start justify-between gap-4">
                <div className="min-w-0">
                    <p className="text-sm font-medium text-muted-foreground">
                        {title}
                    </p>

                    <div className="mt-2 text-2xl font-semibold tracking-tight tabular-nums">
                        {value}
                    </div>
                </div>

                <div
                    className={cn(
                        'flex size-10 shrink-0 items-center justify-center rounded-full transition-colors',
                        toneClasses[tone],
                    )}
                    aria-hidden="true"
                >
                    <Icon className="size-5" />
                </div>
            </div>

            <p className="mt-auto pt-3 text-xs leading-5 text-muted-foreground">
                {description}
            </p>
        </>
    );

    if (href !== undefined) {
        return (
            <Link
                href={href}
                aria-label={`View ${title}`}
                className={cn(
                    cardClassName,
                    'group transition-colors hover:bg-muted/30 focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none',
                )}
            >
                {content}
            </Link>
        );
    }

    return <section className={cardClassName}>{content}</section>;
}
