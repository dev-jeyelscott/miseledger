import { Form, Head } from '@inertiajs/react';
import { useEffect, useId, useState } from 'react';

import PlatformContentController from '@/actions/App/Http/Controllers/Platform/PlatformContentController';
import { ContentMarkdown } from '@/components/content-markdown';
import { PreviousPageButton } from '@/components/navigation/previous-page-button';
import { PageHeader } from '@/components/page-header';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Field } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { useDirtyFormNavigation } from '@/hooks/use-dirty-form-navigation';

type Props = {
    page: {
        id: number;
        kind: 'marketing' | 'legal';
        key: string;
        slug: string;
    };
    revision: {
        id: number;
        revision: number;
        title: string;
        bodyMarkdown: string;
    };
};

function DirtyStateTracker({
    dirty,
    successful,
    onChange,
}: {
    dirty: boolean;
    successful: boolean;
    onChange: (dirty: boolean) => void;
}) {
    useEffect(() => {
        onChange(dirty && !successful);
    }, [dirty, onChange, successful]);

    return null;
}

/** Publish a draft, requiring explicit acknowledgement of its public consequence. */
function PublishDialog({
    revisionId,
    page,
}: {
    revisionId: number;
    page: Props['page'];
}) {
    const [open, setOpen] = useState(false);
    const confirmId = useId();

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button variant="outline">Publish revision</Button>
            </DialogTrigger>

            <DialogContent className="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Publish this revision?</DialogTitle>
                    <DialogDescription>
                        This makes the content immediately visible on the public{' '}
                        {page.kind} page at slug &ldquo;{page.slug}
                        &rdquo;. Published revisions are immutable historical
                        evidence and cannot be edited afterward.
                        {page.kind === 'legal' ? (
                            <span className="mt-2 block font-medium text-warning-foreground">
                                Legal content: confirm this page has cleared the
                                required legal/business-registration review
                                before publishing.
                            </span>
                        ) : null}
                    </DialogDescription>
                </DialogHeader>

                <Form
                    {...PlatformContentController.publish.form(revisionId)}
                    onSuccess={() => setOpen(false)}
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="flex items-start gap-2">
                                <Checkbox
                                    id={confirmId}
                                    name="confirm"
                                    value="1"
                                    required
                                    aria-describedby={
                                        errors.confirm
                                            ? `${confirmId}-error`
                                            : undefined
                                    }
                                />
                                <Label
                                    htmlFor={confirmId}
                                    className="text-sm leading-snug font-normal"
                                >
                                    I understand this publishes the exact
                                    Markdown above to the public {page.kind}{' '}
                                    page immediately.
                                </Label>
                            </div>

                            {errors.confirm ? (
                                <p
                                    id={`${confirmId}-error`}
                                    className="mt-2 text-sm text-destructive"
                                >
                                    {errors.confirm}
                                </p>
                            ) : null}

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
                                    {processing
                                        ? 'Publishing…'
                                        : 'Publish Revision'}
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

/** Edit and preview a draft content revision's Markdown before publishing. */
export default function PlatformContentEdit({ page, revision }: Props) {
    const dirtyFormNavigation = useDirtyFormNavigation(
        'You have unsaved draft changes. Leave without saving them?',
    );

    const [mode, setMode] = useState<'edit' | 'preview'>('edit');
    const [body, setBody] = useState(revision.bodyMarkdown);
    const [title, setTitle] = useState(revision.title);

    return (
        <>
            <Head title={`Edit ${title || 'draft'}`} />

            <div className="flex flex-1 flex-col gap-6 p-4 sm:p-6">
                <PageHeader
                    title={`${page.key} · Draft revision ${revision.revision}`}
                    description="Published revisions are immutable. This draft is only visible on the platform console until it is published."
                    actions={
                        <>
                            <StatusBadge label="Draft" variant="warning" />

                            <PublishDialog
                                revisionId={revision.id}
                                page={page}
                            />

                            <PreviousPageButton
                                variant="outline"
                                fallback={
                                    PlatformContentController.show(page.id).url
                                }
                                onNavigate={
                                    dirtyFormNavigation.confirmNavigation
                                }
                            >
                                Back to page
                            </PreviousPageButton>
                        </>
                    }
                />

                <Form
                    {...PlatformContentController.update.form(revision.id)}
                    className="space-y-6"
                >
                    {({ processing, errors, isDirty, wasSuccessful }) => (
                        <>
                            <DirtyStateTracker
                                dirty={isDirty}
                                successful={wasSuccessful}
                                onChange={dirtyFormNavigation.setIsDirty}
                            />

                            <div className="rounded-xl border border-border bg-card p-5 shadow-sm">
                                <Field
                                    id="revision-title"
                                    label="Title"
                                    error={errors.title}
                                >
                                    <Input
                                        name="title"
                                        required
                                        maxLength={200}
                                        value={title}
                                        onChange={(event) =>
                                            setTitle(event.target.value)
                                        }
                                    />
                                </Field>
                            </div>

                            <div className="rounded-xl border border-border bg-card p-5 shadow-sm">
                                <div className="mb-4 flex items-center justify-between">
                                    <h2 className="font-medium">Markdown</h2>

                                    <ToggleGroup
                                        type="single"
                                        variant="outline"
                                        value={mode}
                                        onValueChange={(value) => {
                                            if (
                                                value === 'edit' ||
                                                value === 'preview'
                                            ) {
                                                setMode(value);
                                            }
                                        }}
                                        aria-label="Editor mode"
                                    >
                                        <ToggleGroupItem value="edit">
                                            Edit
                                        </ToggleGroupItem>
                                        <ToggleGroupItem value="preview">
                                            Preview
                                        </ToggleGroupItem>
                                    </ToggleGroup>
                                </div>

                                {mode === 'edit' ? (
                                    <Field
                                        id="revision-body"
                                        label="Body (Markdown)"
                                        error={errors.body_markdown}
                                    >
                                        <Textarea
                                            name="body_markdown"
                                            required
                                            rows={22}
                                            className="font-mono text-sm"
                                            value={body}
                                            onChange={(event) =>
                                                setBody(event.target.value)
                                            }
                                        />
                                    </Field>
                                ) : (
                                    <div
                                        aria-live="polite"
                                        className="rounded-md border border-border p-4"
                                    >
                                        <ContentMarkdown body={body} />
                                    </div>
                                )}
                            </div>

                            <div className="flex justify-end">
                                <Button type="submit" disabled={processing}>
                                    {processing ? 'Saving…' : 'Save Draft'}
                                </Button>
                            </div>
                        </>
                    )}
                </Form>
            </div>
        </>
    );
}
