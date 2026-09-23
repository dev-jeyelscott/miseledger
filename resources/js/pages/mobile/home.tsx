import { Head, Link } from '@inertiajs/react';
import { Boxes, ListChecks, ScanLine, Sparkles } from 'lucide-react';

import { EmptyState } from '@/components/empty-state';
import { TaskCard } from '@/components/mobile/task-card';
import mobile from '@/routes/mobile';
import organizations from '@/routes/organizations';
import type {
    MobileActiveLocation,
    MobileOrganizationSummary,
    MobileTask,
} from '@/types/mobile';

type HomeProps = {
    activeLocation: MobileActiveLocation;
    hasLocations: boolean;
    organization: MobileOrganizationSummary | null;
    tasks: MobileTask[];
};

export default function MobileHome({
    activeLocation,
    hasLocations,
    organization,
    tasks,
}: HomeProps) {
    return (
        <>
            <Head title="Mobile" />

            {organization === null ? (
                <EmptyState
                    icon={Boxes}
                    title="No organization yet"
                    description="Set up your organization on desktop to start using MiseLedger."
                    action={
                        <Link
                            href={organizations.create.url()}
                            className="text-sm font-medium text-primary underline-offset-2 hover:underline"
                        >
                            Create organization
                        </Link>
                    }
                />
            ) : !hasLocations ? (
                <EmptyState
                    icon={Boxes}
                    title="No locations configured"
                    description="Add a location on desktop before you can use MiseLedger on mobile."
                />
            ) : (
                <div className="space-y-6">
                    <div>
                        <h1 className="text-lg font-semibold">Home</h1>
                        <p className="text-sm text-muted-foreground">
                            Working at {activeLocation?.name}.
                        </p>
                    </div>

                    <div className="space-y-2">
                        <h2 className="text-sm font-semibold text-muted-foreground">
                            Today&apos;s work
                        </h2>

                        {tasks.length === 0 ? (
                            <EmptyState
                                icon={Sparkles}
                                title="You're all caught up"
                                description="No receiving, counts, transfers, or restock alerts are waiting at this location."
                            />
                        ) : (
                            <ul className="space-y-2">
                                {tasks.map((task) => (
                                    <li key={`${task.type}-${task.href}`}>
                                        <TaskCard task={task} />
                                    </li>
                                ))}
                            </ul>
                        )}

                        {tasks.length > 0 ? (
                            <Link
                                href={mobile.tasks.index.url()}
                                className="inline-flex items-center gap-1 text-sm font-medium text-primary underline-offset-2 hover:underline"
                            >
                                <ListChecks
                                    className="size-4"
                                    aria-hidden="true"
                                />
                                View all tasks
                            </Link>
                        ) : null}
                    </div>

                    <div className="grid grid-cols-2 gap-2">
                        <Link
                            href={mobile.scan.index.url()}
                            className="flex min-h-[44px] items-center justify-center gap-2 rounded-md border border-input px-4 py-3 font-medium hover:bg-accent"
                        >
                            <ScanLine className="size-4" aria-hidden="true" />
                            Scan
                        </Link>
                        <Link
                            href={mobile.home.url()}
                            className="flex min-h-[44px] items-center justify-center gap-2 rounded-md border border-input px-4 py-3 font-medium hover:bg-accent"
                        >
                            <Boxes className="size-4" aria-hidden="true" />
                            Stock
                        </Link>
                    </div>
                </div>
            )}
        </>
    );
}
