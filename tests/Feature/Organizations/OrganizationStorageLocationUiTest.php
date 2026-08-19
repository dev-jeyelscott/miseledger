<?php

use Illuminate\Support\Facades\File;

test('storage locations use the compact searchable management layout', function () {
    $source = File::get(
        resource_path(
            'js/pages/organizations/locations/storage-locations/index.tsx',
        ),
    );

    expect($source)
        ->toContain("import { Badge } from '@/components/ui/badge';")
        ->toContain("import { Plus, Search } from 'lucide-react';")
        ->toContain('CreateStorageLocationDialog')
        ->toContain('EditStorageLocationDialog')
        ->toContain('useGuardedDialog')
        ->toContain('Total storage locations')
        ->toContain('Active locations')
        ->toContain('Search storage locations by name or code...')
        ->toContain('All statuses')
        ->toContain('filteredStorageLocations')
        ->toContain('aria-live="polite"')
        ->toContain('<table')
        ->toContain('Name')
        ->toContain('Code')
        ->toContain('Status')
        ->toContain('Actions')
        ->toContain('<Badge')
        ->not->toContain(
            'lg:grid-cols-[minmax(0,1fr)_360px]',
        );
});

test('add storage location is a guarded modal using the existing store contract', function () {
    $source = File::get(
        resource_path(
            'js/pages/organizations/locations/storage-locations/index.tsx',
        ),
    );

    expect($source)
        ->toContain('CreateStorageLocationDialog')
        ->toContain('<DialogTrigger asChild>{trigger}</DialogTrigger>')
        ->toContain('Add storage location')
        ->toContain(
            'OrganizationStorageLocationController.store.form([',
        )
        ->toContain('errorBag="createStorageLocation"')
        ->toContain('name="name"')
        ->toContain('name="code"')
        ->toContain('name="active"')
        ->toContain('value="1"')
        ->toContain('resetOnSuccess')
        ->toContain('onSuccess={dialog.closeAfterSuccess}')
        ->toContain(
            'New storage locations are active by default.',
        );
});

test('storage location editing preserves guarded status and update behavior', function () {
    $source = File::get(
        resource_path(
            'js/pages/organizations/locations/storage-locations/index.tsx',
        ),
    );

    expect($source)
        ->toContain('EditStorageLocationDialog')
        ->toContain(
            'OrganizationStorageLocationController.update.form([',
        )
        ->toContain(
            'errorBag={`editStorageLocation${storageLocation.id}`}',
        )
        ->toContain('name="active"')
        ->toContain('<option value="1">Active</option>')
        ->toContain('<option value="0">Inactive</option>')
        ->toContain(
            'Discard the storage-location changes you entered?',
        )
        ->toContain(
            'Deactivation may be blocked while a',
        )
        ->toContain('shipped stock transfer is awaiting')
        ->toContain('receipt here.');
});
