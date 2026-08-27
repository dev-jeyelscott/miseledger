<?php

use App\Enums\OrganizationRolloutClassification;
use App\Models\AuditLog;
use App\Models\Organization;
use Illuminate\Support\Facades\Config;

test('it refuses to run while any pre-existing organization has no approved classification', function () {
    $organization = Organization::factory()->create(['slug' => 'unapproved-tenant']);

    Config::set('organization_rollout.classifications', []);

    $this->artisan('organizations:apply-rollout-classifications')
        ->assertFailed();

    expect($organization->fresh()->rollout_classification)->toBeNull();
});

test('it applies the approved classification map and records an audit entry', function () {
    $organization = Organization::factory()->create(['slug' => 'demo-tenant']);

    Config::set('organization_rollout.classifications', [
        'demo-tenant' => [
            'classification' => OrganizationRolloutClassification::DevelopmentTest,
            'rationale' => 'Seeded demo tenant.',
        ],
    ]);

    $this->artisan('organizations:apply-rollout-classifications')
        ->assertSuccessful();

    expect($organization->fresh()->rollout_classification)
        ->toBe(OrganizationRolloutClassification::DevelopmentTest);

    $entry = AuditLog::query()
        ->where('organization_id', $organization->id)
        ->where('action', 'organization.rollout_classification.applied')
        ->sole();

    expect($entry->after_data['rollout_classification'])->toBe('development_test');
});

test('a dry run reports the planned change without writing it', function () {
    $organization = Organization::factory()->create(['slug' => 'demo-tenant']);

    Config::set('organization_rollout.classifications', [
        'demo-tenant' => [
            'classification' => OrganizationRolloutClassification::TrialEligible,
            'rationale' => 'Approved for a fresh trial.',
        ],
    ]);

    $this->artisan('organizations:apply-rollout-classifications', ['--dry-run' => true])
        ->assertSuccessful();

    expect($organization->fresh()->rollout_classification)->toBeNull();
});

test('re-running the command is idempotent for already classified organizations', function () {
    $organization = Organization::factory()->create([
        'slug' => 'demo-tenant',
        'rollout_classification' => OrganizationRolloutClassification::Grandfathered,
    ]);

    Config::set('organization_rollout.classifications', [
        'demo-tenant' => [
            'classification' => OrganizationRolloutClassification::Grandfathered,
            'rationale' => 'Approved legacy exemption.',
        ],
    ]);

    $this->artisan('organizations:apply-rollout-classifications')
        ->assertSuccessful();

    expect(
        AuditLog::query()
            ->where('organization_id', $organization->id)
            ->where('action', 'organization.rollout_classification.applied')
            ->count(),
    )->toBe(0);
});
