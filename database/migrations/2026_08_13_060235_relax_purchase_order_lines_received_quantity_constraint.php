<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Allow accepted receipt quantity to exceed the ordered base quantity.
     */
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement(<<<'SQL'
            ALTER TABLE purchase_order_lines
            DROP CONSTRAINT purchase_order_lines_quantities_valid,
            ADD CONSTRAINT purchase_order_lines_quantities_valid
            CHECK (
                ordered_quantity > 0
                AND base_quantity > 0
                AND received_base_quantity >= 0
            )
        SQL);
    }

    /**
     * Restore the original upper bound only when no valid over-receipt exists.
     */
    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        $hasOverReceivedData = DB::table('purchase_order_lines')
            ->whereColumn('received_base_quantity', '>', 'base_quantity')
            ->exists();

        if ($hasOverReceivedData) {
            throw new RuntimeException(
                'Cannot restore the purchase-order received quantity upper bound while over-receipt data exists.',
            );
        }

        DB::statement(<<<'SQL'
            ALTER TABLE purchase_order_lines
            DROP CONSTRAINT purchase_order_lines_quantities_valid,
            ADD CONSTRAINT purchase_order_lines_quantities_valid
            CHECK (
                ordered_quantity > 0
                AND base_quantity > 0
                AND received_base_quantity >= 0
                AND received_base_quantity <= base_quantity
            )
        SQL);
    }
};
