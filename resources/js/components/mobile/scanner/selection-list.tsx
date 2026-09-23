import type { ScannedItem } from '@/types/mobile';

type SelectionListProps = {
    items: ScannedItem[];
    onSelect: (item: ScannedItem) => void;
};

/**
 * Tappable candidate list used identically whether it was reached from a
 * loose camera-scan fallback or a manual search with multiple hits
 * (decision #1.A).
 */
function SelectionList({ items, onSelect }: SelectionListProps) {
    return (
        <ul className="space-y-2">
            {items.map((item) => (
                <li key={item.inventoryItemId}>
                    <button
                        type="button"
                        onClick={() => onSelect(item)}
                        className="flex min-h-[44px] w-full items-center justify-between rounded-md border border-input px-4 py-3 text-left hover:bg-accent"
                    >
                        <span className="min-w-0">
                            <span className="block truncate font-medium">
                                {item.name}
                            </span>
                            <span className="block text-xs text-muted-foreground">
                                {item.sku ?? 'No SKU'} ·{' '}
                                {item.matchedUnit.symbol}
                            </span>
                        </span>
                    </button>
                </li>
            ))}
        </ul>
    );
}

export { SelectionList };
