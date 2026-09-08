<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Add CHECK constraint requiring quantity_in_base_unit > 0.
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE inventory_item_units ADD CONSTRAINT chk_inventory_item_units_quantity_positive CHECK (quantity_in_base_unit > 0)');
    }

    /**
     * Remove CHECK constraint.
     */
    public function down(): void
    {
        DB::statement('ALTER TABLE inventory_item_units DROP CONSTRAINT chk_inventory_item_units_quantity_positive');
    }
};
