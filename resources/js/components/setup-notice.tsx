import { Link, usePage } from '@inertiajs/react';
import { ListChecks } from 'lucide-react';
import OnboardingController from '@/actions/App/Http/Controllers/OnboardingController';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { buttonVariants } from '@/components/ui/button';
import { cn } from '@/lib/utils';

/**
 * Explain why stock workflows are blocked while first-time setup is
 * incomplete. Display only: the server enforces the blocker on every
 * operational mutation.
 */
export function SetupNotice() {
    const page = usePage();
    const { setup } = page.props;

    if (setup === null || setup.ready || page.component === 'onboarding/show') {
        return null;
    }

    return (
        <Alert className="mx-4 mt-4 w-auto min-w-0 border-warning-border bg-warning-subtle text-warning-foreground md:mx-6 [&>svg]:text-warning-foreground">
            <ListChecks aria-hidden="true" />
            <AlertTitle>Finish setup to start recording stock</AlertTitle>
            <AlertDescription className="text-warning-foreground/80">
                <p>
                    Purchasing, receiving, stock counts, transfers, waste, and
                    adjustments are locked until your organization has a
                    location and inventory items with opening stock resolved.
                </p>
                <Link
                    href={OnboardingController.show()}
                    className={cn(
                        buttonVariants({ variant: 'outline', size: 'sm' }),
                        'mt-2',
                    )}
                >
                    Continue setup
                </Link>
            </AlertDescription>
        </Alert>
    );
}
