import { Head } from '@inertiajs/react';
import { Boxes, Sparkles } from 'lucide-react';

import { EmptyState } from '@/components/empty-state';
import { TaskCard } from '@/components/mobile/task-card';
import type { MobileActiveLocation, MobileTaskGroup } from '@/types/mobile';

type TasksIndexProps = {
    activeLocation: MobileActiveLocation;
    taskGroups: MobileTaskGroup[];
};

/** The full, grouped task list (Spec 7): same aggregator and sort as Home's preview. */
export default function TasksIndex({
    activeLocation,
    taskGroups,
}: TasksIndexProps) {
    if (activeLocation === null) {
        return (
            <>
                <Head title="Tasks" />
                <EmptyState
                    icon={Boxes}
                    title="No locations configured"
                    description="Add a location on desktop before you can use MiseLedger on mobile."
                />
            </>
        );
    }

    return (
        <>
            <Head title="Tasks" />

            <div className="space-y-6">
                <div>
                    <h1 className="text-lg font-semibold">Tasks</h1>
                    <p className="text-sm text-muted-foreground">
                        {activeLocation.name}
                    </p>
                </div>

                {taskGroups.length === 0 ? (
                    <EmptyState
                        icon={Sparkles}
                        title="You're all caught up"
                        description="No receiving, counts, transfers, or restock alerts are waiting at this location."
                    />
                ) : (
                    <div className="space-y-6">
                        {taskGroups.map((group) => (
                            <section key={group.type} className="space-y-2">
                                <div className="flex items-center gap-2">
                                    <h2 className="text-sm font-semibold text-muted-foreground">
                                        {group.label}
                                    </h2>
                                    <span className="rounded-full bg-muted px-2 py-0.5 text-xs font-medium text-muted-foreground">
                                        {group.tasks.length}
                                    </span>
                                </div>

                                <ul className="space-y-2">
                                    {group.tasks.map((task) => (
                                        <li key={task.href}>
                                            <TaskCard task={task} />
                                        </li>
                                    ))}
                                </ul>
                            </section>
                        ))}
                    </div>
                )}
            </div>
        </>
    );
}
