<?php

namespace App\Http\Controllers\Mobile;

use App\Models\Organization;
use App\Models\User;
use App\Support\Mobile\MobileTaskAggregator;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends MobileController
{
    public function __construct(
        private readonly MobileTaskAggregator $taskAggregator,
    ) {}

    /**
     * Task-first mobile Home: the top 5 tasks across all types, under
     * "Today's work" (Spec 7 Behavior step 2), or the empty state a new
     * tenant sees before creating a location.
     */
    public function index(Request $request): Response
    {
        $organization = $this->activeOrganization($request);

        if ($organization === null) {
            return Inertia::render('mobile/home', [
                'activeLocation' => null,
                'organization' => null,
                'hasLocations' => false,
                'navBadgeCounts' => [],
                'tasks' => [],
            ]);
        }

        $location = $this->requireActiveLocation($request);
        $actor = $request->user();

        $allTasks = ($location !== null && $actor instanceof User)
            ? $this->taskAggregator->forLocation($organization, $location, $actor)
            : collect();

        return Inertia::render('mobile/home', [
            'activeLocation' => $location !== null ? [
                'id' => $location->id,
                'name' => $location->name,
                'code' => $location->code,
            ] : null,
            'organization' => $this->organizationData($organization),
            'hasLocations' => $organization->locations()
                ->where('active', true)
                ->exists(),
            'navBadgeCounts' => ['tasks' => $allTasks->count()],
            'tasks' => $allTasks->take(5)->map(fn ($task) => $task->toArray())->values()->all(),
        ]);
    }

    /**
     * Serialize only organization data required by the mobile shell.
     *
     * @return array{id: int, name: string, slug: string}
     */
    private function organizationData(Organization $organization): array
    {
        return [
            'id' => $organization->id,
            'name' => $organization->name,
            'slug' => $organization->slug,
        ];
    }
}
