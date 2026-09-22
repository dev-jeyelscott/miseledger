import { Form, Head, Link } from '@inertiajs/react';
import { useId, useState } from 'react';
import type { ReactNode } from 'react';

import PlatformContentController from '@/actions/App/Http/Controllers/Platform/PlatformContentController';
import { PreviousPageButton } from '@/components/navigation/previous-page-button';
import { PageHeader } from '@/components/page-header';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';

type ContentPageDetail = {
    id: number;
    kind: 'marketing' | 'legal';
    key: string;
    slug: string;
    title: string;
    hasPublishedRevision: boolean;
    hasDraft: boolean;
    draftRevisionId: number | null;
};

type Revision = {
    id: number;
    revision: number;
    title: string;
    status: 'draft' | 'published' | 'superseded';
    createdBy: { name: string; email: string } | null;
    createdAt: string | null;
    publishedBy: { name: string; email: string } | null;
    publishedAt: string | null;
};

type Props = {
    page: ContentPageDetail;
    revisions: Revision[];
};

function RevisionStatusBadge({ status }: { status: Revision['status'] }) {
    if (status === 'published') {
        return <StatusBadge label="Published" variant="success" />;
    }

    if (status === 'draft') {
        return <StatusBadge label="Draft" variant="warning" />;
    }

    return <StatusBadge label="Superseded" variant="neutral" />;
}

/** Restore historical Markdown as a brand-new draft revision. Never auto-publishes. */
function RestoreDialog({
    revision,
    trigger,
}: {
    revision: Revision;
    trigger: ReactNode;
}) {
    const [open, setOpen] = useState(false);
    const descriptionId = useId();

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>{trigger}</DialogTrigger>

            <DialogContent className="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>
                        Restore revision {revision.revision} as a new draft?
                    </DialogTitle>
                    <DialogDescription id={descriptionId}>
                        This copies the Markdown from revision{' '}
                        {revision.revision} into a brand-new draft revision. It
                        does not publish anything and does not rewrite history;
                        every intermediate revision remains queryable. You will
                        still need to review and publish the new draft normally.
                    </DialogDescription>
                </DialogHeader>

                <Form
                    {...PlatformContentController.restore.form(revision.id)}
                    onSuccess={() => setOpen(false)}
                >
                    {({ processing }) => (
                        <DialogFooter className="mt-5">
                            <Button
                                type="button"
                                variant="outline"
                                disabled={processing}
                                onClick={() => setOpen(false)}
                            >
                                Cancel
                            </Button>

                            <Button type="submit" disabled={processing}>
                                {processing ? 'Restoring…' : 'Restore as Draft'}
                            </Button>
                        </DialogFooter>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

/** Render one content page's deterministic newest-first revision history. */
export default function PlatformContentShow({ page, revisions }: Props) {
    return (
        <>
            <Head title={page.title} />

            <div className="flex flex-1 flex-col gap-6 p-4 sm:p-6">
                <PageHeader
                    title={page.title}
                    description={`Internal key "${page.key}" · public slug "${page.slug}" · ${page.kind}`}
                    actions={
                        <>
                            {page.hasPublishedRevision ? (
                                <StatusBadge
                                    label="Published"
                                    variant="success"
                                />
                            ) : (
                                <StatusBadge
                                    label="Not published"
                                    variant="neutral"
                                />
                            )}

                            {page.hasDraft && page.draftRevisionId ? (
                                <Button asChild variant="outline">
                                    <Link
                                        href={PlatformContentController.edit(
                                            page.draftRevisionId,
                                        )}
                                    >
                                        Continue editing draft
                                    </Link>
                                </Button>
                            ) : null}

                            <PreviousPageButton
                                variant="outline"
                                fallback={PlatformContentController.index().url}
                            >
                                Back to content
                            </PreviousPageButton>
                        </>
                    }
                />

                <section
                    aria-label="Revision history"
                    className="overflow-hidden rounded-xl border border-border bg-card"
                >
                    <div className="overflow-x-auto">
                        <table className="w-full min-w-[760px] text-sm">
                            <caption className="sr-only">
                                Revision history, newest first
                            </caption>
                            <thead className="border-b border-border bg-muted/30 text-left text-xs text-muted-foreground">
                                <tr>
                                    <th
                                        scope="col"
                                        className="px-4 py-3 font-medium"
                                    >
                                        Revision
                                    </th>
                                    <th
                                        scope="col"
                                        className="px-4 py-3 font-medium"
                                    >
                                        Status
                                    </th>
                                    <th
                                        scope="col"
                                        className="px-4 py-3 font-medium"
                                    >
                                        Created by
                                    </th>
                                    <th
                                        scope="col"
                                        className="px-4 py-3 font-medium"
                                    >
                                        Published by
                                    </th>
                                    <th
                                        scope="col"
                                        className="px-4 py-3 font-medium"
                                    >
                                        Published at
                                    </th>
                                    <th
                                        scope="col"
                                        className="px-4 py-3 text-right font-medium"
                                    >
                                        Actions
                                    </th>
                                </tr>
                            </thead>

                            <tbody className="divide-y divide-border">
                                {revisions.map((revision) => (
                                    <tr
                                        key={revision.id}
                                        className="hover:bg-muted/30"
                                    >
                                        <td className="px-4 py-3 tabular-nums">
                                            {revision.revision}
                                        </td>
                                        <td className="px-4 py-3">
                                            <RevisionStatusBadge
                                                status={revision.status}
                                            />
                                        </td>
                                        <td className="px-4 py-3 text-muted-foreground">
                                            {revision.createdBy?.name ?? '—'}
                                        </td>
                                        <td className="px-4 py-3 text-muted-foreground">
                                            {revision.publishedBy?.name ?? '—'}
                                        </td>
                                        <td className="px-4 py-3 text-muted-foreground">
                                            {revision.publishedAt
                                                ? new Date(
                                                      revision.publishedAt,
                                                  ).toLocaleString()
                                                : '—'}
                                        </td>
                                        <td className="px-4 py-3 text-right">
                                            {revision.status === 'draft' ? (
                                                <Button
                                                    asChild
                                                    variant="outline"
                                                    size="sm"
                                                >
                                                    <Link
                                                        href={PlatformContentController.edit(
                                                            revision.id,
                                                        )}
                                                    >
                                                        Edit
                                                    </Link>
                                                </Button>
                                            ) : page.hasDraft ? (
                                                <span className="text-xs text-muted-foreground">
                                                    Draft in progress
                                                </span>
                                            ) : (
                                                <RestoreDialog
                                                    revision={revision}
                                                    trigger={
                                                        <Button
                                                            variant="outline"
                                                            size="sm"
                                                        >
                                                            Restore as Draft
                                                        </Button>
                                                    }
                                                />
                                            )}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>
        </>
    );
}
