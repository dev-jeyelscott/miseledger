<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DemoDataSeeder extends Seeder
{
    /**
     * Seed a connected presentation-ready dataset in dependency order.
     */
    public function run(): void
    {
        if (app()->environment('production')) {
            return;
        }

        $this->call([
            DemoOrganizationSeeder::class,
            DemoInventorySeeder::class,
            DemoSupplierSeeder::class,
            DemoStockLedgerSeeder::class,
            DemoPurchasingSeeder::class,
            DemoWasteSeeder::class,
            DemoStockTransferSeeder::class,
            DemoStockCountSeeder::class,
            DemoRecipeSeeder::class,
        ]);
    }
}
