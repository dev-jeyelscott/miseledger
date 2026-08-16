<?php

namespace App\Http\Controllers\Recipes;

use App\Actions\Recipes\SaveRecipe;
use App\Enums\OrganizationPermission;
use App\Enums\RecipeType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Recipes\SaveRecipeRequest;
use App\Models\Organization;
use App\Models\Recipe;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class RecipeController extends Controller
{
    public function index(Request $request): Response
    {
        $organization = $this->activeOrganization($request);

        Gate::authorize(
            OrganizationPermission::RecipesView->value,
            $organization,
        );

        $recipes = $organization
            ->recipes()
            ->orderByDesc('active')
            ->orderBy('name')
            ->get()
            ->map(
                static fn (Recipe $recipe): array => [
                    'id' => $recipe->id,
                    'code' => $recipe->code,
                    'name' => $recipe->name,
                    'type' => $recipe->type->value,
                    'active' => $recipe->active,
                ],
            )
            ->values()
            ->all();

        return Inertia::render('recipes/index', [
            'recipes' => $recipes,
            'canManage' => Gate::allows(
                OrganizationPermission::RecipesManage->value,
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

        return to_route('recipes.index');
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

        return to_route('recipes.edit', $recipe);
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
