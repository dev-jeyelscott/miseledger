<?php

namespace Database\Seeders;

use App\Actions\Organizations\AddOrganizationMember;
use App\Actions\Organizations\CreateOrganization;
use App\Actions\Organizations\SaveStorageLocation;
use App\Enums\OrganizationRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

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

        $this->createVerifiedUser(
            'MiseLedger Super Admin',
            'superadmin@miseledger.com',
        );

        $owner = $this->createVerifiedUser(
            'Andrea Santos',
            'owner@miseledger.com',
        );

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
            $user = $this->createVerifiedUser(
                $account['name'],
                $account['email'],
            );

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
            $location = $organization->locations()->create([
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

    /**
     * Create one deterministic, verified local demo account.
     */
    private function createVerifiedUser(
        string $name,
        string $email,
    ): User {
        $user = User::query()->create([
            'name' => $name,
            'email' => $email,
            'password' => 'password',
        ]);

        $user->forceFill([
            'email_verified_at' => Carbon::parse(
                '2026-08-01 08:00:00',
                'Asia/Manila',
            ),
        ])->save();

        return $user;
    }
}
