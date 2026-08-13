<?php

namespace Database\Seeders;

use App\Support\Inventory\StandardUnits;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class StandardUnitSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the approved global unit catalog without duplicating rows.
     */
    public function run(): void
    {
        $timestamp = now();
        $units = array_map(
            fn (array $unit): array => [
                'code' => $unit['symbol'],
                'name' => $unit['name'],
                'dimension' => $unit['dimension'],
                'canonical_factor' => $unit['canonical_factor'],
                'active' => true,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ],
            StandardUnits::definitions(),
        );

        DB::table('standard_units')->upsert(
            $units,
            ['code'],
            ['name', 'dimension', 'canonical_factor', 'active', 'updated_at'],
        );
    }
}
