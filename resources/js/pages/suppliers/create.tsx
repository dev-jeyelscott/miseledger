import { Form, Head } from '@inertiajs/react';
import SupplierController from '@/actions/App/Http/Controllers/Suppliers/SupplierController';
import InputError from '@/components/input-error';
import { PreviousPageButton } from '@/components/navigation/previous-page-button';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { dashboard } from '@/routes';

export default function CreateSupplier() {
    return (
        <>
            <Head title="Create supplier" />

            <div className="flex flex-1 flex-col gap-6 p-4">
                <div>
                    <h1 className="text-2xl font-semibold">Create supplier</h1>
                    <p className="mt-1 text-sm text-muted-foreground">
                        Add a vendor to the active organization.
                    </p>
                </div>

                <div className="max-w-2xl rounded-xl border border-sidebar-border/70 p-5 dark:border-sidebar-border">
                    <Form
                        {...SupplierController.store.form()}
                        className="space-y-5"
                    >
                        {({ processing, errors }) => (
                            <>
                                <input type="hidden" name="active" value="1" />

                                <div className="grid gap-5 sm:grid-cols-2">
                                    <div className="grid gap-2">
                                        <Label htmlFor="name">Name</Label>
                                        <Input
                                            id="name"
                                            name="name"
                                            required
                                            autoFocus
                                            placeholder="Metro Food Supply"
                                        />
                                        <InputError message={errors.name} />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="code">Code</Label>
                                        <Input
                                            id="code"
                                            name="code"
                                            required
                                            placeholder="METRO"
                                        />
                                        <InputError message={errors.code} />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="contact_name">
                                            Contact name
                                        </Label>
                                        <Input
                                            id="contact_name"
                                            name="contact_name"
                                        />
                                        <InputError
                                            message={errors.contact_name}
                                        />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="email">Email</Label>
                                        <Input
                                            id="email"
                                            name="email"
                                            type="email"
                                        />
                                        <InputError message={errors.email} />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="phone">Phone</Label>
                                        <Input id="phone" name="phone" />
                                        <InputError message={errors.phone} />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="payment_terms">
                                            Payment terms
                                        </Label>
                                        <Input
                                            id="payment_terms"
                                            name="payment_terms"
                                            placeholder="Net 30"
                                        />
                                        <InputError
                                            message={errors.payment_terms}
                                        />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="lead_time_days">
                                            Lead time (days)
                                        </Label>
                                        <Input
                                            id="lead_time_days"
                                            name="lead_time_days"
                                            type="number"
                                            min="0"
                                            step="1"
                                        />
                                        <InputError
                                            message={errors.lead_time_days}
                                        />
                                    </div>
                                </div>

                                <div className="flex gap-2">
                                    <Button type="submit" disabled={processing}>
                                        {processing
                                            ? 'Creating…'
                                            : 'Create supplier'}
                                    </Button>

                                    <PreviousPageButton
                                        variant="outline"
                                        disabled={processing}
                                        fallback={
                                            SupplierController.index().url
                                        }
                                    >
                                        Cancel
                                    </PreviousPageButton>
                                </div>
                            </>
                        )}
                    </Form>
                </div>
            </div>
        </>
    );
}

CreateSupplier.layout = {
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: dashboard(),
        },
        {
            title: 'Suppliers',
            href: SupplierController.index(),
        },
    ],
};
