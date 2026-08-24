import { Link, usePage } from '@inertiajs/react';
import { AlertTriangle } from 'lucide-react';
import OrganizationBillingController from '@/actions/App/Http/Controllers/Billing/OrganizationBillingController';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { buttonVariants } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import type { OrganizationContext } from '@/types';

type PageProps = {
    organizationContext: OrganizationContext;
};

type Props = {
    /** The `UsageLimitKey` dimension this notice reports on. */
    limitKey: string;
    /** Label used in the rendered guidance, e.g. "locations". */
    resourceLabel: string;
};

/**
 * Render non-authoritative current-usage-versus-limit guidance for one
 * quantitative dimension, driven solely by the safe P2/P4 subscription
 * context shared to every authenticated page. Existing records are never
 * affected by this notice: the server remains the sole enforcement boundary
 * blocking new creation once the plan's limit is reached.
 */
export function UsageLimitNotice({ limitKey, resourceLabel }: Props) {
    const { organizationContext } = usePage<PageProps>().props;

    const activeOrganization = organizationContext.active;
    const usage = organizationContext.entitlements?.usage[limitKey];

    if (activeOrganization === null || usage === undefined) {
        return null;
    }

    if (usage.isUnlimited) {
        return null;
    }

    if (!usage.atLimit) {
        return (
            <p className="text-sm text-muted-foreground">
                {usage.current} of {usage.limit} {resourceLabel} used on the
                current plan.
            </p>
        );
    }

    return (
        <Alert variant="destructive">
            <AlertTriangle aria-hidden="true" />
            <AlertTitle>Plan limit reached</AlertTitle>
            <AlertDescription>
                <p>
                    This organization has used {usage.current} of{' '}
                    {usage.limit} {resourceLabel} allowed on the current plan.
                    Existing {resourceLabel} remain available, but creating
                    another is blocked until you upgrade.
                </p>

                <Link
                    href={OrganizationBillingController.show(
                        activeOrganization.id,
                    )}
                    className={cn(
                        buttonVariants({ variant: 'outline', size: 'sm' }),
                        'mt-2',
                    )}
                >
                    Go to Billing
                </Link>
            </AlertDescription>
        </Alert>
    );
}
