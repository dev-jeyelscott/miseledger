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
        <li className="flex shrink-0 items-center gap-2 text-xs font-semibold text-marketing-muted sm:border-l sm:border-marketing-border/15 sm:pl-10 sm:text-sm sm:first:border-l-0 sm:first:pl-0">
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
            className="scroll-mt-24 border-b border-marketing-border/15 bg-marketing-surface-muted"
        >
            <div className="mx-auto max-w-[1280px] sm:px-8 lg:px-12">
                <div
                    className="marketing-marquee overflow-hidden py-6 sm:hidden"
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

                <ul className="sr-only py-7 sm:not-sr-only sm:flex sm:flex-wrap sm:items-center sm:justify-center sm:gap-x-10 sm:gap-y-4">
                    {PROOF_ITEMS.map(({ label, icon }) => (
                        <ProofItem key={label} label={label} icon={icon} />
                    ))}
                </ul>
            </div>
        </section>
    );
}
