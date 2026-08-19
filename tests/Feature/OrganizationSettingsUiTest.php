<?php

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

test('organization settings uses the approved responsive overview and details layout', function () {
    $source = File::get(
        resource_path('js/pages/organizations/settings.tsx'),
    );

    $normalizedSource = Str::squish($source);

    expect($normalizedSource)
        ->toContain("import { Badge } from '@/components/ui/badge';")
        ->toContain("from '@/components/ui/card';")
        ->toContain('Organization overview')
        ->toContain('Organization details')
        ->toContain('Organization ID')
        ->toContain('xl:grid-cols-[minmax(0,320px)_minmax(0,1fr)]')
        ->toContain('Use a valid IANA timezone')
        ->toContain('Use a 3-letter ISO currency code')
        ->toContain('type="radio"')
        ->toContain('name="active"')
        ->toContain('value="1"')
        ->toContain('value="0"')
        ->toContain('defaultChecked={')
        ->toContain('Save changes')
        ->toContain('PreviousPageButton')
        ->not->toContain('Contact support')
        ->not->toContain('delete your organization');
});

test('organization settings redesign preserves form submission and validation feedback', function () {
    $source = File::get(
        resource_path('js/pages/organizations/settings.tsx'),
    );

    expect($source)
        ->toContain('OrganizationController.update.form(')
        ->toContain('organization.id,')
        ->toContain('disabled={processing}')
        ->toContain('aria-invalid={Boolean(')
        ->toContain('<InputError')
        ->toContain('message={errors.name}')
        ->toContain('message={errors.slug}')
        ->toContain('message={errors.timezone}')
        ->toContain('message={errors.currency}')
        ->toContain('message={errors.active}')
        ->toContain("'Saving...'")
        ->toContain("'Save changes'");
});
