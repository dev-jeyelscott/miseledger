<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add CHECK constraint requiring quantity_in_base_unit > 0.
     */
    public function up(): void
    {
        Schema::table('inventory_item_units', function (Blueprint $table): void {
            $table->check('quantity_in_base_unit > 0')
                ->name('chk_inventory_item_units_quantity_positive');
        });
    }

    /**
     * Remove CHECK constraint.
     */
    public function down(): void
    {
        Schema::table('inventory_item_units', function (Blueprint $table): void {
            $table->dropCheck('chk_inventory_item_units_quantity_positive');
        });
    }
};
