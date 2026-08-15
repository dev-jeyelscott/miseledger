<?php

use App\Actions\Organizations\CreateOrganization;
use App\Actions\Organizations\EnsureDefaultWasteReasons;
use App\Models\Organization;
use App\Models\User;
use App\Models\WasteReason;
use Database\Seeders\WasteReasonSeeder;

test('new organizations receive the approved default waste reasons', function () {
    $owner = User::factory()->create();

    $organization = app(CreateOrganization::class)->handle(
        $owner,
        'Waste Defaults Restaurant',
    );

    expect(
        WasteReason::query()
            ->where('organization_id', $organization->id)
            ->count(),
    )->toBe(count(EnsureDefaultWasteReasons::names()));

    foreach (EnsureDefaultWasteReasons::names() as $name) {
        $reason = WasteReason::query()
            ->where('organization_id', $organization->id)
            ->where('name', $name)
            ->sole();

        expect($reason->active)->toBeTrue();
    }
});

test(
    'default waste reason setup is repeatable without overwriting configured reasons',
    function () {
        $organization = Organization::factory()->create();

        $spoilage = WasteReason::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Spoilage',
            'active' => false,
        ]);

        $customReason = WasteReason::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Trim Loss',
            'active' => false,
        ]);

        $spoilageId = $spoilage->id;
        $customReasonId = $customReason->id;

        $ensureDefaultWasteReasons = app(
            EnsureDefaultWasteReasons::class,
        );

        $ensureDefaultWasteReasons->handle($organization);
        $ensureDefaultWasteReasons->handle($organization);

        expect(
            WasteReason::query()
                ->where('organization_id', $organization->id)
                ->count(),
        )
            ->toBe(count(EnsureDefaultWasteReasons::names()) + 1)
            ->and($spoilage->refresh()->id)
            ->toBe($spoilageId)
            ->and($spoilage->active)
            ->toBeFalse()
            ->and($customReason->refresh()->id)
            ->toBe($customReasonId)
            ->and($customReason->active)
            ->toBeFalse();

        foreach (EnsureDefaultWasteReasons::names() as $name) {
            expect(
                WasteReason::query()
                    ->where('organization_id', $organization->id)
                    ->where('name', $name)
                    ->count(),
            )->toBe(1);
        }
    },
);

test('waste reason seeding is isolated by organization', function () {
    $firstOrganization = Organization::factory()->create();
    $secondOrganization = Organization::factory()->create();

    $firstExpired = WasteReason::query()->create([
        'organization_id' => $firstOrganization->id,
        'name' => 'Expired',
        'active' => false,
    ]);

    $firstCustomReason = WasteReason::query()->create([
        'organization_id' => $firstOrganization->id,
        'name' => 'Trim Loss',
        'active' => true,
    ]);

    $this->seed(WasteReasonSeeder::class);
    $this->seed(WasteReasonSeeder::class);

    expect(
        WasteReason::query()
            ->where(
                'organization_id',
                $firstOrganization->id,
            )
            ->count(),
    )
        ->toBe(count(EnsureDefaultWasteReasons::names()) + 1)
        ->and(
            WasteReason::query()
                ->where(
                    'organization_id',
                    $secondOrganization->id,
                )
                ->count(),
        )
        ->toBe(count(EnsureDefaultWasteReasons::names()));

    foreach (
        [$firstOrganization, $secondOrganization] as $organization
    ) {
        foreach (EnsureDefaultWasteReasons::names() as $name) {
            expect(
                WasteReason::query()
                    ->where(
                        'organization_id',
                        $organization->id,
                    )
                    ->where('name', $name)
                    ->count(),
            )->toBe(1);
        }
    }

    expect($firstExpired->refresh()->active)
        ->toBeFalse()
        ->and($firstCustomReason->refresh()->active)
        ->toBeTrue()
        ->and(
            WasteReason::query()
                ->where(
                    'organization_id',
                    $secondOrganization->id,
                )
                ->where('name', 'Expired')
                ->sole()
                ->active,
        )
        ->toBeTrue()
        ->and(
            WasteReason::query()
                ->where(
                    'organization_id',
                    $secondOrganization->id,
                )
                ->where('name', 'Trim Loss')
                ->exists(),
        )
        ->toBeFalse();
});
