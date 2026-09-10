import { useForm, Link } from '@inertiajs/react';
import { useState } from 'react';
import type { ReactNode } from 'react';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { AlertCircle, Loader2, Upload, X } from 'lucide-react';
import { Alert, AlertDescription } from '@/components/ui/alert';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

export default function CreateProblemReport() {
    const { data, setData, post, processing, errors } = useForm({
        title: '',
        description: '',
        screenshots: [] as File[],
    });

    const [previewUrls, setPreviewUrls] = useState<string[]>([]);

    const handleScreenshotsChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        const files = Array.from(e.target.files || []);
        const totalFiles = data.screenshots.length + files.length;

        if (totalFiles > 5) {
            errors.screenshots = 'Maximum 5 screenshots allowed';
            return;
        }

        const newScreenshots = [...data.screenshots, ...files];
        setData('screenshots', newScreenshots);

        const newPreviews = files.map(file => URL.createObjectURL(file));
        setPreviewUrls([...previewUrls, ...newPreviews]);
    };

    const removeScreenshot = (index: number) => {
        const newScreenshots = data.screenshots.filter((_, i) => i !== index);
        const newPreviews = previewUrls.filter((_, i) => i !== index);

        setData('screenshots', newScreenshots);
        setPreviewUrls(newPreviews);

        if (newPreviews.length === 0) {
            delete errors.screenshots;
        }
    };

    const handleSubmit = (e: React.FormEvent<HTMLFormElement>) => {
        e.preventDefault();

        const formData = new FormData();
        formData.append('title', data.title);
        formData.append('description', data.description);

        data.screenshots.forEach((file) => {
            formData.append('screenshots[]', file);
        });

        post('/problem-reports', {
            data: formData,
            headers: {
                'Content-Type': 'multipart/form-data',
            },
        });
    };

    return (
            <div className="space-y-6">
                <div className="flex justify-between items-center">
                    <PageHeader title="Report a Problem" description="Help us improve by reporting any issues you encounter" />
                    <Link href="/problem-reports">
                        <Button variant="outline">My Reports</Button>
                    </Link>
                </div>

                {Object.keys(errors).length > 0 && (
                    <Alert variant="destructive">
                        <AlertCircle className="h-4 w-4" />
                        <AlertDescription>
                            Please fix the errors below and try again
                        </AlertDescription>
                    </Alert>
                )}

                <form onSubmit={handleSubmit} className="space-y-6 max-w-2xl">
                    <div className="space-y-2">
                        <Label htmlFor="title">Title (Optional)</Label>
                        <Input
                            id="title"
                            type="text"
                            maxLength={160}
                            placeholder="Brief summary of the problem"
                            value={data.title}
                            onChange={(e) => setData('title', e.target.value)}
                            disabled={processing}
                        />
                        {errors.title && (
                            <p className="text-sm text-red-500">{errors.title}</p>
                        )}
                    </div>

                    <div className="space-y-2">
                        <Label htmlFor="description">
                            Description <span className="text-red-500">*</span>
                        </Label>
                        <Textarea
                            id="description"
                            maxLength={10000}
                            placeholder="Please describe the problem in detail..."
                            rows={6}
                            value={data.description}
                            onChange={(e) => setData('description', e.target.value)}
                            disabled={processing}
                            className="resize-none"
                        />
                        <div className="flex justify-between text-sm text-gray-500">
                            <span>
                                {data.description.length} / 10,000 characters
                            </span>
                        </div>
                        {errors.description && (
                            <p className="text-sm text-red-500">{errors.description}</p>
                        )}
                    </div>

                    <div className="space-y-2">
                        <Label htmlFor="screenshots">
                            Screenshots <span className="text-gray-500 text-sm">(Optional, max 5 images)</span>
                        </Label>

                        {data.screenshots.length > 0 && (
                            <div className="grid grid-cols-2 gap-4 sm:grid-cols-3">
                                {previewUrls.map((url, index) => (
                                    <div
                                        key={index}
                                        className="relative aspect-video rounded-lg overflow-hidden bg-gray-100 border border-gray-200"
                                    >
                                        <img
                                            src={url}
                                            alt={`Screenshot ${index + 1}`}
                                            className="w-full h-full object-cover"
                                        />
                                        <button
                                            type="button"
                                            onClick={() => removeScreenshot(index)}
                                            disabled={processing}
                                            className="absolute top-1 right-1 bg-red-500 hover:bg-red-600 text-white rounded p-1"
                                        >
                                            <X className="h-4 w-4" />
                                        </button>
                                    </div>
                                ))}
                            </div>
                        )}

                        <div className="relative">
                            <input
                                type="file"
                                multiple
                                accept="image/jpeg,image/png,image/webp"
                                onChange={handleScreenshotsChange}
                                disabled={processing || data.screenshots.length >= 5}
                                className="sr-only"
                                id="screenshots"
                            />
                            <Label
                                htmlFor="screenshots"
                                className="flex flex-col items-center justify-center gap-2 px-6 py-8 rounded-lg border-2 border-dashed border-gray-300 hover:border-gray-400 cursor-pointer transition-colors"
                            >
                                <Upload className="h-6 w-6 text-gray-400" />
                                <span className="text-sm font-medium text-gray-700">
                                    Click to upload screenshots
                                </span>
                                <span className="text-xs text-gray-500">
                                    JPEG, PNG, WebP up to 5 MB each
                                </span>
                            </Label>
                        </div>

                        {errors.screenshots && (
                            <p className="text-sm text-red-500">{errors.screenshots}</p>
                        )}
                    </div>

                    <div className="flex gap-4">
                        <Button
                            type="submit"
                            disabled={processing || !data.description.trim()}
                        >
                            {processing ? (
                                <>
                                    <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                                    Submitting...
                                </>
                            ) : (
                                'Submit Report'
                            )}
                        </Button>
                    </div>
                </form>
            </div>
    );
}

CreateProblemReport.layout = (page: ReactNode) => (
    <AppLayout
        breadcrumbs={[
            { title: 'My Reports', href: '/problem-reports' } satisfies BreadcrumbItem,
            { title: 'Submit Report', href: '/problem-reports/create' } satisfies BreadcrumbItem,
        ]}
    >
        {page}
    </AppLayout>
);
