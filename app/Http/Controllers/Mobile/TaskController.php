<?php

namespace App\Http\Controllers\Mobile;

use App\Models\User;
use App\Support\Mobile\MobileTask;
use App\Support\Mobile\MobileTaskAggregator;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The dedicated Tasks tab: the full, grouped task list. Calls the same
 * `MobileTaskAggregator` instance as `HomeController` (Spec 7 Backend/API)
 * so Home's top-5 preview and this full list never drift apart on ordering.
 */
class TaskController extends MobileController
{
    /**
     * Type display order and labels, fixed regardless of which groups a
     * given actor happens to have permission to see.
     *
     * @var array<string, string>
     */
    private const array TYPE_LABELS = [
        'receive' => 'Receive',
        'count' => 'Count',
        'ship' => 'Ship',
        'receive_transfer' => 'Receive transfer',
        'restock' => 'Restock',
    ];

    public function __construct(
        private readonly MobileTaskAggregator $taskAggregator,
    ) {}

    public function index(Request $request): Response
    {
        $organization = $this->activeOrganization($request);

        if ($organization === null) {
            return Inertia::render('mobile/tasks/index', [
                'activeLocation' => null,
                'taskGroups' => [],
                'navBadgeCounts' => [],
            ]);
        }

        $location = $this->requireActiveLocation($request);
        $actor = $request->user();

        $tasks = ($location !== null && $actor instanceof User)
            ? $this->taskAggregator->forLocation($organization, $location, $actor)
            : collect();

        return Inertia::render('mobile/tasks/index', [
            'activeLocation' => $location !== null ? [
                'id' => $location->id,
                'name' => $location->name,
                'code' => $location->code,
            ] : null,
            'taskGroups' => $this->groupByType($tasks),
            'navBadgeCounts' => ['tasks' => $tasks->count()],
        ]);
    }

    /**
     * Group already urgency-sorted tasks by type, preserving that order
     * within each group, in a fixed type display order.
     *
     * @param  Collection<int, MobileTask>  $tasks
     * @return list<array{type: string, label: string, tasks: list<array<string, mixed>>}>
     */
    private function groupByType(Collection $tasks): array
    {
        $byType = $tasks->groupBy('type');

        $groups = [];

        foreach (self::TYPE_LABELS as $type => $label) {
            $typeTasks = $byType->get($type);

            if ($typeTasks === null || $typeTasks->isEmpty()) {
                continue;
            }

            $groups[] = [
                'type' => $type,
                'label' => $label,
                'tasks' => array_values(
                    $typeTasks->map(fn (MobileTask $task): array => $task->toArray())->all(),
                ),
            ];
        }

        return $groups;
    }
}
