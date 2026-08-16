<?php

namespace App\Http\Requests\Recipes;

use App\Enums\OrganizationPermission;
use App\Enums\RecipeType;
use App\Models\Organization;
use App\Models\Recipe;
use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class SaveRecipeRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $organization = $this->organization();

        if (
            ! $user instanceof User
            || $organization === null
            || ! Gate::forUser($user)->allows(
                OrganizationPermission::RecipesManage->value,
                $organization,
            )
        ) {
            return false;
        }

        return $this->route('recipe') === null
            || $this->recipe() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        $organizationId = (int) (
            $this->organization()?->getKey() ?? 0
        );

        return [
            'code' => [
                'required',
                'string',
                'max:80',
                Rule::unique('recipes', 'code')
                    ->where(
                        fn (Builder $query): Builder => $query->where(
                            'organization_id',
                            $organizationId,
                        ),
                    )
                    ->ignore($this->recipe()),
            ],
            'name' => [
                'required',
                'string',
                'max:160',
            ],
            'type' => [
                'required',
                Rule::enum(RecipeType::class),
            ],
            'active' => [
                'required',
                'boolean',
            ],
        ];
    }

    public function organization(): ?Organization
    {
        $organization = $this->attributes->get('activeOrganization');

        return $organization instanceof Organization
            ? $organization
            : null;
    }

    public function recipe(): ?Recipe
    {
        $organization = $this->organization();
        $routeId = $this->route('recipe');

        if (
            $organization === null
            || $routeId === null
            || ! is_numeric($routeId)
        ) {
            return null;
        }

        return Recipe::query()
            ->where('organization_id', $organization->id)
            ->find((int) $routeId);
    }

    protected function prepareForValidation(): void
    {
        $code = $this->input('code');
        $name = $this->input('name');

        $this->merge([
            'code' => is_string($code)
                ? Str::upper(trim($code))
                : $code,
            'name' => is_string($name)
                ? Str::squish($name)
                : $name,
            'active' => $this->boolean('active'),
        ]);
    }
}
