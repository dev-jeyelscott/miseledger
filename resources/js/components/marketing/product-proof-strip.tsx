import { Boxes, ChefHat, ClipboardCheck, MapPin, Truck } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';

const PROOF_ITEMS: Array<{ label: string; icon: LucideIcon }> = [
    { label: 'Inventory visibility', icon: Boxes },
    { label: 'Purchasing & receiving', icon: Truck },
    { label: 'Counts & waste', icon: ClipboardCheck },
    { label: 'Recipe costing', icon: ChefHat },
    { label: 'Multi-location', icon: MapPin },
];

/** Render one proof item; reused by both the marquee track and the wrapped list. */
function ProofItem({ label, icon: Icon }: { label: string; icon: LucideIcon }) {
    return (
        <li className="flex shrink-0 items-center gap-2 text-xs font-semibold text-marketing-muted lg:border-l lg:border-marketing-border/15 lg:pl-10 lg:text-sm lg:first:border-l-0 lg:first:pl-0">
            <Icon
                className="size-4 shrink-0 text-marketing-accent"
                aria-hidden="true"
            />
            <span>{label}</span>
        </li>
    );
}

/** Render the truthful product-evidence strip that replaces customer-logo social proof. */
export function ProductProofStrip() {
    return (
        <section
            id="product"
            aria-label="What MiseLedger covers"
            className="scroll-mt-24 bg-marketing-surface-muted"
        >
            <div className="mx-auto max-w-[1280px] border-b border-marketing-border/15">
                <div
                    className="marketing-marquee overflow-hidden py-6 lg:hidden"
                    aria-hidden="true"
                >
                    <ul className="marketing-marquee-track flex w-max items-center gap-8">
                        {[...PROOF_ITEMS, ...PROOF_ITEMS].map(
                            ({ label, icon }, index) => (
                                <ProofItem
                                    key={`${label}-${index}`}
                                    label={label}
                                    icon={icon}
                                />
                            ),
                        )}
                    </ul>
                </div>

                <ul className="sr-only lg:not-sr-only lg:flex lg:flex-wrap lg:items-center lg:justify-center lg:gap-x-10 lg:gap-y-4 lg:px-12 lg:py-6">
                    {PROOF_ITEMS.map(({ label, icon }) => (
                        <ProofItem key={label} label={label} icon={icon} />
                    ))}
                </ul>
            </div>
        </section>
    );
}
