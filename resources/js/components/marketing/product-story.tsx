import type { ReactNode } from 'react';

import { ZoomableImage } from '@/components/marketing/zoomable-image';
import { useMarketingReveal } from '@/lib/marketing-motion';

type ProductStoryProps = {
    eyebrow: string;
    title: string;
    description: string;
    bullets: string[];
    imageSrc: string;
    imageAlt: string;
    reverse?: boolean;
    tone?: 'canvas' | 'muted';
    children?: ReactNode;
};

/**
 * Render one alternating copy-plus-real-screenshot product story used for
 * the Inventory Control, Purchasing & Receiving, and Cost & Waste Control
 * sections. `reverse` flips the desktop column order; source order (copy
 * before image) is preserved for logical reading order and mobile stacking.
 */
export function ProductStory({
    eyebrow,
    title,
    description,
    bullets,
    imageSrc,
    imageAlt,
    reverse = false,
    tone = 'canvas',
    children,
}: ProductStoryProps) {
    const { ref, isVisible } = useMarketingReveal<HTMLDivElement>();

    return (
        <section
            className={`border-b border-marketing-border/15 ${
                tone === 'muted'
                    ? 'bg-marketing-surface-muted'
                    : 'bg-marketing-canvas'
            }`}
        >
            <div
                ref={ref}
                className={`marketing-reveal mx-auto grid max-w-[1280px] items-center gap-10 px-5 py-16 sm:px-8 sm:py-20 ${reverse ? 'lg:grid-cols-[1.28fr_0.72fr]' : 'lg:grid-cols-[0.72fr_1.28fr]'} lg:gap-14 lg:px-12 lg:py-28 ${isVisible ? 'is-visible' : ''}`}
            >
                <div className={reverse ? 'lg:order-2' : ''}>
                    <p className="text-xs font-bold tracking-[0.16em] text-marketing-muted uppercase">
                        {eyebrow}
                    </p>
                    <h2 className="mt-3 font-serif text-4xl leading-tight tracking-[-0.035em] text-marketing-ink sm:text-5xl">
                        {title}
                    </h2>
                    <p className="mt-4 max-w-sm text-base leading-7 text-marketing-muted">
                        {description}
                    </p>
                    <ul className="mt-6 space-y-2.5 text-sm leading-6 text-[#526674]">
                        {bullets.map((bullet) => (
                            <li key={bullet} className="flex gap-2.5">
                                <span
                                    className="mt-2 size-1.5 shrink-0 rounded-full bg-marketing-accent"
                                    aria-hidden="true"
                                />
                                <span>{bullet}</span>
                            </li>
                        ))}
                    </ul>
                    {children}
                </div>

                <div className={reverse ? 'lg:order-1' : ''}>
                    <figure className="overflow-hidden rounded-xl border border-marketing-border/25 bg-marketing-surface p-2 shadow-[0_22px_56px_rgba(16,40,58,0.14)] sm:p-2.5">
                        <ZoomableImage
                            src={imageSrc}
                            alt={imageAlt}
                            width={1918}
                            height={943}
                            className="block h-auto w-full rounded-lg border border-marketing-border/15 bg-marketing-surface"
                        />
                    </figure>
                </div>
            </div>
        </section>
    );
}
