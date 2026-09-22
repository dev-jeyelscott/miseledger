import { Form, Head } from '@inertiajs/react';

import PlatformContentController from '@/actions/App/Http/Controllers/Platform/PlatformContentController';
import { PreviousPageButton } from '@/components/navigation/previous-page-button';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { Field } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';
import { Textarea } from '@/components/ui/textarea';

type Option = {
    value: string;
    label: string;
};

type Props = {
    kindOptions: Option[];
};

/** Create a new marketing/legal content page and its first draft revision. */
export default function PlatformContentCreate({ kindOptions }: Props) {
    return (
        <>
            <Head title="New content page" />

            <div className="flex flex-1 flex-col gap-6 p-4 sm:p-6">
                <PageHeader
                    title="New content page"
                    description="Markdown is the only supported authoring format. The page starts as an unpublished draft that only platform admins can see."
                    actions={
                        <PreviousPageButton
                            variant="outline"
                            fallback={PlatformContentController.index().url}
                        >
                            Back to content
                        </PreviousPageButton>
                    }
                />

                <Form
                    {...PlatformContentController.store.form()}
                    className="space-y-6"
                >
                    {({ processing, errors }) => (
                        <div className="rounded-xl border border-border bg-card p-5 shadow-sm">
                            <div className="grid gap-5 sm:grid-cols-2">
                                <Field
                                    id="content-kind"
                                    label="Kind"
                                    error={errors.kind}
                                >
                                    <NativeSelect
                                        name="kind"
                                        defaultValue={
                                            kindOptions[0]?.value ?? ''
                                        }
                                    >
                                        {kindOptions.map((option) => (
                                            <option
                                                key={option.value}
                                                value={option.value}
                                            >
                                                {option.label}
                                            </option>
                                        ))}
                                    </NativeSelect>
                                </Field>

                                <Field
                                    id="content-title"
                                    label="Title"
                                    error={errors.title}
                                >
                                    <Input
                                        name="title"
                                        required
                                        maxLength={200}
                                    />
                                </Field>

                                <Field
                                    id="content-key"
                                    label="Internal key"
                                    helper="Stable identifier, e.g. legal.terms_of_service."
                                    error={errors.key}
                                >
                                    <Input
                                        name="key"
                                        required
                                        maxLength={120}
                                    />
                                </Field>

                                <Field
                                    id="content-slug"
                                    label="Public slug"
                                    helper="Used in the public URL once published."
                                    error={errors.slug}
                                >
                                    <Input
                                        name="slug"
                                        required
                                        maxLength={160}
                                    />
                                </Field>
                            </div>

                            <div className="mt-5">
                                <Field
                                    id="content-body"
                                    label="Markdown"
                                    error={errors.body_markdown}
                                >
                                    <Textarea
                                        name="body_markdown"
                                        required
                                        rows={16}
                                        className="font-mono text-sm"
                                    />
                                </Field>
                            </div>

                            <div className="mt-5 flex justify-end">
                                <Button type="submit" disabled={processing}>
                                    {processing ? 'Saving…' : 'Save draft'}
                                </Button>
                            </div>
                        </div>
                    )}
                </Form>
            </div>
        </>
    );
}
