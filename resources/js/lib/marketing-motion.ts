import { useEffect, useRef, useState } from 'react';

/**
 * One-time, reduced-motion-safe scroll reveal. Under
 * `prefers-reduced-motion: reduce` the element is already visible via CSS
 * (see `.marketing-reveal` in app.css), so no observer is registered.
 */
export function useMarketingReveal<T extends HTMLElement>() {
    const ref = useRef<T | null>(null);
    const [isVisible, setIsVisible] = useState(
        () => window.matchMedia('(prefers-reduced-motion: reduce)').matches,
    );

    useEffect(() => {
        const node = ref.current;

        if (node === null || isVisible) {
            return;
        }

        const observer = new IntersectionObserver(
            ([entry]) => {
                if (entry.isIntersecting) {
                    setIsVisible(true);
                    observer.disconnect();
                }
            },
            { threshold: 0.15, rootMargin: '0px 0px -80px 0px' },
        );

        observer.observe(node);

        return () => observer.disconnect();
    }, [isVisible]);

    return { ref, isVisible };
}

/**
 * Small, bounded passive-scroll parallax for a single hero visual. Disabled
 * entirely under `prefers-reduced-motion: reduce`. Capped to a few pixels
 * of translation so it never competes with reading or layout stability.
 */
export function useHeroParallax<T extends HTMLElement>(maxOffsetPx = 18) {
    const ref = useRef<T | null>(null);

    useEffect(() => {
        const node = ref.current;

        if (
            node === null ||
            window.matchMedia('(prefers-reduced-motion: reduce)').matches
        ) {
            return;
        }

        let frame = 0;

        const update = (): void => {
            frame = 0;
            const rect = node.getBoundingClientRect();
            const viewport = window.innerHeight || 0;
            const progress = Math.min(
                Math.max((viewport - rect.top) / (viewport + rect.height), 0),
                1,
            );
            const offset = (progress - 0.5) * 2 * maxOffsetPx;
            node.style.transform = `translateY(${offset.toFixed(2)}px)`;
        };

        const onScroll = (): void => {
            if (frame === 0) {
                frame = requestAnimationFrame(update);
            }
        };

        update();
        window.addEventListener('scroll', onScroll, { passive: true });

        return () => {
            window.removeEventListener('scroll', onScroll);

            if (frame !== 0) {
                cancelAnimationFrame(frame);
            }
        };
    }, [maxOffsetPx]);

    return ref;
}
