import { Head, Link, usePage } from '@inertiajs/react';
import { ChevronDown } from 'lucide-react';
import { useEffect, useState } from 'react';
import { PageHeader } from '@/components/page-header';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import { cn } from '@/lib/utils';
import { index, show } from '@/routes/user-guide';
import type { OrganizationContext } from '@/types';
import { resolveGuideAction } from '@/user-guide/actions';
import type {
    GuideAccessContext,
    GuideModule,
    GuideModulePage,
    GuideModuleSlug,
} from '@/user-guide/types';
import { guideModules, guideModulesBySlug } from './content';

type Props = {
    module: string;
};

/** Render the canonical module list for both wide and compact guide navigation. */
function GuideNavigationLinks({
    currentSlug,
}: {
    currentSlug: GuideModuleSlug;
}) {
    return (
        <ul className="space-y-1">
            {guideModules.map((guideModule) => {
                const isCurrent = guideModule.slug === currentSlug;

                return (
                    <li key={guideModule.slug}>
                        <Link
                            href={show(guideModule.slug)}
                            prefetch
                            aria-current={isCurrent ? 'page' : undefined}
                            className={cn(
                                'flex min-h-11 min-w-0 items-center rounded-md border-l-2 px-3 py-2 text-sm leading-5 whitespace-normal transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background focus-visible:outline-none motion-reduce:transition-none 2xl:min-h-9',
                                isCurrent
                                    ? 'border-foreground bg-muted font-semibold text-foreground'
                                    : 'border-transparent text-muted-foreground hover:bg-muted hover:text-foreground',
                            )}
                        >
                            <span className="min-w-0 break-words">
                                {guideModule.title}
                            </span>
                        </Link>
                    </li>
                );
            })}
        </ul>
    );
}

/** Expose Guide Nav as a compact disclosure below wide desktop widths. */
function CompactGuideNavigation({ guide }: { guide: GuideModule }) {
    return (
        <nav aria-label="Guide navigation" className="2xl:hidden">
            <Collapsible>
                <div className="rounded-lg border border-border bg-background">
                    <CollapsibleTrigger asChild>
                        <button
                            type="button"
                            className="group flex min-h-11 w-full items-center justify-between gap-4 rounded-lg px-4 py-3 text-left focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background focus-visible:outline-none"
                        >
                            <span className="min-w-0">
                                <span className="block text-xs font-medium tracking-wide text-muted-foreground uppercase">
                                    Guide Nav
                                </span>
                                <span className="mt-0.5 block text-sm font-medium break-words text-foreground">
                                    {guide.title}
                                </span>
                            </span>
                            <ChevronDown
                                className="size-4 shrink-0 text-muted-foreground transition-transform group-data-[state=open]:rotate-180 motion-reduce:transition-none"
                                aria-hidden="true"
                            />
                        </button>
                    </CollapsibleTrigger>
                    <CollapsibleContent>
                        <div className="border-t border-border p-2">
                            <GuideNavigationLinks currentSlug={guide.slug} />
                        </div>
                    </CollapsibleContent>
                </div>
            </Collapsible>
        </nav>
    );
}

/** Render persistent secondary Guide Nav only when the viewport has sufficient width. */
function DesktopGuideNavigation({
    currentSlug,
}: {
    currentSlug: GuideModuleSlug;
}) {
    return (
        <aside className="hidden 2xl:col-span-2 2xl:block 2xl:self-start">
            <nav aria-label="Guide navigation" className="sticky top-24">
                <p className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                    Guide Nav
                </p>
                <div className="mt-3">
                    <GuideNavigationLinks currentSlug={currentSlug} />
                </div>
            </nav>
        </aside>
    );
}

/** Render native section anchors directly from the canonical guide page data. */
function GuidePageLinks({ pages }: { pages: GuideModulePage[] }) {
    return (
        <ol className="space-y-1">
            {pages.map((page) => (
                <li key={page.id}>
                    <a
                        href={`#${page.id}`}
                        className="flex min-h-11 min-w-0 items-center rounded-sm border-l-2 border-transparent px-3 py-2 text-sm leading-5 text-muted-foreground transition-colors hover:border-border hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background focus-visible:outline-none motion-reduce:transition-none 2xl:min-h-0 2xl:py-1.5"
                    >
                        <span className="min-w-0 break-words">
                            {page.title}
                        </span>
                    </a>
                </li>
            ))}
        </ol>
    );
}

/** Expose the page table of contents through an accessible compact disclosure. */
function CompactPageNavigation({ pages }: { pages: GuideModulePage[] }) {
    return (
        <nav
            aria-label="On this page"
            className="border-y border-border 2xl:hidden"
        >
            <Collapsible>
                <CollapsibleTrigger asChild>
                    <button
                        type="button"
                        className="group flex min-h-11 w-full items-center justify-between gap-4 py-3 text-left text-sm font-semibold focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background focus-visible:outline-none"
                    >
                        <span>On this page</span>
                        <ChevronDown
                            className="size-4 shrink-0 text-muted-foreground transition-transform group-data-[state=open]:rotate-180 motion-reduce:transition-none"
                            aria-hidden="true"
                        />
                    </button>
                </CollapsibleTrigger>
                <CollapsibleContent className="pb-3">
                    <GuidePageLinks pages={pages} />
                </CollapsibleContent>
            </Collapsible>
        </nav>
    );
}

/** Render a quiet sticky table of contents for wide documentation layouts. */
function DesktopPageNavigation({ pages }: { pages: GuideModulePage[] }) {
    return (
        <aside className="hidden 2xl:col-span-2 2xl:block 2xl:self-start">
            <nav aria-label="On this page" className="sticky top-24">
                <p className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                    On this page
                </p>
                <div className="mt-3">
                    <GuidePageLinks pages={pages} />
                </div>
            </nav>
        </aside>
    );
}

/** Render a controlled tutorial disclosure while preserving direct hash-target reveal behavior. */
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

    useEffect(() => {
        let revealFrame: number | undefined;
        let scrollFrame: number | undefined;

        /** Open and scroll to this tutorial when its stable hash becomes the active URL target. */
        const revealHashTarget = (): void => {
            if (window.location.hash !== `#${id}`) {
                return;
            }

            revealFrame = requestAnimationFrame(() => {
                setOpen(true);
                scrollFrame = requestAnimationFrame(() => {
                    document
                        .getElementById(id)
                        ?.scrollIntoView({ block: 'start' });
                });
            });
        };

        revealHashTarget();
        window.addEventListener('hashchange', revealHashTarget);

        return () => {
            window.removeEventListener('hashchange', revealHashTarget);

            if (revealFrame !== undefined) {
                cancelAnimationFrame(revealFrame);
            }

            if (scrollFrame !== undefined) {
                cancelAnimationFrame(scrollFrame);
            }
        };
    }, [id]);

    return (
        <Collapsible open={open} onOpenChange={setOpen}>
            <CollapsibleTrigger asChild>
                <button
                    id={id}
                    type="button"
                    className="flex min-h-11 w-full scroll-mt-20 items-center justify-between gap-4 rounded-md py-3 text-left text-sm font-medium focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background focus-visible:outline-none"
                >
                    <span className="min-w-0 break-words">{title}</span>
                    <ChevronDown
                        className={cn(
                            'size-4 shrink-0 text-muted-foreground transition-transform motion-reduce:transition-none',
                            open && 'rotate-180',
                        )}
                        aria-hidden="true"
                    />
                </button>
            </CollapsibleTrigger>
            <CollapsibleContent className="pb-4">
                <ol className="list-none space-y-4 pt-2">
                    {steps.map((step, index) => (
                        <li
                            key={`${id}-${index}`}
                            className="flex gap-3 text-sm leading-6 text-muted-foreground"
                        >
                            <span
                                aria-hidden="true"
                                className="flex size-8 shrink-0 items-center justify-center rounded-full border border-border bg-muted text-sm font-semibold text-foreground"
                            >
                                {index + 1}
                            </span>
                            <span className="min-w-0 flex-1 pt-1">{step}</span>
                        </li>
                    ))}
                </ol>
            </CollapsibleContent>
        </Collapsible>
    );
}

/** Render one canonical guide page as a semantic section inside the shared module article. */
function GuidePage({
    isFirst,
    page,
}: {
    isFirst: boolean;
    page: GuideModulePage;
}) {
    const headingId = `${page.id}-heading`;

    return (
        <section
            id={page.id}
            aria-labelledby={headingId}
            className={cn(
                'scroll-mt-20 py-8',
                isFirst ? 'pt-0' : 'border-t border-border',
            )}
        >
            <div className="space-y-2">
                <h2
                    id={headingId}
                    className="text-xl font-semibold tracking-tight"
                >
                    {page.title}
                </h2>
                <p className="max-w-3xl text-sm leading-6 text-muted-foreground">
                    {page.summary}
                </p>
            </div>

            {page.whenToUse ? (
                <section className="mt-6">
                    <h3 className="text-sm font-semibold">When to use it</h3>
                    <p className="mt-2 max-w-3xl text-sm leading-6 text-muted-foreground">
                        {page.whenToUse}
                    </p>
                </section>
            ) : null}

            {page.controls && page.controls.length > 0 ? (
                <section className="mt-6">
                    <h3 className="text-sm font-semibold">
                        Important controls
                    </h3>
                    <dl className="mt-3 divide-y divide-border border-y border-border">
                        {page.controls.map((control) => (
                            <div
                                key={control.label}
                                className="grid gap-1 py-3 sm:grid-cols-3 sm:gap-6"
                            >
                                <dt className="text-sm font-medium">
                                    {control.label}
                                </dt>
                                <dd className="text-sm leading-6 text-muted-foreground sm:col-span-2">
                                    {control.description}
                                </dd>
                            </div>
                        ))}
                    </dl>
                </section>
            ) : null}

            {page.fields && page.fields.length > 0 ? (
                <section className="mt-6">
                    <h3 className="text-sm font-semibold">Important fields</h3>
                    <dl className="mt-3 divide-y divide-border border-y border-border">
                        {page.fields.map((field) => (
                            <div
                                key={field.name}
                                className="grid gap-1 py-3 sm:grid-cols-3 sm:gap-6"
                            >
                                <dt className="text-sm font-medium">
                                    {field.name}
                                </dt>
                                <dd className="text-sm leading-6 text-muted-foreground sm:col-span-2">
                                    {field.description}
                                </dd>
                            </div>
                        ))}
                    </dl>
                </section>
            ) : null}

            {page.tutorials && page.tutorials.length > 0 ? (
                <section className="mt-6">
                    <h3 className="text-sm font-semibold">Tutorials</h3>
                    <div className="mt-3 divide-y divide-border border-y border-border">
                        {page.tutorials.map((tutorial) => (
                            <GuideTutorial key={tutorial.id} {...tutorial} />
                        ))}
                    </div>
                </section>
            ) : null}

            {page.whatHappensNext && page.whatHappensNext.length > 0 ? (
                <section className="mt-6 rounded-lg bg-muted p-4">
                    <h3 className="text-sm font-semibold">What happens next</h3>
                    <ul className="mt-2 list-disc space-y-2 pl-5 text-sm leading-6 text-muted-foreground">
                        {page.whatHappensNext.map((outcome) => (
                            <li key={outcome}>{outcome}</li>
                        ))}
                    </ul>
                </section>
            ) : null}

            {page.notes && page.notes.length > 0 ? (
                <section aria-label="Notes" className="mt-6 space-y-3">
                    {page.notes.map((note) => (
                        <Alert key={note.title}>
                            <AlertTitle>
                                <h3 className="text-sm font-medium">
                                    {note.title}
                                </h3>
                            </AlertTitle>
                            <AlertDescription>
                                {note.description}
                            </AlertDescription>
                        </Alert>
                    ))}
                </section>
            ) : null}

            {page.troubleshooting && page.troubleshooting.length > 0 ? (
                <section className="mt-6">
                    <h3 className="text-sm font-semibold">Troubleshooting</h3>
                    <dl className="mt-3 divide-y divide-border border-y border-border">
                        {page.troubleshooting.map((item) => (
                            <div key={item.question} className="py-3">
                                <dt className="text-sm font-medium">
                                    {item.question}
                                </dt>
                                <dd className="mt-1 max-w-3xl text-sm leading-6 text-muted-foreground">
                                    {item.answer}
                                </dd>
                            </div>
                        ))}
                    </dl>
                </section>
            ) : null}
        </section>
    );
}

/** Preserve legacy top-level tutorial, field, and troubleshooting data without requiring content migration. */
function LegacyGuideContent({ guide }: { guide: GuideModule }) {
    return (
        <div>
            {guide.tutorials.length > 0 ? (
                <section className="border-t border-border py-8 first:border-t-0 first:pt-0">
                    <h2 className="text-xl font-semibold tracking-tight">
                        Tutorials
                    </h2>
                    <div className="mt-4 divide-y divide-border border-y border-border">
                        {guide.tutorials.map((tutorial) => (
                            <GuideTutorial key={tutorial.id} {...tutorial} />
                        ))}
                    </div>
                </section>
            ) : null}

            {guide.fields.length > 0 ? (
                <section className="border-t border-border py-8 first:border-t-0 first:pt-0">
                    <h2 className="text-xl font-semibold tracking-tight">
                        Key fields
                    </h2>
                    <dl className="mt-4 divide-y divide-border border-y border-border">
                        {guide.fields.map((field) => (
                            <div
                                key={field.name}
                                className="grid gap-1 py-3 sm:grid-cols-3 sm:gap-6"
                            >
                                <dt className="text-sm font-medium">
                                    {field.name}
                                </dt>
                                <dd className="text-sm leading-6 text-muted-foreground sm:col-span-2">
                                    {field.description}
                                </dd>
                            </div>
                        ))}
                    </dl>
                </section>
            ) : null}

            {guide.troubleshooting.length > 0 ? (
                <section className="border-t border-border py-8 first:border-t-0 first:pt-0">
                    <h2 className="text-xl font-semibold tracking-tight">
                        Troubleshooting
                    </h2>
                    <ul className="mt-4 list-disc space-y-2 pl-5 text-sm leading-6 text-muted-foreground">
                        {guide.troubleshooting.map((tip) => (
                            <li key={tip}>{tip}</li>
                        ))}
                    </ul>
                </section>
            ) : null}
        </div>
    );
}

/** Render the shared User Guide module reader using canonical content and access contracts. */
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
    const pages = guide.pages ?? [];

    return (
        <>
            <Head title={`${guide.title} | User Guide`} />

            <div className="flex flex-1 flex-col p-4 sm:p-6">
                <div className="mx-auto flex w-full max-w-7xl flex-col gap-6">
                    <PageHeader
                        title={guide.title}
                        description={guide.description}
                    />

                    {guide.accessNote ? (
                        <Alert>
                            <AlertDescription>
                                {guide.accessNote}
                            </AlertDescription>
                        </Alert>
                    ) : null}

                    <CompactGuideNavigation guide={guide} />

                    <div className="grid min-w-0 gap-8 2xl:grid-cols-12">
                        <DesktopGuideNavigation currentSlug={guide.slug} />

                        <article
                            aria-label={`${guide.title} guide`}
                            className="mx-auto w-full max-w-4xl min-w-0 2xl:col-span-8 2xl:max-w-none"
                        >
                            <div className="space-y-4">
                                <p className="max-w-3xl text-base leading-7 text-muted-foreground">
                                    {guide.overview}
                                </p>

                                {guide.actions.length > 0 ? (
                                    availableActions.length > 0 ? (
                                        <div className="flex flex-col items-start gap-2 sm:flex-row sm:flex-wrap">
                                            {availableActions.map((action) => (
                                                <Button
                                                    key={action.label}
                                                    asChild
                                                    variant="outline"
                                                    className="min-h-11 w-full whitespace-normal sm:min-h-9 sm:w-auto"
                                                >
                                                    <Link
                                                        href={action.href}
                                                        prefetch
                                                    >
                                                        {action.label}
                                                    </Link>
                                                </Button>
                                            ))}
                                        </div>
                                    ) : (
                                        <p className="max-w-3xl text-sm leading-6 text-muted-foreground">
                                            You may not see this feature if it
                                            is not included in your plan or your
                                            access level.
                                        </p>
                                    )
                                ) : null}
                            </div>

                            {pages.length > 0 ? (
                                <>
                                    <div className="mt-6">
                                        <CompactPageNavigation pages={pages} />
                                    </div>

                                    <div className="mt-10">
                                        {pages.map((page, pageIndex) => (
                                            <GuidePage
                                                key={page.id}
                                                page={page}
                                                isFirst={pageIndex === 0}
                                            />
                                        ))}
                                    </div>
                                </>
                            ) : (
                                <div className="mt-10">
                                    <LegacyGuideContent guide={guide} />
                                </div>
                            )}
                        </article>

                        {pages.length > 0 ? (
                            <DesktopPageNavigation pages={pages} />
                        ) : null}
                    </div>
                </div>
            </div>
        </>
    );
}

/** Provide canonical User Guide breadcrumbs to the authenticated application layout. */
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
