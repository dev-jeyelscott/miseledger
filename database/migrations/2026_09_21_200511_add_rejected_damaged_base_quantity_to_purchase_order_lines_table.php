<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Track cumulative rejected/damaged evidence across finalized receipts.
     */
    public function up(): void
    {
        Schema::table('purchase_order_lines', function (Blueprint $table): void {
            $table->decimal('rejected_damaged_base_quantity', 15, 6)->default(0);
        });

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement(<<<'SQL'
                ALTER TABLE purchase_order_lines
                ADD CONSTRAINT purchase_order_lines_rejected_damaged_non_negative
                CHECK (rejected_damaged_base_quantity >= 0)
            SQL);
        }
    }

    /**
     * Drop the cumulative rejected/damaged tracking column.
     */
    public function down(): void
    {
        Schema::table('purchase_order_lines', function (Blueprint $table): void {
            $table->dropColumn('rejected_damaged_base_quantity');
        });
    }
};
