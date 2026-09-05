import { Search } from 'lucide-react';
import * as React from 'react';

import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';

/** Render a search input with a decorative icon while preserving native input props. */
function SearchInput({
    className,
    ...props
}: React.ComponentProps<typeof Input>) {
    return (
        <div className="relative">
            <Search
                className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                aria-hidden="true"
            />
            <Input className={cn('pl-9', className)} {...props} />
        </div>
    );
}

export { SearchInput };
