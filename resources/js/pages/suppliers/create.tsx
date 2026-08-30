import { Form, Head } from '@inertiajs/react';
import SupplierController from '@/actions/App/Http/Controllers/Suppliers/SupplierController';
import { PreviousPageButton } from '@/components/navigation/previous-page-button';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { Field } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { dashboard } from '@/routes';

export default function CreateSupplier() {
    return (
        <>
            <Head title="Create supplier" />

            <div className="flex flex-1 flex-col gap-6 p-4">
                <PageHeader
                    title="Create supplier"
                    description="Add a vendor to the active organization."
                />

                <div className="max-w-2xl rounded-xl border border-sidebar-border/70 p-5 dark:border-sidebar-border">
                    <Form
                        {...SupplierController.store.form()}
                        className="space-y-5"
                    >
                        {({ processing, errors }) => (
                            <>
                                <input type="hidden" name="active" value="1" />

                                <div className="grid gap-5 sm:grid-cols-2">
                                    <Field
                                        id="name"
                                        label="Name"
                                        error={errors.name}
                                    >
                                        <Input
                                            name="name"
                                            required
                                            autoFocus
                                            placeholder="Metro Food Supply"
                                        />
                                    </Field>

                                    <Field
                                        id="code"
                                        label="Code"
                                        error={errors.code}
                                    >
                                        <Input
                                            name="code"
                                            required
                                            placeholder="METRO"
                                        />
                                    </Field>

                                    <Field
                                        id="contact_name"
                                        label="Contact name"
                                        error={errors.contact_name}
                                    >
                                        <Input name="contact_name" />
                                    </Field>

                                    <Field
                                        id="email"
                                        label="Email"
                                        error={errors.email}
                                    >
                                        <Input name="email" type="email" />
                                    </Field>

                                    <Field
                                        id="phone"
                                        label="Phone"
                                        error={errors.phone}
                                    >
                                        <Input name="phone" />
                                    </Field>

                                    <Field
                                        id="payment_terms"
                                        label="Payment terms"
                                        error={errors.payment_terms}
                                    >
                                        <Input
                                            name="payment_terms"
                                            placeholder="Net 30"
                                        />
                                    </Field>

                                    <Field
                                        id="lead_time_days"
                                        label="Lead time (days)"
                                        error={errors.lead_time_days}
                                    >
                                        <Input
                                            name="lead_time_days"
                                            type="number"
                                            min="0"
                                            step="1"
                                        />
                                    </Field>
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
