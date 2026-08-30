<?php

namespace Database\Seeders;

use App\Enums\OrganizationRole;
use App\Models\InventoryItem;
use App\Models\Location;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\StorageLocation;
use App\Models\UnitOfMeasure;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Seed a single deterministic organization owner plus minimal purchasing
 * and inventory master data for the Playwright E2E harness. This seeder is
 * never called by DatabaseSeeder::run() and must only run against an
 * isolated, disposable test database.
 */
class E2ETestSeeder extends Seeder
{
    public const string EMAIL = 'e2e-owner@miseledger.test';

    public const string PASSWORD = 'password';

    public function run(): void
    {
        $organization = Organization::factory()->create([
            'name' => 'E2E Test Kitchen',
            'timezone' => 'Asia/Manila',
            'currency' => 'PHP',
            'active' => true,
        ]);

        $user = User::factory()->create([
            'name' => 'E2E Owner',
            'email' => self::EMAIL,
        ]);

        OrganizationMembership::factory()->create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'role' => OrganizationRole::Owner,
        ]);

        $location = Location::factory()->create([
            'organization_id' => $organization->id,
            'name' => 'Main Kitchen',
            'code' => 'MAIN',
        ]);

        $storageLocation = new StorageLocation;
        $storageLocation->organization_id = $organization->id;
        $storageLocation->location_id = $location->id;
        $storageLocation->name = 'Main Storage';
        $storageLocation->code = 'MAIN';
        $storageLocation->active = true;
        $storageLocation->save();

        $unit = UnitOfMeasure::factory()->create([
            'organization_id' => $organization->id,
            'name' => 'Kilogram',
            'symbol' => 'kg',
            'dimension' => 'weight',
        ]);

        InventoryItem::factory()->create([
            'organization_id' => $organization->id,
            'base_unit_of_measure_id' => $unit->id,
            'name' => 'E2E Test Ingredient',
            'sku' => 'E2E-0001',
        ]);
    }
}
