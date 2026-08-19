<?php

namespace App\Http\Controllers\Recipes;

use App\Actions\Recipes\SaveRecipe;
use App\Enums\OrganizationPermission;
use App\Enums\RecipeType;
use App\Enums\RecipeVersionStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Recipes\SaveRecipeRequest;
use App\Models\Organization;
use App\Models\Recipe;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class RecipeController extends Controller
{
    /**
     * List recipe identities with tenant-scoped operational filters and
     * version-coverage metadata. Cost values remain on the location-aware
     * recipe costing screen.
     */
    public function index(Request $request): Response
    {
        $organization = $this->activeOrganization($request);

        Gate::authorize(
            OrganizationPermission::RecipesView->value,
            $organization,
        );

        [$filters, $query] = $this->indexQuery($request, $organization);

        $paginatedRecipes = $this->applyIndexSort(
            $query,
            $filters['sort'],
            $filters['direction'],
        )
            ->paginate($filters['perPage'])
            ->withQueryString();

        $rows = collect($paginatedRecipes->items())
            ->map(
                fn (Recipe $recipe): array => $this->indexRowData($recipe),
            )
            ->values()
            ->all();

        return Inertia::render('recipes/index', [
            'rows' => $rows,
            'pagination' => [
                'currentPage' => $paginatedRecipes->currentPage(),
                'from' => $paginatedRecipes->firstItem(),
                'lastPage' => $paginatedRecipes->lastPage(),
                'nextPageUrl' => $paginatedRecipes->nextPageUrl(),
                'perPage' => $paginatedRecipes->perPage(),
                'previousPageUrl' => $paginatedRecipes->previousPageUrl(),
                'to' => $paginatedRecipes->lastItem(),
                'total' => $paginatedRecipes->total(),
            ],
            'summary' => $this->indexSummary($organization),
            'filters' => $filters,
            'canManage' => Gate::allows(
                OrganizationPermission::RecipesManage->value,
                $organization,
            ),
            'canViewCosts' => Gate::allows(
                OrganizationPermission::CostsView->value,
                $organization,
            ),
        ]);
    }

    public function store(
        SaveRecipeRequest $request,
        SaveRecipe $saveRecipe,
    ): RedirectResponse {
        $organization = $request->organization();

        if ($organization === null) {
            abort(403);
        }

        $saveRecipe->handle($organization, [
            'code' => (string) $request->validated('code'),
            'name' => (string) $request->validated('name'),
            'type' => RecipeType::from((string) $request->validated('type')),
            'active' => (bool) $request->validated('active'),
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Recipe created.'),
        ]);

        return redirect()->to(
            $request->returnTo() ?? route('recipes.index'),
        );
    }

    public function edit(
        Request $request,
        string $recipe,
    ): Response {
        $organization = $this->activeOrganization($request);

        Gate::authorize(
            OrganizationPermission::RecipesManage->value,
            $organization,
        );

        $recipeModel = $organization
            ->recipes()
            ->findOrFail($recipe);

        return Inertia::render('recipes/edit', [
            'recipe' => [
                'id' => $recipeModel->id,
                'code' => $recipeModel->code,
                'name' => $recipeModel->name,
                'type' => $recipeModel->type->value,
                'active' => $recipeModel->active,
            ],
        ]);
    }

    public function update(
        SaveRecipeRequest $request,
        string $recipe,
        SaveRecipe $saveRecipe,
    ): RedirectResponse {
        $organization = $request->organization();
        $recipeModel = $request->recipe();

        if ($organization === null || $recipeModel === null) {
            abort(403);
        }

        $saveRecipe->handle(
            $organization,
            [
                'code' => (string) $request->validated('code'),
                'name' => (string) $request->validated('name'),
                'type' => RecipeType::from((string) $request->validated('type')),
                'active' => (bool) $request->validated('active'),
            ],
            $recipeModel,
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Recipe updated.'),
        ]);

        return redirect()->to(
            $request->returnTo() ?? route('recipes.edit', $recipe),
        );
    }

    /**
     * Build the validated tenant-scoped query used by the Recipes index.
     *
     * @return array{
     *     0: array{
     *         search: string|null,
     *         type: 'all'|'menu_item'|'prepared_item'|'batch',
     *         activity: 'all'|'active'|'inactive',
     *         sort: 'name'|'type'|'activity'|'updated_at',
     *         direction: 'asc'|'desc',
     *         perPage: int
     *     },
     *     1: EloquentBuilder<Recipe>
     * }
     */
    private function indexQuery(
        Request $request,
        Organization $organization,
    ): array {
        $validated = $request->validate([
            'search' => [
                'nullable',
                'string',
                'max:120',
            ],
            'type' => [
                'nullable',
                Rule::in([
                    'all',
                    RecipeType::MenuItem->value,
                    RecipeType::PreparedItem->value,
                    RecipeType::Batch->value,
                ]),
            ],
            'activity' => [
                'nullable',
                Rule::in(['all', 'active', 'inactive']),
            ],
            'sort' => [
                'nullable',
                Rule::in(['name', 'type', 'activity', 'updated_at']),
            ],
            'direction' => [
                'nullable',
                Rule::in(['asc', 'desc']),
            ],
            'per_page' => [
                'nullable',
                'integer',
                Rule::in([10, 25, 50]),
            ],
        ]);

        $search = isset($validated['search'])
            ? trim((string) $validated['search'])
            : null;

        if ($search === '') {
            $search = null;
        }

        $type = match ($validated['type'] ?? 'all') {
            RecipeType::MenuItem->value => RecipeType::MenuItem->value,
            RecipeType::PreparedItem->value => RecipeType::PreparedItem->value,
            RecipeType::Batch->value => RecipeType::Batch->value,
            default => 'all',
        };

        $activity = match ($validated['activity'] ?? 'all') {
            'active' => 'active',
            'inactive' => 'inactive',
            default => 'all',
        };

        $sort = match ($validated['sort'] ?? 'name') {
            'type' => 'type',
            'activity' => 'activity',
            'updated_at' => 'updated_at',
            default => 'name',
        };

        $direction = ($validated['direction'] ?? 'asc') === 'desc'
            ? 'desc'
            : 'asc';

        $perPage = isset($validated['per_page'])
            ? (int) $validated['per_page']
            : 10;

        $query = Recipe::query()
            ->where('organization_id', $organization->id)
            ->withCount([
                'versions',
                'versions as published_versions_count' => static function ($query): void {
                    $query->where(
                        'status',
                        RecipeVersionStatus::Published->value,
                    );
                },
                'versions as draft_versions_count' => static function ($query): void {
                    $query->where(
                        'status',
                        RecipeVersionStatus::Draft->value,
                    );
                },
            ])
            ->withMax('versions', 'version_number');

        if ($search !== null) {
            $query->where(
                function (EloquentBuilder $searchQuery) use ($search): void {
                    $searchQuery
                        ->whereLike('name', "%{$search}%")
                        ->orWhereLike('code', "%{$search}%");
                },
            );
        }

        if ($type !== 'all') {
            $query->where('type', $type);
        }

        if ($activity === 'active') {
            $query->where('active', true);
        } elseif ($activity === 'inactive') {
            $query->where('active', false);
        }

        return [
            [
                'search' => $search,
                'type' => $type,
                'activity' => $activity,
                'sort' => $sort,
                'direction' => $direction,
                'perPage' => $perPage,
            ],
            $query,
        ];
    }

    /**
     * Return tenant-wide headline metrics independently from current filters.
     *
     * @return array{
     *     totalCount: int,
     *     activeCount: int,
     *     menuItemCount: int,
     *     preparedItemCount: int,
     *     batchCount: int
     * }
     */
    private function indexSummary(Organization $organization): array
    {
        $baseQuery = Recipe::query()
            ->where('organization_id', $organization->id);

        return [
            'totalCount' => (clone $baseQuery)->count(),
            'activeCount' => (clone $baseQuery)
                ->where('active', true)
                ->count(),
            'menuItemCount' => (clone $baseQuery)
                ->where('type', RecipeType::MenuItem->value)
                ->count(),
            'preparedItemCount' => (clone $baseQuery)
                ->where('type', RecipeType::PreparedItem->value)
                ->count(),
            'batchCount' => (clone $baseQuery)
                ->where('type', RecipeType::Batch->value)
                ->count(),
        ];
    }

    /**
     * Serialize one recipe identity without calculating location-dependent
     * costs or conflating recipe activity with recipe-version lifecycle.
     *
     * @return array{
     *     id: int,
     *     code: string,
     *     name: string,
     *     type: string,
     *     active: bool,
     *     versionCount: int,
     *     publishedVersionCount: int,
     *     draftVersionCount: int,
     *     latestVersionNumber: int|null,
     *     updatedAt: string|null
     * }
     */
    private function indexRowData(Recipe $recipe): array
    {
        $latestVersionNumber = $recipe->getAttribute(
            'versions_max_version_number',
        );

        return [
            'id' => $recipe->id,
            'code' => $recipe->code,
            'name' => $recipe->name,
            'type' => $recipe->type->value,
            'active' => $recipe->active,
            'versionCount' => (int) ($recipe->getAttribute('versions_count') ?? 0),
            'publishedVersionCount' => (int) (
                $recipe->getAttribute('published_versions_count') ?? 0
            ),
            'draftVersionCount' => (int) (
                $recipe->getAttribute('draft_versions_count') ?? 0
            ),
            'latestVersionNumber' => is_numeric($latestVersionNumber)
                ? (int) $latestVersionNumber
                : null,
            'updatedAt' => $recipe->updated_at?->toIso8601String(),
        ];
    }

    /**
     * Apply an allow-listed sort and deterministic recipe-id tie breaker.
     *
     * @param  EloquentBuilder<Recipe>  $query
     * @param  'name'|'type'|'activity'|'updated_at'  $sort
     * @param  'asc'|'desc'  $direction
     * @return EloquentBuilder<Recipe>
     */
    private function applyIndexSort(
        EloquentBuilder $query,
        string $sort,
        string $direction,
    ): EloquentBuilder {
        $column = match ($sort) {
            'type' => 'type',
            'activity' => 'active',
            'updated_at' => 'updated_at',
            default => 'name',
        };

        return $query
            ->orderBy($column, $direction)
            ->orderBy('id');
    }

    private function activeOrganization(Request $request): Organization
    {
        $organization = $request->attributes->get(
            'activeOrganization',
        );

        if (! $organization instanceof Organization) {
            abort(403);
        }

        return $organization;
    }
}
