<?php

use App\Models\InventoryCategory;
use App\Models\Organization;

test('the import-master command reads CSV files and reports row errors', function () {
    $organization = Organization::factory()->create();

    $path = tempnam(sys_get_temp_dir(), 'categories-');
    file_put_contents($path, "name,active\nDry Goods,true\n,true\n");

    $this->artisan('inventory:import-master', [
        'organization' => $organization->id,
        '--categories' => $path,
    ])
        ->assertExitCode(1)
        ->expectsOutputToContain('1 created, 0 updated, 1 row error')
        ->expectsOutputToContain('Row 3:');

    unlink($path);

    expect(InventoryCategory::query()->where('name', 'Dry Goods')->exists())->toBeTrue();
});

test('the import-master command fails for an unknown organization', function () {
    $this->artisan('inventory:import-master', [
        'organization' => 999999,
    ])->assertExitCode(1);
});

test('the import-master command refuses to import into a commercially read-only organization', function () {
    $organization = Organization::factory()->create([
        'trial_ends_at' => now()->subDay(),
    ]);

    $path = tempnam(sys_get_temp_dir(), 'categories-');
    file_put_contents($path, "name,active\nDry Goods,true\n");

    $this->artisan('inventory:import-master', [
        'organization' => $organization->id,
        '--categories' => $path,
    ])
        ->assertExitCode(1)
        ->expectsOutputToContain('read-only');

    unlink($path);

    expect(InventoryCategory::query()->where('name', 'Dry Goods')->exists())->toBeFalse();
});
