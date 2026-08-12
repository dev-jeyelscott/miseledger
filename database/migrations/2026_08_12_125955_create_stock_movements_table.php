<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create the authoritative append-oriented inventory ledger.
     */
    public function up(): void
    {
        Schema::create('stock_movements', function (Blueprint $table): void {
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

            $table->string('type', 40);
            $table->decimal('quantity', 15, 6);

            $table->foreignId('base_unit_of_measure_id')
                ->constrained('units_of_measure')
                ->restrictOnDelete();

            $table->decimal('unit_cost', 15, 4)->nullable();
            $table->decimal('total_cost', 15, 4)->nullable();

            $table->string('reference_type', 50);
            $table->unsignedBigInteger('reference_id');
            $table->timestampTz('occurred_at');

            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->restrictOnDelete();

            $table->string('idempotency_key', 180)->nullable();
            $table->text('notes')->nullable();
            $table->timestampTz('created_at');

            $table->index([
                'organization_id',
                'inventory_item_id',
                'occurred_at',
            ], 'stock_movements_org_item_occurred_idx');

            $table->index([
                'organization_id',
                'location_id',
                'occurred_at',
            ], 'stock_movements_org_location_occurred_idx');

            $table->index([
                'organization_id',
                'storage_location_id',
                'occurred_at',
            ], 'stock_movements_org_storage_occurred_idx');

            $table->index([
                'organization_id',
                'type',
                'occurred_at',
            ], 'stock_movements_org_type_occurred_idx');

            $table->index([
                'reference_type',
                'reference_id',
            ], 'stock_movements_reference_idx');
        });

        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX stock_movements_org_idempotency_unique
            ON stock_movements (organization_id, idempotency_key)
            WHERE idempotency_key IS NOT NULL
        SQL);

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement(<<<'SQL'
                ALTER TABLE stock_movements
                ADD CONSTRAINT stock_movements_quantity_non_zero
                CHECK (quantity <> 0)
            SQL);

            DB::statement(<<<'SQL'
                ALTER TABLE stock_movements
                ADD CONSTRAINT stock_movements_unit_cost_non_negative
                CHECK (unit_cost IS NULL OR unit_cost >= 0)
            SQL);

            DB::statement(<<<'SQL'
                ALTER TABLE stock_movements
                ADD CONSTRAINT stock_movements_total_cost_non_negative
                CHECK (total_cost IS NULL OR total_cost >= 0)
            SQL);
        }
    }

    /**
     * Remove the stock ledger after dependent projections are removed.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
