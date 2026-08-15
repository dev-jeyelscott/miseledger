<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's baseline and local demo data.
     */
    public function run(): void
    {
        $this->call([
            StandardUnitSeeder::class,
            WasteReasonSeeder::class,
            DemoDataSeeder::class,
        ]);
    }
}
