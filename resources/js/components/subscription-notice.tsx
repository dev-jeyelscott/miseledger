import { Link, usePage } from '@inertiajs/react';
import { AlertTriangle, CreditCard, Lock } from 'lucide-react';
import OrganizationBillingController from '@/actions/App/Http/Controllers/Billing/OrganizationBillingController';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { buttonVariants } from '@/components/ui/button';
import { resolveCallToAction, resolveNotice } from '@/lib/subscription-notice';
import { cn } from '@/lib/utils';
import type { OrganizationContext } from '@/types';

type PageProps = {
    organizationContext: OrganizationContext;
};

/**
 * Render a persistent, active-organization-scoped subscription notice
 * driven solely by the safe P2 subscription context shared to every
 * authenticated page. Never reads or infers authorization: the server
 * remains the sole enforcement boundary for any restriction described here.
 */
export function SubscriptionNotice() {
    const { organizationContext } = usePage<PageProps>().props;

    const activeOrganization = organizationContext.active;
    const subscription = organizationContext.subscription;

    if (activeOrganization === null || subscription === null) {
        return null;
    }

    const notice = resolveNotice(subscription);

    if (notice === null) {
        return null;
    }

    const callToAction = resolveCallToAction(organizationContext);

    const Icon = notice.variant === 'destructive' ? AlertTriangle : Lock;

    return (
        <Alert
            key={activeOrganization.id}
            variant={notice.variant}
            className="mx-4 mt-4 md:mx-6"
        >
            <Icon aria-hidden="true" />
            <AlertTitle>{notice.title}</AlertTitle>
            <AlertDescription>
                <p>{notice.description}</p>

                {callToAction.type === 'billing_link' ? (
                    <Link
                        href={OrganizationBillingController.show(
                            callToAction.organizationId,
                        )}
                        className={cn(
                            buttonVariants({
                                variant: 'outline',
                                size: 'sm',
                            }),
                            'mt-2',
                        )}
                    >
                        <CreditCard aria-hidden="true" />
                        Go to Billing
                    </Link>
                ) : (
                    <p className="mt-2 text-xs">
                        Contact your organization owner to update billing.
                    </p>
                )}
            </AlertDescription>
        </Alert>
    );
}
