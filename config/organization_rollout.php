<?php

use App\Enums\OrganizationRolloutClassification;

/*
|--------------------------------------------------------------------------
| Approved Existing-Organization Rollout Classifications
|--------------------------------------------------------------------------
|
| Operator-approved pre-enforcement classification for every organization
| that exists before subscription enforcement is activated, keyed by the
| organization's stable, unique `slug`. See
| docs/existing-organization-rollout-plan.md for the full rollout plan.
|
| Every entry reflects an explicit human decision with its supporting
| evidence, never an inference from `created_at`/`updated_at` or any other
| incidental timestamp. `organizations:apply-rollout-classifications`
| refuses to run while any pre-existing organization is missing from this
| map, so activation can never proceed with an unclassified tenant.
|
*/

return [

    'classifications' => [
        'sinta-kitchen-cafe-01m0d8g6dm0fkf49mjr2eet6b8' => [
            'classification' => OrganizationRolloutClassification::DevelopmentTest,
            'rationale' => 'Created exclusively by DemoOrganizationSeeder, which refuses to run when app()->environment(\'production\'); a demo tenant, never a real customer.',
        ],
        'kling-schroeder-and-jenkins-ejt1apk6' => [
            'classification' => OrganizationRolloutClassification::DevelopmentTest,
            'rationale' => 'Faker-generated company name produced by the Organization factory; a test-data artifact, never a real customer.',
        ],
        'jenkins-mcglynn-op0p3bgb' => [
            'classification' => OrganizationRolloutClassification::DevelopmentTest,
            'rationale' => 'Faker-generated company name produced by the Organization factory; a test-data artifact, never a real customer.',
        ],
        'qrph-browser-test' => [
            'classification' => OrganizationRolloutClassification::DevelopmentTest,
            'rationale' => 'Named "QR Ph Browser Test"; a manual browser-testing tenant used to exercise the QR Ph checkout flow, never a real customer.',
        ],
    ],

];
