import { useHttp } from '@inertiajs/react';
import { Camera, Search as SearchIcon } from 'lucide-react';
import { useEffect, useState } from 'react';
import type { FormEvent } from 'react';

import { EmptyState } from '@/components/empty-state';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { SearchInput } from '@/components/ui/search-input';
import {
    Sheet,
    SheetContent,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import mobileScan from '@/routes/mobile/scan';
import type { ItemSearchResponse, ScannedItem } from '@/types/mobile';

import { SelectionList } from './selection-list';

type ManualEntrySheetProps = {
    /** `inline` replaces the viewfinder when camera permission is denied; `sheet` is the toggled overlay. */
    variant: 'sheet' | 'inline';
    open?: boolean;
    onOpenChange?: (open: boolean) => void;
    onSelect: (item: ScannedItem) => void;
    onEnableCamera?: () => void;
};

/** Manual name/SKU/barcode search fallback (decisions #27 and #32.A). */
function ManualEntrySheet({
    variant,
    open = true,
    onOpenChange,
    onSelect,
    onEnableCamera,
}: ManualEntrySheetProps) {
    const [query, setQuery] = useState('');
    const [barcode, setBarcode] = useState('');
    const [hasSearched, setHasSearched] = useState(false);
    const search = useHttp<Record<string, never>, ItemSearchResponse>({});

    useEffect(() => {
        const term = query.trim();

        if (term.length < 2) {
            return;
        }

        const timeout = window.setTimeout(() => {
            setHasSearched(true);
            void search.get(mobileScan.search.url({ query: { q: term } }));
        }, 300);

        return () => window.clearTimeout(timeout);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [query]);

    function submitBarcode(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();

        const term = barcode.trim();

        if (term === '') {
            return;
        }

        setHasSearched(true);
        void search.get(mobileScan.search.url({ query: { q: term } }));
    }

    const results = search.response?.matches ?? [];
    const showResults = hasSearched && !search.processing;

    const content = (
        <div className="space-y-4">
            <div className="space-y-1.5">
                <Label htmlFor="manual-search">Search by name or SKU</Label>
                <SearchInput
                    id="manual-search"
                    value={query}
                    onChange={(event) => setQuery(event.target.value)}
                    placeholder="Tomato, SKU-1042…"
                />
            </div>

            <form onSubmit={submitBarcode} className="space-y-1.5">
                <Label htmlFor="manual-barcode">
                    Or type the barcode digits
                </Label>
                <div className="flex gap-2">
                    <Input
                        id="manual-barcode"
                        inputMode="numeric"
                        value={barcode}
                        onChange={(event) => setBarcode(event.target.value)}
                        placeholder="0123456789012"
                    />
                    <Button type="submit" variant="secondary">
                        Find
                    </Button>
                </div>
            </form>

            {variant === 'inline' && onEnableCamera ? (
                <button
                    type="button"
                    onClick={onEnableCamera}
                    className="flex items-center gap-1.5 text-sm font-medium text-primary underline-offset-2 hover:underline"
                >
                    <Camera className="size-4" aria-hidden="true" />
                    Enable camera
                </button>
            ) : null}

            {showResults ? (
                results.length > 0 ? (
                    <SelectionList items={results} onSelect={onSelect} />
                ) : (
                    <EmptyState
                        icon={SearchIcon}
                        title="No matches"
                        description="Try a different name, SKU, or barcode."
                    />
                )
            ) : null}
        </div>
    );

    if (variant === 'inline') {
        return <div className="space-y-4">{content}</div>;
    }

    return (
        <Sheet open={open} onOpenChange={onOpenChange}>
            <SheetContent
                side="bottom"
                className="max-h-[85dvh] overflow-y-auto"
            >
                <SheetHeader>
                    <SheetTitle>Search inventory</SheetTitle>
                </SheetHeader>
                <div className="px-4 pb-4">{content}</div>
            </SheetContent>
        </Sheet>
    );
}

export { ManualEntrySheet };
