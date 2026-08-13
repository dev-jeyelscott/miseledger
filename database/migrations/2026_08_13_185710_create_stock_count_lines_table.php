<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create physical-count evidence and immutable finalization snapshots.
     */
    public function up(): void
    {
        Schema::create('stock_count_lines', function (Blueprint $table): void {
            $table->id();

            $table
                ->foreignId('stock_count_id')
                ->constrained()
                ->restrictOnDelete();

            $table
                ->foreignId('inventory_item_id')
                ->constrained()
                ->restrictOnDelete();

            $table
                ->decimal('expected_base_quantity', 15, 6)
                ->default(0);

            $table->decimal('counted_quantity', 15, 6);

            $table
                ->foreignId('count_unit_id')
                ->constrained('units_of_measure')
                ->restrictOnDelete();

            $table->decimal('counted_base_quantity', 15, 6);

            $table
                ->decimal('variance_base_quantity', 15, 6)
                ->default(0);

            $table
                ->decimal('variance_unit_cost', 15, 4)
                ->default(0);

            $table
                ->decimal('variance_total_cost', 15, 4)
                ->default(0);

            $table->text('notes')->nullable();

            $table->timestampsTz();

            $table->unique([
                'stock_count_id',
                'inventory_item_id',
            ]);
        });

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement(
                <<<'SQL'
                ALTER TABLE stock_count_lines
                ADD CONSTRAINT stock_count_lines_counted_quantity_check
                CHECK (counted_quantity >= 0)
                SQL,
            );

            DB::statement(
                <<<'SQL'
                ALTER TABLE stock_count_lines
                ADD CONSTRAINT stock_count_lines_counted_base_quantity_check
                CHECK (counted_base_quantity >= 0)
                SQL,
            );

            DB::statement(
                <<<'SQL'
                ALTER TABLE stock_count_lines
                ADD CONSTRAINT stock_count_lines_variance_unit_cost_check
                CHECK (variance_unit_cost >= 0)
                SQL,
            );
        }
    }

    /**
     * Remove physical-count line storage.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_count_lines');
    }
};
