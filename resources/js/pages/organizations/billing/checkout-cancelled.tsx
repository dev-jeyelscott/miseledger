import { Head, Link } from '@inertiajs/react';
import { XCircle } from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import settings from '@/routes/organizations/settings';
import type { OrganizationSummary } from '@/types';

type Props = {
    organization: OrganizationSummary;
};

/**
 * Show that Stripe Checkout was cancelled and return the member safely to
 * the organization's billing context. No subscription state is read or
 * changed here.
 */
export default function OrganizationCheckoutCancelled({ organization }: Props) {
    return (
        <>
            <Head title="Checkout cancelled" />

            <div className="flex flex-1 items-center justify-center p-4 md:p-6">
                <Card className="w-full max-w-md">
                    <CardHeader>
                        <div className="flex items-start gap-3">
                            <div className="flex size-10 shrink-0 items-center justify-center rounded-lg bg-muted">
                                <XCircle
                                    className="size-5 text-muted-foreground"
                                    aria-hidden="true"
                                />
                            </div>

                            <div className="grid gap-1">
                                <CardTitle>Checkout cancelled</CardTitle>
                                <CardDescription>
                                    No subscription changes were made for{' '}
                                    {organization.name}.
                                </CardDescription>
                            </div>
                        </div>
                    </CardHeader>

                    <CardContent>
                        <p className="text-sm text-muted-foreground">
                            You can restart Checkout at any time from the
                            organization's billing settings.
                        </p>
                    </CardContent>

                    <CardFooter className="justify-end border-t pt-6">
                        <Button asChild>
                            <Link href={settings.edit.url(organization.id)}>
                                Return to organization settings
                            </Link>
                        </Button>
                    </CardFooter>
                </Card>
            </div>
        </>
    );
}
