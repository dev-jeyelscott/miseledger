<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create goods receipt line snapshots.
     */
    public function up(): void
    {
        Schema::create('goods_receipt_lines', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('goods_receipt_id')
                ->constrained()
                ->restrictOnDelete();

            $table->foreignId('purchase_order_line_id')
                ->constrained()
                ->restrictOnDelete();

            $table->foreignId('inventory_item_id')
                ->constrained()
                ->restrictOnDelete();

            $table->foreignId('storage_location_id')
                ->constrained()
                ->restrictOnDelete();

            $table->decimal('received_quantity', 15, 6);

            $table->foreignId('received_unit_of_measure_id')
                ->constrained('units_of_measure')
                ->restrictOnDelete();

            $table->decimal('base_quantity', 15, 6);
            $table->decimal('unit_cost', 15, 4);
            $table->decimal('total_cost', 15, 4);

            $table->text('notes')->nullable();
            $table->timestampTz('created_at')->useCurrent();

            $table->index('goods_receipt_id');
            $table->index('purchase_order_line_id');
        });

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement(<<<'SQL'
                ALTER TABLE goods_receipt_lines
                ADD CONSTRAINT goods_receipt_lines_quantities_positive
                CHECK (
                    received_quantity > 0
                    AND base_quantity > 0
                )
            SQL);

            DB::statement(<<<'SQL'
                ALTER TABLE goods_receipt_lines
                ADD CONSTRAINT goods_receipt_lines_costs_non_negative
                CHECK (
                    unit_cost >= 0
                    AND total_cost >= 0
                )
            SQL);
        }
    }

    /**
     * Remove goods receipt lines.
     */
    public function down(): void
    {
        Schema::dropIfExists('goods_receipt_lines');
    }
};
