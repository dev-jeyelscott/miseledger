<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create immutable finalized waste evidence.
     */
    public function up(): void
    {
        Schema::create('waste_records', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('organization_id')
                ->constrained()
                ->restrictOnDelete();

            $table->foreignId('location_id')
                ->constrained()
                ->restrictOnDelete();

            $table->foreignId('storage_location_id')
                ->constrained()
                ->restrictOnDelete();

            $table->foreignId('inventory_item_id')
                ->constrained()
                ->restrictOnDelete();

            $table->foreignId('waste_reason_id')
                ->constrained()
                ->restrictOnDelete();

            /*
             * Stable business-operation identity protects single-step waste
             * creation from browser retries and concurrent duplicate submits.
             */
            $table->uuid('operation_id');

            $table->decimal('quantity', 15, 6);

            $table->foreignId('unit_id')
                ->constrained('units_of_measure')
                ->restrictOnDelete();

            $table->decimal('base_quantity', 15, 6);
            $table->decimal('unit_cost', 15, 4);
            $table->decimal('total_cost', 15, 4);

            $table->timestampTz('occurred_at');

            $table->foreignId('recorded_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->text('notes')->nullable();
            $table->timestampTz('created_at')->useCurrent();

            $table->unique([
                'organization_id',
                'operation_id',
            ], 'waste_records_org_operation_unique');

            $table->index([
                'organization_id',
                'location_id',
                'occurred_at',
            ], 'waste_records_org_location_occurred_idx');

            $table->index([
                'organization_id',
                'inventory_item_id',
                'occurred_at',
            ], 'waste_records_org_item_occurred_idx');

            $table->index([
                'waste_reason_id',
                'occurred_at',
            ], 'waste_records_reason_occurred_idx');
        });

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement(<<<'SQL'
                ALTER TABLE waste_records
                ADD CONSTRAINT waste_records_quantity_positive
                CHECK (quantity > 0)
            SQL);

            DB::statement(<<<'SQL'
                ALTER TABLE waste_records
                ADD CONSTRAINT waste_records_base_quantity_positive
                CHECK (base_quantity > 0)
            SQL);

            DB::statement(<<<'SQL'
                ALTER TABLE waste_records
                ADD CONSTRAINT waste_records_unit_cost_non_negative
                CHECK (unit_cost >= 0)
            SQL);

            DB::statement(<<<'SQL'
                ALTER TABLE waste_records
                ADD CONSTRAINT waste_records_total_cost_non_negative
                CHECK (total_cost >= 0)
            SQL);
        }
    }

    /**
     * Remove immutable waste evidence.
     */
    public function down(): void
    {
        Schema::dropIfExists('waste_records');
    }
};
