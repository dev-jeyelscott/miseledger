<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create append-only historical supplier prices.
     */
    public function up(): void
    {
        Schema::create('supplier_item_prices', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('organization_id')
                ->constrained()
                ->restrictOnDelete();

            $table->foreignId('supplier_item_id')
                ->constrained()
                ->restrictOnDelete();

            $table->decimal('price', 15, 4);
            $table->char('currency', 3);
            $table->timestampTz('effective_at');
            $table->timestampTz('created_at');

            $table->index(
                [
                    'supplier_item_id',
                    'effective_at',
                ],
                'supplier_item_prices_item_effective_idx',
            );

            $table->index(
                [
                    'organization_id',
                    'effective_at',
                ],
                'supplier_item_prices_org_effective_idx',
            );
        });

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement(<<<'SQL'
                ALTER TABLE supplier_item_prices
                ADD CONSTRAINT supplier_item_prices_price_non_negative
                CHECK (price >= 0)
            SQL);
        }
    }

    /**
     * Remove supplier price history.
     */
    public function down(): void
    {
        Schema::dropIfExists('supplier_item_prices');
    }
};
