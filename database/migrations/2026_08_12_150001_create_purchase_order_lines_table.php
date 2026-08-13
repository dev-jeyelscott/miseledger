<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create immutable purchase-order line snapshots.
     */
    public function up(): void
    {
        Schema::create('purchase_order_lines', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('purchase_order_id')
                ->constrained()
                ->restrictOnDelete();

            $table->foreignId('supplier_item_id')
                ->constrained()
                ->restrictOnDelete();

            $table->foreignId('inventory_item_id')
                ->constrained()
                ->restrictOnDelete();

            $table->string('item_name_snapshot', 180);
            $table->string('supplier_sku_snapshot', 100);

            $table->decimal('ordered_quantity', 15, 6);

            $table->foreignId('purchase_unit_of_measure_id')
                ->constrained('units_of_measure')
                ->restrictOnDelete();

            $table->decimal('base_quantity', 15, 6);
            $table->decimal('unit_price', 15, 4);
            $table->decimal('line_total', 15, 2);
            $table->decimal('received_base_quantity', 15, 6)->default(0);

            $table->timestampsTz();

            $table->index('purchase_order_id');
            $table->index('inventory_item_id');
        });

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement(<<<'SQL'
                ALTER TABLE purchase_order_lines
                ADD CONSTRAINT purchase_order_lines_quantities_valid
                CHECK (
                    ordered_quantity > 0
                    AND base_quantity > 0
                    AND received_base_quantity >= 0
                    AND received_base_quantity <= base_quantity
                )
            SQL);

            DB::statement(<<<'SQL'
                ALTER TABLE purchase_order_lines
                ADD CONSTRAINT purchase_order_lines_costs_non_negative
                CHECK (
                    unit_price >= 0
                    AND line_total >= 0
                )
            SQL);
        }
    }

    /**
     * Remove purchase order line snapshots.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_order_lines');
    }
};
