import { Link } from '@inertiajs/react';
import {
    ArrowLeftRight,
    ClipboardList,
    PackagePlus,
    TriangleAlert,
} from 'lucide-react';
import type { ComponentType } from 'react';

import { cn } from '@/lib/utils';
import type {
    MobileTask,
    MobileTaskType,
    MobileTaskUrgency,
} from '@/types/mobile';

const TYPE_ICON: Record<
    MobileTaskType,
    ComponentType<{ className?: string }>
> = {
    receive: PackagePlus,
    count: ClipboardList,
    ship: ArrowLeftRight,
    receive_transfer: ArrowLeftRight,
    restock: TriangleAlert,
};

/**
 * Colored left-border/badge per urgency band, not a full-row color wash
 * (Spec 7 Frontend/UI/UX: "keeps the list scannable when several urgent
 * items stack").
 */
const URGENCY_STYLE: Record<
    MobileTaskUrgency,
    { border: string; badge: string; label: string }
> = {
    overdue: {
        border: 'border-l-destructive',
        badge: 'bg-destructive/10 text-destructive',
        label: 'Overdue',
    },
    in_progress: {
        border: 'border-l-amber-500',
        badge: 'bg-amber-500/10 text-amber-600 dark:text-amber-400',
        label: 'In progress',
    },
    ready: {
        border: 'border-l-primary',
        badge: 'bg-primary/10 text-primary',
        label: 'Ready',
    },
    attention: {
        border: 'border-l-muted-foreground',
        badge: 'bg-muted text-muted-foreground',
        label: 'Attention',
    },
};

/** Formats an ISO timestamp as a short relative-time label ("3d ago", "2h ago"). */
function relativeTime(iso: string): string {
    const diffMs = Date.now() - new Date(iso).getTime();
    const minutes = Math.round(diffMs / 60_000);

    if (minutes < 1) {
        return 'just now';
    }

    if (minutes < 60) {
        return `${minutes}m ago`;
    }

    const hours = Math.round(minutes / 60);

    if (hours < 24) {
        return `${hours}h ago`;
    }

    const days = Math.round(hours / 24);

    return `${days}d ago`;
}

type TaskCardProps = {
    task: MobileTask;
};

/** One operational task, shared by mobile Home's preview and the Tasks tab. */
export function TaskCard({ task }: TaskCardProps) {
    const Icon = TYPE_ICON[task.type];
    const style = URGENCY_STYLE[task.urgency];

    return (
        <Link
            href={task.href}
            className={cn(
                'flex min-h-[44px] items-start gap-3 rounded-md border border-l-4 border-input bg-card px-4 py-3 text-left hover:bg-accent',
                style.border,
            )}
        >
            <Icon
                className="mt-0.5 size-5 shrink-0 text-muted-foreground"
                aria-hidden="true"
            />

            <span className="min-w-0 flex-1 space-y-1">
                <span className="flex items-center justify-between gap-2">
                    <span className="truncate font-medium">{task.title}</span>
                    <span
                        className={cn(
                            'shrink-0 rounded-md px-1.5 py-0.5 text-[10px] font-medium whitespace-nowrap',
                            style.badge,
                        )}
                    >
                        {style.label}
                    </span>
                </span>
                <span className="block truncate text-sm text-muted-foreground">
                    {task.subtitle}
                </span>
                <span className="block text-xs text-muted-foreground">
                    {relativeTime(task.createdAt)}
                </span>
            </span>
        </Link>
    );
}
