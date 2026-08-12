<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create the rebuildable current-stock projection.
     */
    public function up(): void
    {
        Schema::create('stock_balances', function (Blueprint $table): void {
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

            $table->decimal('quantity_on_hand', 15, 6)->default(0);
            $table->decimal('average_unit_cost', 15, 4)->default(0);
            $table->decimal('inventory_value', 15, 4)->default(0);
            $table->timestampTz('last_movement_at')->nullable();
            $table->timestampsTz();

            $table->unique(
                [
                    'organization_id',
                    'location_id',
                    'storage_location_id',
                    'inventory_item_id',
                ],
                'stock_balances_identity_unique',
            );

            $table->index([
                'organization_id',
                'location_id',
                'inventory_item_id',
            ], 'stock_balances_org_location_item_idx');

            $table->index([
                'organization_id',
                'inventory_item_id',
            ], 'stock_balances_org_item_idx');
        });

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement(<<<'SQL'
                ALTER TABLE stock_balances
                ADD CONSTRAINT stock_balances_average_unit_cost_non_negative
                CHECK (average_unit_cost >= 0)
            SQL);
        }
    }

    /**
     * Remove the rebuildable stock projection.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_balances');
    }
};
