import * as DialogPrimitive from '@radix-ui/react-dialog';
import { XIcon } from 'lucide-react';
import type { ImgHTMLAttributes } from 'react';

import {
    Dialog,
    DialogOverlay,
    DialogPortal,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { cn } from '@/lib/utils';

type ZoomableImageProps = Pick<
    ImgHTMLAttributes<HTMLImageElement>,
    'src' | 'alt' | 'width' | 'height' | 'loading' | 'fetchPriority'
> & {
    className?: string;
};

/**
 * Render a product screenshot that opens centered and enlarged in a dialog
 * on click, dismissible via the top-right close button, outside click, or
 * Escape. `alt` must be non-empty (decorative images should stay plain
 * `<img>` tags, not this component).
 */
export function ZoomableImage({
    src,
    alt,
    width,
    height,
    loading = 'lazy',
    fetchPriority,
    className,
}: ZoomableImageProps) {
    return (
        <Dialog>
            <DialogTrigger asChild>
                <button
                    type="button"
                    aria-label={`Zoom in on ${alt}`}
                    className="block w-full cursor-zoom-in rounded-lg focus-visible:ring-2 focus-visible:ring-marketing-focus focus-visible:ring-offset-2 focus-visible:outline-hidden"
                >
                    <img
                        src={src}
                        alt={alt}
                        width={width}
                        height={height}
                        loading={loading}
                        fetchPriority={fetchPriority}
                        decoding="async"
                        className={className}
                    />
                </button>
            </DialogTrigger>
            <DialogPortal data-slot="dialog-portal">
                <DialogOverlay className="backdrop-blur-sm" />
                <DialogPrimitive.Content
                    data-slot="dialog-content"
                    className={cn(
                        'fixed top-[50%] left-[50%] z-50 w-full max-w-[min(92vw,1600px)] translate-x-[-50%] translate-y-[-50%] border-none bg-transparent p-0 shadow-none duration-200 data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=closed]:zoom-out-95 data-[state=open]:animate-in data-[state=open]:fade-in-0 data-[state=open]:zoom-in-95',
                    )}
                >
                    <DialogTitle className="sr-only">{alt}</DialogTitle>
                    <img
                        src={src}
                        alt={alt}
                        className="block h-auto max-h-[92vh] w-full rounded-lg object-contain"
                    />
                    <DialogPrimitive.Close className="absolute -top-3 -right-3 rounded-full bg-white p-1.5 text-marketing-ink opacity-90 shadow-md ring-offset-background transition-opacity hover:opacity-100 focus:ring-2 focus:ring-ring focus:ring-offset-2 focus:outline-hidden sm:top-2 sm:right-2">
                        <XIcon className="size-5" aria-hidden="true" />
                        <span className="sr-only">Close</span>
                    </DialogPrimitive.Close>
                </DialogPrimitive.Content>
            </DialogPortal>
        </Dialog>
    );
}
