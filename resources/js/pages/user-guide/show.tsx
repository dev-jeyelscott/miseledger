import { Head, Link, usePage } from '@inertiajs/react';
import { ChevronDown } from 'lucide-react';
import { useState } from 'react';
import { PageHeader } from '@/components/page-header';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import { index, show } from '@/routes/user-guide';
import type { OrganizationContext } from '@/types';
import { resolveGuideAction } from '@/user-guide/actions';
import type { GuideAccessContext, GuideModuleSlug } from '@/user-guide/types';
import { guideModulesBySlug } from './content';

type Props = {
    module: string;
};

function GuideTutorial({
    id,
    steps,
    title,
}: {
    id: string;
    steps: string[];
    title: string;
}) {
    const [open, setOpen] = useState(false);

    return (
        <Collapsible open={open} onOpenChange={setOpen}>
            <CollapsibleTrigger asChild>
                <button
                    id={id}
                    type="button"
                    className="flex w-full items-center justify-between gap-4 rounded-md py-2 text-left text-sm font-medium focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                >
                    {title}
                    <ChevronDown
                        className={`size-4 shrink-0 text-muted-foreground transition-transform ${open ? 'rotate-180' : ''}`}
                        aria-hidden="true"
                    />
                </button>
            </CollapsibleTrigger>
            <CollapsibleContent className="pb-2">
                <ol className="list-decimal space-y-2 pl-5 text-sm leading-6 text-muted-foreground">
                    {steps.map((step) => (
                        <li key={step}>{step}</li>
                    ))}
                </ol>
            </CollapsibleContent>
        </Collapsible>
    );
}

export default function UserGuideShow({ module }: Props) {
    const guide = guideModulesBySlug[module as GuideModuleSlug];
    const { organizationContext } = usePage<{
        organizationContext: OrganizationContext;
    }>().props;
    const activeMembership = organizationContext.memberships.find(
        (membership) =>
            membership.organization.id === organizationContext.active?.id,
    );
    const permissions = new Set(activeMembership?.permissions ?? []);
    const accessContext: GuideAccessContext = {
        activeOrganizationId: organizationContext.active?.id ?? null,
        aiCanUse: organizationContext.ai?.canUse ?? false,
        hasFeature: (feature) =>
            organizationContext.entitlements?.grants[feature] ?? false,
        hasPermission: (permission) => permissions.has(permission),
    };
    const availableActions = guide.actions.flatMap((action) => {
        const resolvedAction = resolveGuideAction(action, accessContext);

        return resolvedAction === null ? [] : [resolvedAction];
    });

    return (
        <>
            <Head title={`${guide.title} | User Guide`} />

            <div className="flex flex-1 flex-col gap-6 p-4 sm:p-6">
                <PageHeader
                    title={guide.title}
                    description={guide.description}
                />

                {guide.accessNote ? (
                    <Alert>
                        <AlertDescription>{guide.accessNote}</AlertDescription>
                    </Alert>
                ) : null}

                <section className="rounded-xl border border-border bg-card p-5 shadow-sm">
                    <h2 className="text-sm font-semibold">Overview</h2>
                    <p className="mt-2 max-w-3xl text-sm leading-6 text-muted-foreground">
                        {guide.overview}
                    </p>
                </section>

                {guide.actions.length > 0 ? (
                    <section className="rounded-xl border border-border bg-card p-5 shadow-sm">
                        <h2 className="text-sm font-semibold">
                            Go to this feature
                        </h2>
                        {availableActions.length > 0 ? (
                            <div className="mt-4 flex flex-wrap gap-2">
                                {availableActions.map((action) => (
                                    <Button
                                        key={action.label}
                                        asChild
                                        variant="outline"
                                    >
                                        <Link href={action.href} prefetch>
                                            {action.label}
                                        </Link>
                                    </Button>
                                ))}
                            </div>
                        ) : (
                            <p className="mt-2 text-sm leading-6 text-muted-foreground">
                                You may not see this feature if it is not
                                included in your plan or your access level.
                            </p>
                        )}
                    </section>
                ) : null}

                <div className="grid gap-6 lg:grid-cols-2">
                    <section className="rounded-xl border border-border bg-card p-5 shadow-sm">
                        <h2 className="text-sm font-semibold">Tutorials</h2>
                        <div className="mt-3 divide-y divide-border">
                            {guide.tutorials.map((tutorial) => (
                                <GuideTutorial
                                    key={tutorial.id}
                                    {...tutorial}
                                />
                            ))}
                        </div>
                    </section>

                    <section className="rounded-xl border border-border bg-card p-5 shadow-sm">
                        <h2 className="text-sm font-semibold">Key fields</h2>
                        <dl className="mt-4 space-y-4">
                            {guide.fields.map((field) => (
                                <div key={field.name}>
                                    <dt className="text-sm font-medium">
                                        {field.name}
                                    </dt>
                                    <dd className="mt-1 text-sm leading-6 text-muted-foreground">
                                        {field.description}
                                    </dd>
                                </div>
                            ))}
                        </dl>
                    </section>
                </div>

                <section className="rounded-xl border border-border bg-card p-5 shadow-sm">
                    <h2 className="text-sm font-semibold">Troubleshooting</h2>
                    <ul className="mt-3 list-disc space-y-2 pl-5 text-sm leading-6 text-muted-foreground">
                        {guide.troubleshooting.map((tip) => (
                            <li key={tip}>{tip}</li>
                        ))}
                    </ul>
                </section>
            </div>
        </>
    );
}

UserGuideShow.layout = (page: Props) => {
    const guide = guideModulesBySlug[page.module as GuideModuleSlug];

    return {
        breadcrumbs: [
            {
                title: 'User Guide',
                href: index(),
            },
            ...(guide
                ? [
                      {
                          title: guide.title,
                          href: show(page.module),
                      },
                  ]
                : []),
        ],
    };
};
