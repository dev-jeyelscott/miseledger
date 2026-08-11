<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create item-specific conversions into each item's base unit.
     */
    public function up(): void
    {
        Schema::create('inventory_item_units', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('inventory_item_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('unit_of_measure_id')
                ->constrained('units_of_measure')
                ->restrictOnDelete();

            $table->decimal('quantity_in_base_unit', 20, 6);
            $table->boolean('active')->default(true);
            $table->timestampsTz();

            $table->unique([
                'inventory_item_id',
                'unit_of_measure_id',
            ]);

            $table->index([
                'inventory_item_id',
                'active',
            ]);
        });
    }

    /**
     * Remove item-specific UOM conversions.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_item_units');
    }
};
