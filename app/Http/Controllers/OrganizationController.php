<?php

namespace App\Http\Controllers;

use App\Actions\Organizations\CreateOrganization;
use App\Http\Requests\Organizations\StoreOrganizationRequest;
use App\Http\Requests\Organizations\UpdateOrganizationSettingsRequest;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class OrganizationController extends Controller
{
    /**
     * Show the organization onboarding form.
     */
    public function create(): Response
    {
        return Inertia::render('organizations/create');
    }

    /**
     * Create an organization with the authenticated user as owner.
     */
    public function store(
        StoreOrganizationRequest $request,
        CreateOrganization $createOrganization,
    ): RedirectResponse {
        $user = $request->user();

        if (! $user instanceof User) {
            abort(401);
        }

        $organization = $createOrganization->handle(
            $user,
            (string) $request->validated('name'),
        );

        $request->session()->put(
            'active_organization_id',
            $organization->getKey(),
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Organization created.'),
        ]);

        return to_route('dashboard');
    }

    /**
     * Activate an organization only when the user belongs to it.
     */
    public function activate(
        Request $request,
        Organization $organization,
    ): RedirectResponse {
        Gate::authorize('view', $organization);

        $request->session()->put(
            'active_organization_id',
            $organization->getKey(),
        );

        return to_route('dashboard');
    }

    /**
     * Show editable settings for an organization the user manages.
     */
    public function edit(Organization $organization): Response
    {
        Gate::authorize('update', $organization);

        return Inertia::render('organizations/settings', [
            'organization' => [
                'id' => $organization->id,
                'name' => $organization->name,
                'slug' => $organization->slug,
                'timezone' => $organization->timezone,
                'currency' => $organization->currency,
                'active' => $organization->active,
            ],
            'timezoneOptions' => UpdateOrganizationSettingsRequest::timezoneOptions(),
            'currencyOptions' => UpdateOrganizationSettingsRequest::currencyOptions(),
        ]);
    }

    /**
     * Update the tenant's mutable settings without changing its ownership.
     */
    public function update(
        UpdateOrganizationSettingsRequest $request,
        Organization $organization,
    ): RedirectResponse {
        $organization->update($request->validated());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Organization settings updated.'),
        ]);

        return to_route('dashboard');
    }
}
