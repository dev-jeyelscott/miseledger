import { ZoomableImage } from '@/components/marketing/zoomable-image';
import { useMarketingReveal } from '@/lib/marketing-motion';

const HIGHLIGHTS = [
    'Locations and storage areas organized under one organization',
    'Stock on hand tracked per location',
    'Transfers move stock between locations with a clear record',
];

/** Render the large dark multi-location narrative band using a real locations screenshot. */
export function MultiLocationStory() {
    const { ref, isVisible } = useMarketingReveal<HTMLDivElement>();

    return (
        <section className="bg-marketing-dark text-marketing-dark-foreground">
            <div
                ref={ref}
                className={`marketing-reveal mx-auto grid max-w-[1280px] items-center gap-12 px-5 py-16 sm:px-8 sm:py-24 lg:grid-cols-[0.72fr_1.28fr] lg:gap-16 lg:px-12 lg:py-32 ${isVisible ? 'is-visible' : ''}`}
            >
                <div>
                    <p className="text-xs font-bold tracking-[0.16em] text-[#d6a06e] uppercase">
                        Multi-location operations
                    </p>
                    <h2 className="mt-3 max-w-[22ch] font-serif text-4xl leading-tight tracking-[-0.035em] sm:text-5xl lg:text-6xl">
                        One inventory picture across every location.
                    </h2>
                    <p className="mt-5 max-w-md text-base leading-7 text-marketing-dark-muted">
                        Organize kitchens, outlets, and storage areas under one
                        organization and see stock on hand, transfers, and
                        location context without switching between disconnected
                        spreadsheets.
                    </p>
                    <ul className="mt-7 space-y-2.5 text-sm leading-6 text-marketing-dark-muted">
                        {HIGHLIGHTS.map((highlight) => (
                            <li key={highlight} className="flex gap-2.5">
                                <span
                                    className="mt-2 size-1.5 shrink-0 rounded-full bg-[#d6a06e]"
                                    aria-hidden="true"
                                />
                                <span>{highlight}</span>
                            </li>
                        ))}
                    </ul>
                </div>

                <figure className="overflow-hidden rounded-xl border border-white/15 bg-marketing-dark p-2 shadow-[0_28px_70px_rgba(0,0,0,0.45)] sm:p-2.5">
                    <ZoomableImage
                        src="/images/marketing/location.png"
                        alt="MiseLedger locations screen showing kitchens, outlets, storage areas, and stock context across an organization."
                        width={1918}
                        height={943}
                        className="block h-auto w-full rounded-lg border border-white/10"
                    />
                </figure>
            </div>
        </section>
    );
}
