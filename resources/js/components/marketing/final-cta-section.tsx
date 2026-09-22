import { Link } from '@inertiajs/react';
import { ArrowRight } from 'lucide-react';

import { dashboard, register } from '@/routes';

/** Render the full-width dark final call-to-action with a single dominant action. */
export function FinalCtaSection({
    isAuthenticated,
}: {
    isAuthenticated: boolean;
}) {
    return (
        <section className="relative overflow-hidden bg-marketing-dark text-marketing-dark-foreground">
            <img
                src="/images/marketing/dashboard.png"
                alt=""
                aria-hidden="true"
                loading="lazy"
                decoding="async"
                className="absolute inset-0 h-full w-full scale-110 object-cover opacity-[0.14] blur-[1px]"
            />
            <div className="absolute inset-0 bg-gradient-to-t from-marketing-dark via-marketing-dark/95 to-marketing-dark/80" />

            <div className="relative mx-auto max-w-[1280px] px-5 py-16 text-center sm:px-8 sm:py-24 lg:px-12">
                <h2 className="mx-auto max-w-[22ch] font-serif text-4xl leading-tight tracking-[-0.035em] sm:text-5xl">
                    Know what you have before you buy more.
                </h2>
                <p className="mx-auto mt-5 max-w-xl text-base leading-7 text-marketing-dark-muted">
                    Start free and see your stock, purchasing, and recipe costs
                    in one organized workspace.
                </p>

                <div className="mt-9 flex justify-center">
                    <Link
                        href={isAuthenticated ? dashboard() : register()}
                        className="inline-flex min-h-12 items-center justify-center gap-2 border border-[#2d7b5f] bg-[#2d7b5f] px-8 text-sm font-semibold text-marketing-accent-foreground transition hover:border-[#3d8c6f] hover:bg-[#3d8c6f] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-marketing-focus"
                    >
                        {isAuthenticated ? 'Open dashboard' : 'Start free'}
                        <ArrowRight className="size-4" aria-hidden="true" />
                    </Link>
                </div>
            </div>
        </section>
    );
}
