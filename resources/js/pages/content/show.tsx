import { Head } from '@inertiajs/react';

import { ContentMarkdown } from '@/components/content-markdown';

type Props = {
    title: string;
    bodyMarkdown: string;
    publishedAt: string | null;
};

/**
 * Public rendering for a published marketing/legal CMS page. Only ever
 * receives the exact published revision: no draft, actor, or internal ID
 * props (POC-V6.7).
 */
export default function PublicContentShow({
    title,
    bodyMarkdown,
    publishedAt,
}: Props) {
    return (
        <>
            <Head title={title} />

            <div className="mx-auto max-w-3xl px-4 py-12 sm:px-6">
                <h1 className="mb-2 text-3xl font-semibold tracking-tight">
                    {title}
                </h1>

                {publishedAt ? (
                    <p className="mb-8 text-sm text-muted-foreground">
                        Last updated{' '}
                        {new Date(publishedAt).toLocaleDateString()}
                    </p>
                ) : null}

                <ContentMarkdown body={bodyMarkdown} />
            </div>
        </>
    );
}
