<?php

namespace Database\Seeders;

use App\Actions\Suppliers\RecordSupplierItemPrice;
use App\Actions\Suppliers\SaveSupplier;
use App\Actions\Suppliers\SaveSupplierItem;
use App\Models\InventoryItem;
use App\Models\Organization;
use App\Models\Supplier;
use App\Models\SupplierItem;
use App\Models\UnitOfMeasure;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class DemoSupplierSeeder extends Seeder
{
    /**
     * Seed realistic vendors, supplier SKUs, pack sizes, and price history.
     */
    public function run(
        SaveSupplier $saveSupplier,
        SaveSupplierItem $saveSupplierItem,
        RecordSupplierItemPrice $recordSupplierItemPrice,
    ): void {
        if (app()->environment('production')) {
            return;
        }

        $organization = Organization::query()
            ->where('name', 'Sinta Kitchen & Café')
            ->sole();

        try {
            Carbon::setTestNow('2026-05-20 09:00:00');

            /** @var array<string, Supplier> $suppliers */
            $suppliers = [];

            /**
             * @var list<array{
             *     name: string,
             *     code: string,
             *     contact_name: string|null,
             *     email: string|null,
             *     phone: string|null,
             *     payment_terms: string|null,
             *     lead_time_days: int|null,
             *     active: bool
             * }> $supplierDefinitions
             */
            $supplierDefinitions = [
                [
                    'name' => 'Metro Fresh Foods Trading',
                    'code' => 'MFF',
                    'contact_name' => 'Ramon Villanueva',
                    'email' => 'orders@metrofresh.example',
                    'phone' => '+63 917 555 1101',
                    'payment_terms' => 'Net 15',
                    'lead_time_days' => 2,
                    'active' => true,
                ],
                [
                    'name' => 'Green Harvest Produce',
                    'code' => 'GHP',
                    'contact_name' => 'Liza Mercado',
                    'email' => 'sales@greenharvest.example',
                    'phone' => '+63 917 555 2202',
                    'payment_terms' => 'COD',
                    'lead_time_days' => 1,
                    'active' => true,
                ],
                [
                    'name' => 'Prime Staples Supply Co.',
                    'code' => 'PSS',
                    'contact_name' => 'Nathan Co',
                    'email' => 'wholesale@primestaples.example',
                    'phone' => '+63 917 555 3303',
                    'payment_terms' => 'Net 30',
                    'lead_time_days' => 3,
                    'active' => true,
                ],
                [
                    'name' => 'Manila Dairy & Beverage Supply',
                    'code' => 'MDB',
                    'contact_name' => 'Angela Cruz',
                    'email' => 'accounts@maniladairy.example',
                    'phone' => '+63 917 555 4404',
                    'payment_terms' => 'Net 15',
                    'lead_time_days' => 2,
                    'active' => true,
                ],
                [
                    'name' => 'Coffee & Craft Beverages',
                    'code' => 'CCB',
                    'contact_name' => 'Marco Tan',
                    'email' => 'trade@coffeecraft.example',
                    'phone' => '+63 917 555 5505',
                    'payment_terms' => 'Net 15',
                    'lead_time_days' => 2,
                    'active' => true,
                ],
                [
                    'name' => 'PackPro Foodservice Products',
                    'code' => 'PFP',
                    'contact_name' => 'Grace Uy',
                    'email' => 'sales@packpro.example',
                    'phone' => '+63 917 555 6606',
                    'payment_terms' => 'Net 30',
                    'lead_time_days' => 4,
                    'active' => true,
                ],
                [
                    'name' => 'ValueMart Commercial Supply - Archived',
                    'code' => 'VMC',
                    'contact_name' => null,
                    'email' => null,
                    'phone' => null,
                    'payment_terms' => null,
                    'lead_time_days' => null,
                    'active' => false,
                ],
            ];

            foreach ($supplierDefinitions as $definition) {
                $supplier = $saveSupplier->handle(
                    $organization,
                    $definition,
                );

                $suppliers[$supplier->code] = $supplier;
            }

            /** @var list<array{supplier: string, sku: string, supplier_sku: string, description: string, unit: string, base_quantity: string, previous_price: string|null, current_price: string}> $mappings */
            $mappings = [
                ['supplier' => 'MFF', 'sku' => 'CHK-THIGH', 'supplier_sku' => 'MFF-CHK-10KG', 'description' => 'Boneless chicken thigh fillet, 10 kg case', 'unit' => 'case', 'base_quantity' => '10.000000', 'previous_price' => '1580.0000', 'current_price' => '1650.0000'],
                ['supplier' => 'MFF', 'sku' => 'PORK-BELLY', 'supplier_sku' => 'MFF-PORK-10KG', 'description' => 'Skin-on pork belly, 10 kg case', 'unit' => 'case', 'base_quantity' => '10.000000', 'previous_price' => '2820.0000', 'current_price' => '2890.0000'],
                ['supplier' => 'MFF', 'sku' => 'BEEF-SHORT', 'supplier_sku' => 'MFF-BEEF-10KG', 'description' => 'Beef short plate, 10 kg case', 'unit' => 'case', 'base_quantity' => '10.000000', 'previous_price' => '3890.0000', 'current_price' => '3980.0000'],

                ['supplier' => 'GHP', 'sku' => 'GARLIC', 'supplier_sku' => 'GHP-GARLIC-5KG', 'description' => 'Fresh peeled garlic, 5 kg bag', 'unit' => 'bag', 'base_quantity' => '5.000000', 'previous_price' => '720.0000', 'current_price' => '760.0000'],
                ['supplier' => 'GHP', 'sku' => 'RED-ONION', 'supplier_sku' => 'GHP-ONION-10KG', 'description' => 'Red onion, 10 kg bag', 'unit' => 'bag', 'base_quantity' => '10.000000', 'previous_price' => '880.0000', 'current_price' => '920.0000'],
                ['supplier' => 'GHP', 'sku' => 'TOMATO-ROMA', 'supplier_sku' => 'GHP-TOMATO-10KG', 'description' => 'Roma tomato, 10 kg box', 'unit' => 'box', 'base_quantity' => '10.000000', 'previous_price' => null, 'current_price' => '1050.0000'],
                ['supplier' => 'GHP', 'sku' => 'GREEN-CHILI', 'supplier_sku' => 'GHP-CHILI-2KG', 'description' => 'Green chili, 2 kg bag', 'unit' => 'bag', 'base_quantity' => '2.000000', 'previous_price' => null, 'current_price' => '420.0000'],
                ['supplier' => 'GHP', 'sku' => 'CILANTRO', 'supplier_sku' => 'GHP-CILANTRO-1KG', 'description' => 'Fresh cilantro, 1 kg bag', 'unit' => 'bag', 'base_quantity' => '1.000000', 'previous_price' => null, 'current_price' => '340.0000'],

                ['supplier' => 'PSS', 'sku' => 'JASMINE-RICE', 'supplier_sku' => 'PSS-RICE-25KG', 'description' => 'Premium jasmine rice, 25 kg sack', 'unit' => 'sack', 'base_quantity' => '25.000000', 'previous_price' => '1390.0000', 'current_price' => '1450.0000'],
                ['supplier' => 'PSS', 'sku' => 'AP-FLOUR', 'supplier_sku' => 'PSS-FLOUR-25KG', 'description' => 'All-purpose flour, 25 kg sack', 'unit' => 'sack', 'base_quantity' => '25.000000', 'previous_price' => '1090.0000', 'current_price' => '1150.0000'],
                ['supplier' => 'PSS', 'sku' => 'BROWN-SUGAR', 'supplier_sku' => 'PSS-SUGAR-25KG', 'description' => 'Brown sugar, 25 kg sack', 'unit' => 'sack', 'base_quantity' => '25.000000', 'previous_price' => null, 'current_price' => '1780.0000'],
                ['supplier' => 'PSS', 'sku' => 'CORNSTARCH', 'supplier_sku' => 'PSS-CORN-5KG', 'description' => 'Cornstarch, 5 kg bag', 'unit' => 'bag', 'base_quantity' => '5.000000', 'previous_price' => null, 'current_price' => '360.0000'],
                ['supplier' => 'PSS', 'sku' => 'SOY-SAUCE', 'supplier_sku' => 'PSS-SOY-12L', 'description' => 'Premium soy sauce, 12 L case', 'unit' => 'case', 'base_quantity' => '12.000000', 'previous_price' => '690.0000', 'current_price' => '720.0000'],
                ['supplier' => 'PSS', 'sku' => 'CANE-VINEGAR', 'supplier_sku' => 'PSS-VINEGAR-12L', 'description' => 'Cane vinegar, 12 L case', 'unit' => 'case', 'base_quantity' => '12.000000', 'previous_price' => null, 'current_price' => '540.0000'],
                ['supplier' => 'PSS', 'sku' => 'COOKING-OIL', 'supplier_sku' => 'PSS-OIL-16L', 'description' => 'Canola cooking oil, 16 L case', 'unit' => 'case', 'base_quantity' => '16.000000', 'previous_price' => '1450.0000', 'current_price' => '1520.0000'],
                ['supplier' => 'PSS', 'sku' => 'FISH-SAUCE', 'supplier_sku' => 'PSS-FISH-12L', 'description' => 'Fish sauce, 12 L case', 'unit' => 'case', 'base_quantity' => '12.000000', 'previous_price' => null, 'current_price' => '840.0000'],

                ['supplier' => 'MDB', 'sku' => 'EGG-LARGE', 'supplier_sku' => 'MDB-EGG-TRAY', 'description' => 'Large eggs, tray of 30', 'unit' => 'tray', 'base_quantity' => '30.000000', 'previous_price' => '270.0000', 'current_price' => '285.0000'],
                ['supplier' => 'MDB', 'sku' => 'BUTTER', 'supplier_sku' => 'MDB-BUTTER-10KG', 'description' => 'Unsalted butter, 10 kg case', 'unit' => 'case', 'base_quantity' => '10.000000', 'previous_price' => null, 'current_price' => '3600.0000'],
                ['supplier' => 'MDB', 'sku' => 'FRESH-MILK', 'supplier_sku' => 'MDB-MILK-12L', 'description' => 'Fresh milk, 12 L case', 'unit' => 'case', 'base_quantity' => '12.000000', 'previous_price' => '1120.0000', 'current_price' => '1180.0000'],
                ['supplier' => 'MDB', 'sku' => 'AP-CREAM', 'supplier_sku' => 'MDB-CREAM-12L', 'description' => 'All-purpose cream, 12 L case', 'unit' => 'case', 'base_quantity' => '12.000000', 'previous_price' => null, 'current_price' => '1560.0000'],

                ['supplier' => 'CCB', 'sku' => 'COFFEE-BEAN', 'supplier_sku' => 'CCB-ARABICA-1KG', 'description' => 'Medium roast Arabica beans, 1 kg bag', 'unit' => 'bag', 'base_quantity' => '1.000000', 'previous_price' => '650.0000', 'current_price' => '680.0000'],
                ['supplier' => 'CCB', 'sku' => 'COLA-CAN', 'supplier_sku' => 'CCB-COLA-24', 'description' => 'Cola 330ml, 24-can case', 'unit' => 'case', 'base_quantity' => '24.000000', 'previous_price' => null, 'current_price' => '840.0000'],
                ['supplier' => 'CCB', 'sku' => 'WATER-500', 'supplier_sku' => 'CCB-WATER-24', 'description' => '500ml bottled water, 24-bottle case', 'unit' => 'case', 'base_quantity' => '24.000000', 'previous_price' => null, 'current_price' => '360.0000'],

                ['supplier' => 'PFP', 'sku' => 'BOWL-750', 'supplier_sku' => 'PFP-BOWL-500', 'description' => '750ml takeout bowls, 500-piece case', 'unit' => 'case', 'base_quantity' => '500.000000', 'previous_price' => '2100.0000', 'current_price' => '2200.0000'],
                ['supplier' => 'PFP', 'sku' => 'CUP-12OZ', 'supplier_sku' => 'PFP-CUP-1000', 'description' => '12oz hot cups, 1,000-piece case', 'unit' => 'case', 'base_quantity' => '1000.000000', 'previous_price' => null, 'current_price' => '3150.0000'],
                ['supplier' => 'PFP', 'sku' => 'PAPER-BAG-M', 'supplier_sku' => 'PFP-BAG-100', 'description' => 'Medium kraft bags, pack of 100', 'unit' => 'pack', 'base_quantity' => '100.000000', 'previous_price' => null, 'current_price' => '260.0000'],
                ['supplier' => 'PFP', 'sku' => 'NAPKIN', 'supplier_sku' => 'PFP-NAPKIN-500', 'description' => 'Dinner napkins, pack of 500', 'unit' => 'pack', 'base_quantity' => '500.000000', 'previous_price' => null, 'current_price' => '420.0000'],
                ['supplier' => 'PFP', 'sku' => 'DISHWASH-LIQ', 'supplier_sku' => 'PFP-DISH-16L', 'description' => 'Commercial dishwashing liquid, 16 L case', 'unit' => 'case', 'base_quantity' => '16.000000', 'previous_price' => null, 'current_price' => '1280.0000'],
                ['supplier' => 'PFP', 'sku' => 'GLOVES-M', 'supplier_sku' => 'PFP-GLOVE-100', 'description' => 'Medium nitrile gloves, box of 100', 'unit' => 'box', 'base_quantity' => '100.000000', 'previous_price' => null, 'current_price' => '420.0000'],
            ];

            /** @var array<string, SupplierItem> $supplierItems */
            $supplierItems = [];

            foreach ($mappings as $mapping) {
                $inventoryItem = InventoryItem::query()
                    ->where('organization_id', $organization->id)
                    ->where('sku', $mapping['sku'])
                    ->sole();

                $purchaseUnit = UnitOfMeasure::query()
                    ->where('organization_id', $organization->id)
                    ->where('symbol', $mapping['unit'])
                    ->where('active', true)
                    ->sole();

                $supplierItem = $saveSupplierItem->handle(
                    $organization,
                    $suppliers[$mapping['supplier']],
                    [
                        'inventory_item_id' => $inventoryItem->id,
                        'supplier_sku' => $mapping['supplier_sku'],
                        'description' => $mapping['description'],
                        'purchase_unit_of_measure_id' => $purchaseUnit->id,
                        'base_quantity' => $mapping['base_quantity'],
                        'active' => true,
                    ],
                );

                $supplierItems[$mapping['supplier_sku']] = $supplierItem;
            }

            Carbon::setTestNow('2026-06-15 09:00:00');

            foreach ($mappings as $mapping) {
                if ($mapping['previous_price'] === null) {
                    continue;
                }

                $recordSupplierItemPrice->handle(
                    $organization,
                    $supplierItems[$mapping['supplier_sku']],
                    $mapping['previous_price'],
                );
            }

            Carbon::setTestNow('2026-08-01 08:00:00');

            foreach ($mappings as $mapping) {
                $recordSupplierItemPrice->handle(
                    $organization,
                    $supplierItems[$mapping['supplier_sku']],
                    $mapping['current_price'],
                );
            }
        } finally {
            Carbon::setTestNow();
        }
    }
}
