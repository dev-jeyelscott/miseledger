import { Head, router } from '@inertiajs/react';
import { SearchX } from 'lucide-react';
import { useMemo, useState } from 'react';

import { EmptyState } from '@/components/empty-state';
import { Label } from '@/components/ui/label';
import { SearchInput } from '@/components/ui/search-input';
import mobileReceiving from '@/routes/mobile/receiving';

type SupplierOption = {
    id: number;
    name: string;
};

type ReceivingCreateProps = {
    suppliers: SupplierOption[];
};

/** Ad-hoc receiving: pick the supplier the unplanned delivery came from. */
export default function ReceivingCreate({ suppliers }: ReceivingCreateProps) {
    const [query, setQuery] = useState('');

    const filtered = useMemo(() => {
        const term = query.trim().toLowerCase();

        if (term === '') {
            return suppliers;
        }

        return suppliers.filter((supplier) =>
            supplier.name.toLowerCase().includes(term),
        );
    }, [query, suppliers]);

    function selectSupplier(supplierId: number) {
        router.post(mobileReceiving.store.url(), {
            supplier_id: supplierId,
        });
    }

    return (
        <>
            <Head title="Start ad-hoc receiving" />

            <div className="space-y-4">
                <div>
                    <h1 className="text-lg font-semibold">Ad-hoc receiving</h1>
                    <p className="text-sm text-muted-foreground">
                        Which supplier is this delivery from?
                    </p>
                </div>

                <div className="space-y-1.5">
                    <Label htmlFor="ad-hoc-supplier-search">Supplier</Label>
                    <SearchInput
                        id="ad-hoc-supplier-search"
                        value={query}
                        onChange={(event) => setQuery(event.target.value)}
                        placeholder="Search suppliers…"
                    />
                </div>

                {filtered.length === 0 ? (
                    <EmptyState
                        icon={SearchX}
                        title="No matching suppliers"
                        description="Try a different search, or add the supplier on desktop first."
                    />
                ) : (
                    <ul className="space-y-2">
                        {filtered.map((supplier) => (
                            <li key={supplier.id}>
                                <button
                                    type="button"
                                    onClick={() => selectSupplier(supplier.id)}
                                    className="flex min-h-[44px] w-full items-center rounded-md border border-input px-4 py-3 text-left font-medium hover:bg-accent"
                                >
                                    {supplier.name}
                                </button>
                            </li>
                        ))}
                    </ul>
                )}
            </div>
        </>
    );
}
