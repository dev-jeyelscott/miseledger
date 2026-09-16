import { Link, useForm } from '@inertiajs/react';
import { AlertCircle, Loader2, Upload, X } from 'lucide-react';
import { useState } from 'react';
import type { ReactNode } from 'react';

import InputError from '@/components/input-error';
import { PageHeader } from '@/components/page-header';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Field } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

/** Renders the problem report submission form and manages its Inertia form state. */
export default function CreateProblemReport() {
    const {
        data,
        setData,
        post,
        processing,
        progress,
        errors,
        setError,
        clearErrors,
    } = useForm({
        title: '',
        description: '',
        screenshots: [] as File[],
    });

    const [previewUrls, setPreviewUrls] = useState<string[]>([]);

    const indexedErrors = errors as Record<string, string | undefined>;
    const submissionError = indexedErrors.submission;
    const screenshotsError =
        errors.screenshots ??
        Object.keys(indexedErrors)
            .filter((key) => /^screenshots\.\d+$/.test(key))
            .sort()
            .map((key) => indexedErrors[key])[0];

    /** Adds selected screenshots while enforcing the client-side five-file limit. */
    const handleScreenshotsChange = (
        e: React.ChangeEvent<HTMLInputElement>,
    ) => {
        const files = Array.from(e.target.files || []);
        const totalFiles = data.screenshots.length + files.length;

        if (totalFiles > 5) {
            setError('screenshots', 'Maximum 5 screenshots allowed');

            return;
        }

        clearErrors('screenshots');

        const newScreenshots = [...data.screenshots, ...files];
        setData('screenshots', newScreenshots);

        const newPreviews = files.map((file) => URL.createObjectURL(file));
        setPreviewUrls([...previewUrls, ...newPreviews]);
    };

    /** Removes a selected screenshot and clears the resolved screenshot error. */
    const removeScreenshot = (index: number) => {
        const newScreenshots = data.screenshots.filter(
            (_, currentIndex) => currentIndex !== index,
        );
        const newPreviews = previewUrls.filter(
            (_, currentIndex) => currentIndex !== index,
        );

        setData('screenshots', newScreenshots);
        setPreviewUrls(newPreviews);
        clearErrors('screenshots');
    };

    /** Submits the current Inertia form state to create the problem report. */
    const handleSubmit = (e: React.FormEvent<HTMLFormElement>) => {
        e.preventDefault();

        post('/problem-reports');
    };

    return (
        <div className="space-y-6">
            <div className="flex items-center justify-between">
                <PageHeader
                    title="Report a Problem"
                    description="Help us improve by reporting any issues you encounter"
                />
                <Button asChild variant="outline">
                    <Link href="/problem-reports">My Reports</Link>
                </Button>
            </div>

            {submissionError && (
                <Alert variant="destructive">
                    <AlertCircle className="h-4 w-4" />
                    <AlertDescription>{submissionError}</AlertDescription>
                </Alert>
            )}

            {!submissionError && Object.keys(errors).length > 0 && (
                <Alert variant="destructive">
                    <AlertCircle className="h-4 w-4" />
                    <AlertDescription>
                        Please fix the errors below and try again
                    </AlertDescription>
                </Alert>
            )}

            <form onSubmit={handleSubmit} className="max-w-2xl space-y-6">
                <Field id="title" label="Title (Optional)" error={errors.title}>
                    <Input
                        type="text"
                        maxLength={160}
                        placeholder="Brief summary of the problem"
                        value={data.title}
                        onChange={(e) => setData('title', e.target.value)}
                        disabled={processing}
                    />
                </Field>

                <Field
                    id="description"
                    label={
                        <>
                            Description{' '}
                            <span className="text-destructive">*</span>
                        </>
                    }
                    helper={`${data.description.length} / 10,000 characters`}
                    error={errors.description}
                >
                    <Textarea
                        maxLength={10000}
                        placeholder="Please describe the problem in detail..."
                        rows={6}
                        value={data.description}
                        onChange={(e) => setData('description', e.target.value)}
                        disabled={processing}
                        className="resize-none"
                    />
                </Field>

                <div className="space-y-2">
                    <Label htmlFor="screenshots">
                        Screenshots{' '}
                        <span className="text-sm text-muted-foreground">
                            (Optional, max 5 images)
                        </span>
                    </Label>

                    {data.screenshots.length > 0 && (
                        <div className="grid grid-cols-2 gap-4 sm:grid-cols-3">
                            {previewUrls.map((url, index) => (
                                <div
                                    key={url}
                                    className="relative aspect-video overflow-hidden rounded-lg border border-border bg-muted"
                                >
                                    <img
                                        src={url}
                                        alt={`Screenshot ${index + 1}`}
                                        className="h-full w-full object-cover"
                                    />
                                    <Button
                                        type="button"
                                        variant="destructive"
                                        size="icon"
                                        onClick={() => removeScreenshot(index)}
                                        disabled={processing}
                                        aria-label={`Remove screenshot ${index + 1}`}
                                        className="absolute top-1 right-1 size-11"
                                    >
                                        <X
                                            className="h-4 w-4"
                                            aria-hidden="true"
                                        />
                                    </Button>
                                </div>
                            ))}
                        </div>
                    )}

                    <div className="relative">
                        <input
                            id="screenshots"
                            type="file"
                            multiple
                            accept="image/jpeg,image/png,image/webp"
                            onChange={handleScreenshotsChange}
                            disabled={
                                processing || data.screenshots.length >= 5
                            }
                            aria-invalid={screenshotsError ? true : undefined}
                            aria-describedby={
                                screenshotsError
                                    ? 'screenshots-error'
                                    : undefined
                            }
                            className="peer sr-only"
                        />
                        <Label
                            htmlFor="screenshots"
                            className="flex cursor-pointer flex-col items-center justify-center gap-2 rounded-lg border-2 border-dashed border-border px-6 py-8 transition-colors peer-focus-visible:border-ring peer-focus-visible:ring-[3px] peer-focus-visible:ring-ring/50 hover:border-muted-foreground"
                        >
                            <Upload
                                className="h-6 w-6 text-muted-foreground"
                                aria-hidden="true"
                            />
                            <span className="text-sm font-medium text-foreground">
                                Click to upload screenshots
                            </span>
                            <span className="text-xs text-muted-foreground">
                                JPEG, PNG, WebP up to 5 MB each
                            </span>
                        </Label>
                    </div>

                    <InputError
                        id="screenshots-error"
                        message={screenshotsError}
                    />
                </div>

                <div className="space-y-2">
                    <div className="flex gap-4">
                        <Button
                            type="submit"
                            disabled={processing || !data.description.trim()}
                        >
                            {processing ? (
                                <>
                                    <Loader2
                                        className="mr-2 h-4 w-4 animate-spin"
                                        aria-hidden="true"
                                    />
                                    Submitting...
                                </>
                            ) : (
                                'Submit Report'
                            )}
                        </Button>
                    </div>

                    {progress && (
                        <div className="max-w-xs space-y-1">
                            <progress
                                value={progress.percentage ?? 0}
                                max={100}
                                className="h-2 w-full"
                            />
                            <p
                                role="status"
                                aria-live="polite"
                                className="text-xs text-muted-foreground"
                            >
                                Uploading… {progress.percentage ?? 0}%
                            </p>
                        </div>
                    )}
                </div>
            </form>
        </div>
    );
}

/** Wraps the create page with the canonical application layout and breadcrumbs. */
CreateProblemReport.layout = (page: ReactNode) => (
    <AppLayout
        breadcrumbs={[
            {
                title: 'My Reports',
                href: '/problem-reports',
            } satisfies BreadcrumbItem,
            {
                title: 'Submit Report',
                href: '/problem-reports/create',
            } satisfies BreadcrumbItem,
        ]}
    >
        {page}
    </AppLayout>
);
