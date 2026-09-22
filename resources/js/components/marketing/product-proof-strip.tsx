import { Boxes, ChefHat, ClipboardCheck, MapPin, Truck } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';

const PROOF_ITEMS: Array<{ label: string; icon: LucideIcon }> = [
    { label: 'Inventory visibility', icon: Boxes },
    { label: 'Purchasing & receiving', icon: Truck },
    { label: 'Counts & waste', icon: ClipboardCheck },
    { label: 'Recipe costing', icon: ChefHat },
    { label: 'Multi-location', icon: MapPin },
];

/** Render the truthful product-evidence strip that replaces customer-logo social proof. */
export function ProductProofStrip() {
    return (
        <section
            id="product"
            aria-label="What MiseLedger covers"
            className="scroll-mt-24 border-b border-marketing-border/15 bg-marketing-surface-muted"
        >
            <div className="mx-auto max-w-[1280px] px-5 sm:px-8 lg:px-12">
                <ul className="grid grid-cols-2 gap-x-4 gap-y-5 py-6 sm:flex sm:flex-wrap sm:items-center sm:justify-center sm:gap-x-10 sm:gap-y-4 sm:py-7">
                    {PROOF_ITEMS.map(({ label, icon: Icon }, index) => (
                        <li
                            key={label}
                            className={`flex items-center gap-2 text-xs font-semibold text-marketing-muted sm:border-l sm:border-marketing-border/15 sm:pl-10 sm:text-sm first:sm:border-l-0 first:sm:pl-0 ${
                                index === PROOF_ITEMS.length - 1
                                    ? 'col-span-2 justify-center'
                                    : ''
                            }`}
                        >
                            <Icon
                                className="size-4 shrink-0 text-marketing-accent"
                                aria-hidden="true"
                            />
                            <span>{label}</span>
                        </li>
                    ))}
                </ul>
            </div>
        </section>
    );
}
