<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        User::factory()->create([
            'name' => 'Miseledger Owner',
            'email' => 'owner@miseledger.com',
        ]);

        User::factory()->create([
            'name' => 'Inventory Staff',
            'email' => 'inventory@miseledger.com',
        ]);

        User::factory()->create([
            'name' => 'Kitchen Staff',
            'email' => 'kitchen@miseledger.com',
        ]);

        User::factory()->create([
            'name' => 'Auditor Staff',
            'email' => 'auditor@miseledger.com',
        ]);
    }
}
