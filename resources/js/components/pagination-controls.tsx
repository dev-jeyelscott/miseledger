import { Link, type InertiaLinkProps } from '@inertiajs/react';

import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

type PaginationControlsProps = {
    className?: string;
    currentPage: number;
    from?: number | null;
    itemLabel?: string;
    lastPage: number;
    nextPageUrl: InertiaLinkProps['href'] | null;
    preserveScroll?: InertiaLinkProps['preserveScroll'];
    preserveState?: InertiaLinkProps['preserveState'];
    previousPageUrl: InertiaLinkProps['href'] | null;
    to?: number | null;
    total: number;
};

/** Render generic server-pagination navigation without owning query or domain state. */
function PaginationControls({
    className,
    currentPage,
    from,
    itemLabel = 'results',
    lastPage,
    nextPageUrl,
    preserveScroll,
    preserveState,
    previousPageUrl,
    to,
    total,
}: PaginationControlsProps) {
    if (lastPage <= 1) {
        return null;
    }

    const range = `Showing ${from ?? 0} to ${to ?? 0} of ${total.toLocaleString()} ${itemLabel}`;

    return (
        <nav
            aria-label="Pagination"
            data-slot="pagination-controls"
            className={cn(
                'flex flex-col gap-3 border-t border-border px-4 py-3 sm:flex-row sm:items-center sm:justify-between',
                className,
            )}
        >
            <p className="text-sm text-muted-foreground">{range}</p>
            <div className="flex items-center gap-2">
                {previousPageUrl === null ? (
                    <Button size="sm" variant="outline" disabled>
                        Previous
                    </Button>
                ) : (
                    <Button size="sm" variant="outline" asChild>
                        <Link
                            href={previousPageUrl}
                            preserveScroll={preserveScroll}
                            preserveState={preserveState}
                        >
                            Previous
                        </Link>
                    </Button>
                )}
                <span className="text-sm text-muted-foreground">
                    Page {currentPage} of {lastPage}
                </span>
                {nextPageUrl === null ? (
                    <Button size="sm" variant="outline" disabled>
                        Next
                    </Button>
                ) : (
                    <Button size="sm" variant="outline" asChild>
                        <Link
                            href={nextPageUrl}
                            preserveScroll={preserveScroll}
                            preserveState={preserveState}
                        >
                            Next
                        </Link>
                    </Button>
                )}
            </div>
        </nav>
    );
}

export { PaginationControls };
export type { PaginationControlsProps };
