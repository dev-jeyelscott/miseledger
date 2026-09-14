<?php

use Illuminate\Support\Facades\File;

test('organization members uses the compact searchable modal-first management layout', function () {
    $source = File::get(
        resource_path('js/pages/organizations/members.tsx'),
    );

    expect($source)
        ->toContain("import { Badge } from '@/components/ui/badge';")
        ->toContain('useMemo')
        ->toContain('Search name or email...')
        ->toContain('All roles')
        ->toContain('filteredMembers')
        ->toContain('Current members')
        ->toContain('Member')
        ->toContain('Email')
        ->toContain('Role')
        ->toContain('<Badge')
        ->toContain('aria-live="polite"')
        ->toContain('No members match these filters.')
        ->toContain('Reset filters')
        ->toContain('data-testid="mobile-organization-members"')
        ->toContain('flex items-center justify-between gap-3')
        ->not->toContain(
            'lg:grid-cols-[minmax(0,1fr)_360px]',
        );
});

test('mobile organization member records give identity a dedicated full-width row above role and AI metadata', function () {
    $source = File::get(
        resource_path('js/pages/organizations/members.tsx'),
    );

    $mobileArticleStart = strpos($source, 'data-testid="mobile-organization-members"');
    $mobileArticleEnd = strpos($source, 'hidden overflow-x-auto md:block', $mobileArticleStart);
    $mobileSection = substr(
        $source,
        $mobileArticleStart,
        $mobileArticleEnd - $mobileArticleStart,
    );

    expect($mobileSection)
        ->toContain('className="space-y-3 p-4"')
        ->toContain('flex items-center justify-between gap-3');

    $identityBlockPosition = strpos($mobileSection, 'flex items-center gap-3');
    $badgePosition = strpos($mobileSection, '<Badge variant="secondary">');

    expect($identityBlockPosition)->toBeLessThan($badgePosition);
});

test('registered user creation is presented through the existing guarded dialog workflow', function () {
    $source = File::get(
        resource_path('js/pages/organizations/members.tsx'),
    );

    expect($source)
        ->toContain('AddRegisteredUserDialog')
        ->toContain('DialogTrigger')
        ->toContain('DialogContent')
        ->toContain('Add registered user')
        ->toContain('useGuardedDialog')
        ->toContain(
            'OrganizationMemberController.store.form(',
        )
        ->toContain('errorBag="addOrganizationMember"')
        ->toContain('resetOnSuccess')
        ->toContain('onSuccess={dialog.closeAfterSuccess}')
        ->toContain('name="email"')
        ->toContain('name="role"')
        ->toContain('Add member')
        ->toContain('Adding member...')
        ->toContain('Cancel');
});
