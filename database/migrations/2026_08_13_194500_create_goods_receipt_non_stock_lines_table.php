<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create rejected and damaged receiving evidence that never enters stock.
     */
    public function up(): void
    {
        Schema::create('goods_receipt_non_stock_lines', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('goods_receipt_id')
                ->constrained()
                ->restrictOnDelete();

            $table->foreignId('goods_receipt_line_id')
                ->nullable()
                ->constrained('goods_receipt_lines')
                ->restrictOnDelete();

            $table->foreignId('purchase_order_line_id')
                ->constrained()
                ->restrictOnDelete();

            $table->foreignId('inventory_item_id')
                ->constrained()
                ->restrictOnDelete();

            $table->decimal('rejected_quantity', 15, 6)->nullable();
            $table->foreignId('rejected_unit_of_measure_id')
                ->nullable()
                ->constrained('units_of_measure')
                ->restrictOnDelete();
            $table->decimal('rejected_base_quantity', 15, 6)->nullable();

            $table->decimal('damaged_quantity', 15, 6)->nullable();
            $table->foreignId('damaged_unit_of_measure_id')
                ->nullable()
                ->constrained('units_of_measure')
                ->restrictOnDelete();
            $table->decimal('damaged_base_quantity', 15, 6)->nullable();

            $table->text('notes')->nullable();
            $table->timestampTz('created_at')->useCurrent();

            $table->unique('goods_receipt_line_id');
            $table->index('goods_receipt_id');
            $table->index('purchase_order_line_id');
        });

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement(<<<'SQL'
                ALTER TABLE goods_receipt_non_stock_lines
                ADD CONSTRAINT goods_receipt_non_stock_lines_rejected_shape_valid
                CHECK (
                    (
                        rejected_quantity IS NULL
                        AND rejected_unit_of_measure_id IS NULL
                        AND rejected_base_quantity IS NULL
                    )
                    OR
                    (
                        rejected_quantity > 0
                        AND rejected_unit_of_measure_id IS NOT NULL
                        AND rejected_base_quantity > 0
                    )
                )
            SQL);

            DB::statement(<<<'SQL'
                ALTER TABLE goods_receipt_non_stock_lines
                ADD CONSTRAINT goods_receipt_non_stock_lines_damaged_shape_valid
                CHECK (
                    (
                        damaged_quantity IS NULL
                        AND damaged_unit_of_measure_id IS NULL
                        AND damaged_base_quantity IS NULL
                    )
                    OR
                    (
                        damaged_quantity > 0
                        AND damaged_unit_of_measure_id IS NOT NULL
                        AND damaged_base_quantity > 0
                    )
                )
            SQL);

            DB::statement(<<<'SQL'
                ALTER TABLE goods_receipt_non_stock_lines
                ADD CONSTRAINT goods_receipt_non_stock_lines_has_evidence
                CHECK (
                    rejected_quantity IS NOT NULL
                    OR damaged_quantity IS NOT NULL
                )
            SQL);
        }
    }

    /**
     * Remove non-stock receiving evidence.
     */
    public function down(): void
    {
        Schema::dropIfExists('goods_receipt_non_stock_lines');
    }
};
