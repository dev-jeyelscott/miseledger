<?php

use Illuminate\Support\Facades\File;

test('organization locations use the compact modal-first management layout', function () {
    $source = File::get(
        resource_path('js/pages/organizations/locations/index.tsx'),
    );

    expect($source)
        ->toContain("import { Badge } from '@/components/ui/badge';")
        ->toContain('CreateLocationDialog')
        ->toContain('EditLocationDialog')
        ->toContain('DialogTrigger')
        ->toContain('useGuardedDialog')
        ->toContain('Search locations by name or code...')
        ->toContain('All statuses')
        ->toContain('filteredLocations')
        ->toContain('aria-live="polite"')
        ->toContain('<table')
        ->toContain('Location')
        ->toContain('Code')
        ->toContain('Status')
        ->toContain('Actions')
        ->toContain('<Badge')
        ->not->toContain(
            'lg:grid-cols-[minmax(0,1fr)_360px]',
        );
});

test('add location is a guarded dialog using the existing store contract', function () {
    $source = File::get(
        resource_path('js/pages/organizations/locations/index.tsx'),
    );

    expect($source)
        ->toContain('CreateLocationDialog')
        ->toContain('Add location')
        ->toContain(
            'OrganizationLocationController.store.form(',
        )
        ->toContain('errorBag="createLocation"')
        ->toContain('name="name"')
        ->toContain('name="code"')
        ->toContain('resetOnSuccess')
        ->toContain('dialog.closeAfterSuccess')
        ->toContain('New locations are active by default.')
        ->not->toContain('create-location-type')
        ->not->toContain('create-location-active');
});

test('location row actions preserve storage navigation and modal editing', function () {
    $source = File::get(
        resource_path('js/pages/organizations/locations/index.tsx'),
    );

    expect($source)
        ->toContain(
            'OrganizationStorageLocationController.index(',
        )
        ->toContain(
            'OrganizationLocationController.update.form([',
        )
        ->toContain(
            'errorBag={`editLocation${location.id}`}',
        )
        ->toContain('EditLocationDialog')
        ->toContain('Storage')
        ->toContain('Edit');
});
