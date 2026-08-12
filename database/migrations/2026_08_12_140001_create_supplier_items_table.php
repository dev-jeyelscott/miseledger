<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create supplier-specific purchase-pack mappings.
     */
    public function up(): void
    {
        Schema::create('supplier_items', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('organization_id')
                ->constrained()
                ->restrictOnDelete();

            $table->foreignId('supplier_id')
                ->constrained()
                ->restrictOnDelete();

            $table->foreignId('inventory_item_id')
                ->constrained()
                ->restrictOnDelete();

            $table->string('supplier_sku', 100);
            $table->string('description', 255)->nullable();

            $table->foreignId('purchase_unit_of_measure_id')
                ->constrained('units_of_measure')
                ->restrictOnDelete();

            $table->decimal('base_quantity', 15, 6);
            $table->decimal('current_price', 15, 4)->nullable();
            $table->char('currency', 3);
            $table->boolean('active')->default(true);
            $table->timestampsTz();

            $table->unique([
                'supplier_id',
                'supplier_sku',
            ]);

            $table->index(
                [
                    'organization_id',
                    'inventory_item_id',
                    'active',
                ],
                'supplier_items_org_item_active_idx',
            );

            $table->index(
                [
                    'supplier_id',
                    'inventory_item_id',
                ],
                'supplier_items_supplier_item_idx',
            );
        });

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement(<<<'SQL'
                ALTER TABLE supplier_items
                ADD CONSTRAINT supplier_items_base_quantity_positive
                CHECK (base_quantity > 0)
            SQL);

            DB::statement(<<<'SQL'
                ALTER TABLE supplier_items
                ADD CONSTRAINT supplier_items_current_price_non_negative
                CHECK (
                    current_price IS NULL
                    OR current_price >= 0
                )
            SQL);
        }
    }

    /**
     * Remove supplier-item mappings.
     */
    public function down(): void
    {
        Schema::dropIfExists('supplier_items');
    }
};
