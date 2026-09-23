import { Link } from '@inertiajs/react';
import { Check } from 'lucide-react';
import AppLogoIcon from '@/components/app-logo-icon';
import { home } from '@/routes';
import type { AuthLayoutProps } from '@/types/ui';

const BRAND_STATEMENT =
    'Clear inventory. Connected purchasing. Better control across your food operation.';

const BENEFITS = [
    'Know what is on hand',
    'Keep purchasing and receiving connected',
    'Track counts, waste, cost, and locations',
];

export default function AuthSplitLayout({
    children,
    title,
    description,
}: AuthLayoutProps) {
    return (
        <div className="min-h-svh lg:grid lg:grid-cols-[44fr_56fr]">
            <div
                className={
                    // Scoped to this decorative panel only (never the form
                    // side) so it can reuse the marketing dark-surface
                    // tokens without pulling the light-only marketing theme
                    // onto the interactive, dark-mode-aware auth form.
                    'marketing-theme relative hidden flex-col justify-between gap-10 overflow-hidden bg-marketing-dark p-10 text-marketing-dark-foreground lg:flex xl:p-14'
                }
            >
                <div
                    aria-hidden="true"
                    className="pointer-events-none absolute inset-0 opacity-[0.06]"
                    style={{
                        backgroundImage:
                            'linear-gradient(to right, currentColor 1px, transparent 1px), linear-gradient(to bottom, currentColor 1px, transparent 1px)',
                        backgroundSize: '42px 42px',
                    }}
                />

                <Link
                    href={home()}
                    className="relative flex items-center gap-2 text-lg font-medium"
                >
                    <AppLogoIcon
                        className="size-8 fill-current text-marketing-dark-foreground"
                        aria-hidden="true"
                    />
                    MiseLedger
                </Link>

                <div className="relative flex flex-1 flex-col items-center justify-center gap-8">
                    <p className="max-w-sm text-2xl leading-snug font-medium text-balance">
                        {BRAND_STATEMENT}
                    </p>

                    <ul className="flex flex-col gap-3 text-sm text-marketing-dark-muted">
                        {BENEFITS.map((benefit) => (
                            <li
                                key={benefit}
                                className="flex items-start gap-3"
                            >
                                <Check
                                    className="mt-0.5 size-4 shrink-0 text-marketing-focus"
                                    aria-hidden="true"
                                />
                                <span>{benefit}</span>
                            </li>
                        ))}
                    </ul>
                </div>

                <span className="sr-only relative">MiseLedger</span>
            </div>

            <div className="flex min-h-svh flex-col items-center justify-center gap-8 bg-background px-6 py-10 sm:px-10">
                <Link
                    href={home()}
                    className="flex items-center gap-2 lg:hidden"
                >
                    <AppLogoIcon
                        className="size-9 fill-current text-foreground dark:text-white"
                        aria-hidden="true"
                    />
                    <span className="font-medium">MiseLedger</span>
                </Link>

                <div className="flex w-full max-w-[440px] min-w-0 flex-col gap-6">
                    <div className="flex flex-col gap-2 text-center lg:text-left">
                        <h1 className="text-xl font-semibold">{title}</h1>
                        <p className="text-sm text-balance text-muted-foreground">
                            {description}
                        </p>
                    </div>
                    {children}
                </div>
            </div>
        </div>
    );
}
