<?php

namespace Database\Seeders;

use App\Actions\Organizations\AddOrganizationMember;
use App\Actions\Organizations\CreateOrganization;
use App\Actions\Organizations\SaveStorageLocation;
use App\Enums\OrganizationRole;
use App\Models\Location;
use App\Models\User;
use Illuminate\Database\Seeder;

class DemoOrganizationSeeder extends Seeder
{
    /**
     * Seed the demo tenant, realistic staff, branches, and storage areas.
     */
    public function run(
        CreateOrganization $createOrganization,
        AddOrganizationMember $addOrganizationMember,
        SaveStorageLocation $saveStorageLocation,
    ): void {
        if (app()->environment('production')) {
            return;
        }

        User::factory()->create([
            'name' => 'MiseLedger Super Admin',
            'email' => 'superadmin@miseledger.com',
        ]);

        $owner = User::factory()->create([
            'name' => 'Andrea Santos',
            'email' => 'owner@miseledger.com',
        ]);

        $organization = $createOrganization->handle(
            $owner,
            'Sinta Kitchen & Café',
        );

        /** @var list<array{name: string, email: string, role: OrganizationRole}> $staff */
        $staff = [
            [
                'name' => 'Miguel Reyes',
                'email' => 'manager@miseledger.com',
                'role' => OrganizationRole::Manager,
            ],
            [
                'name' => 'Carla Mendoza',
                'email' => 'inventory@miseledger.com',
                'role' => OrganizationRole::InventoryStaff,
            ],
            [
                'name' => 'Paolo Dizon',
                'email' => 'kitchen@miseledger.com',
                'role' => OrganizationRole::KitchenStaff,
            ],
            [
                'name' => 'Sofia Lim',
                'email' => 'auditor@miseledger.com',
                'role' => OrganizationRole::Auditor,
            ],
        ];

        foreach ($staff as $account) {
            $user = User::factory()->create([
                'name' => $account['name'],
                'email' => $account['email'],
            ]);

            $addOrganizationMember->handle(
                $organization,
                $owner,
                $user,
                $account['role'],
            );
        }

        /** @var list<array{name: string, code: string, active: bool, storage: list<array{name: string, code: string}>}> $locations */
        $locations = [
            [
                'name' => 'Makati Flagship',
                'code' => 'MKT',
                'active' => true,
                'storage' => [
                    ['name' => 'Dry Store', 'code' => 'MKT-DRY'],
                    ['name' => 'Walk-in Chiller', 'code' => 'MKT-CHILL'],
                    ['name' => 'Freezer', 'code' => 'MKT-FRZ'],
                    ['name' => 'Packaging Store', 'code' => 'MKT-PACK'],
                ],
            ],
            [
                'name' => 'BGC High Street',
                'code' => 'BGC',
                'active' => true,
                'storage' => [
                    ['name' => 'Dry Store', 'code' => 'BGC-DRY'],
                    ['name' => 'Walk-in Chiller', 'code' => 'BGC-CHILL'],
                    ['name' => 'Beverage Store', 'code' => 'BGC-BAR'],
                    ['name' => 'Packaging Store', 'code' => 'BGC-PACK'],
                ],
            ],
            [
                'name' => 'Quezon City Commissary',
                'code' => 'QCC',
                'active' => true,
                'storage' => [
                    ['name' => 'Central Dry Store', 'code' => 'QCC-DRY'],
                    ['name' => 'Commissary Chiller', 'code' => 'QCC-CHILL'],
                    ['name' => 'Central Packaging Store', 'code' => 'QCC-PACK'],
                ],
            ],
            [
                'name' => 'Ortigas Pop-up - Closed',
                'code' => 'ORT',
                'active' => false,
                'storage' => [],
            ],
        ];

        foreach ($locations as $definition) {
            $location = Location::factory()->create([
                'organization_id' => $organization->id,
                'name' => $definition['name'],
                'code' => $definition['code'],
                'active' => $definition['active'],
            ]);

            foreach ($definition['storage'] as $storage) {
                $saveStorageLocation->handle(
                    $organization,
                    $location,
                    [
                        'name' => $storage['name'],
                        'code' => $storage['code'],
                        'active' => true,
                    ],
                );
            }
        }
    }
}
