import { Head, Link } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import SupplierController from '@/actions/App/Http/Controllers/Suppliers/SupplierController';
import { Button } from '@/components/ui/button';
import { dashboard } from '@/routes';

type Supplier = {
    id: number;
    name: string;
    code: string;
    contactName: string | null;
    email: string | null;
    phone: string | null;
    itemCount: number;
    active: boolean;
};

type Props = {
    suppliers: Supplier[];
    canManage: boolean;
};

export default function SuppliersIndex({ suppliers, canManage }: Props) {
    return (
        <>
            <Head title="Suppliers" />

            <div className="flex flex-1 flex-col gap-6 p-4">
                <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
                    <div>
                        <h1 className="text-2xl font-semibold">Suppliers</h1>

                        <p className="mt-1 text-sm text-muted-foreground">
                            Manage vendors, purchase packs, and supplier
                            pricing.
                        </p>
                    </div>

                    {canManage && (
                        <Button asChild>
                            <Link href={SupplierController.create()}>
                                <Plus className="size-4" />
                                New supplier
                            </Link>
                        </Button>
                    )}
                </div>

                <div className="overflow-x-auto rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                    <div className="min-w-[760px]">
                        <div className="grid grid-cols-[minmax(0,1fr)_140px_220px_100px_100px] gap-4 border-b border-sidebar-border/70 px-5 py-3 text-xs font-medium text-muted-foreground uppercase dark:border-sidebar-border">
                            <span>Supplier</span>
                            <span>Code</span>
                            <span>Contact</span>
                            <span>Items</span>
                            <span>Status</span>
                        </div>

                        {suppliers.length === 0 ? (
                            <div className="px-5 py-10 text-center text-sm text-muted-foreground">
                                No suppliers have been created.
                            </div>
                        ) : (
                            <div className="divide-y divide-sidebar-border/70 dark:divide-sidebar-border">
                                {suppliers.map((supplier) => (
                                    <div
                                        key={supplier.id}
                                        className="grid grid-cols-[minmax(0,1fr)_140px_220px_100px_100px] items-center gap-4 px-5 py-4"
                                    >
                                        <Link
                                            href={SupplierController.edit(
                                                supplier.id,
                                            )}
                                            className="font-medium hover:underline"
                                        >
                                            {supplier.name}
                                        </Link>

                                        <span className="text-sm">
                                            {supplier.code}
                                        </span>

                                        <div className="min-w-0 text-sm">
                                            <p className="truncate">
                                                {supplier.contactName ?? '—'}
                                            </p>

                                            <p className="truncate text-muted-foreground">
                                                {supplier.email ??
                                                    supplier.phone ??
                                                    '—'}
                                            </p>
                                        </div>

                                        <span className="text-sm">
                                            {supplier.itemCount}
                                        </span>

                                        <span className="text-sm">
                                            {supplier.active
                                                ? 'Active'
                                                : 'Inactive'}
                                        </span>
                                    </div>
                                ))}
                            </div>
                        )}
                    </div>
                </div>
            </div>
        </>
    );
}

SuppliersIndex.layout = {
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: dashboard(),
        },
    ],
};
