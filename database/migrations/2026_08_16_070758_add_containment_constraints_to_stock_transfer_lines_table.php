<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Enforce organization containment for every stock-transfer line reference.
     */
    public function up(): void
    {
        Schema::table('stock_transfer_lines', function (Blueprint $table): void {
            $table->unsignedBigInteger('organization_id')->nullable();
        });

        /*
         * Populate only the new containment key from the authoritative parent.
         * Existing transfer evidence is intentionally left unchanged.
         */
        DB::table('stock_transfer_lines')->update([
            'organization_id' => DB::raw(
                <<<'SQL'
                (
                    SELECT stock_transfers.organization_id
                    FROM stock_transfers
                    WHERE stock_transfers.id = stock_transfer_lines.stock_transfer_id
                )
                SQL,
            ),
        ]);

        if (
            DB::table('stock_transfer_lines')
                ->whereNull('organization_id')
                ->exists()
        ) {
            throw new RuntimeException(
                'Stock-transfer line containment cannot be enforced because one or more lines have no resolvable parent organization.',
            );
        }

        Schema::table('stock_transfer_lines', function (Blueprint $table): void {
            $table->unsignedBigInteger('organization_id')->change();
        });

        Schema::table('stock_transfers', function (Blueprint $table): void {
            $table->unique(
                ['organization_id', 'id'],
                'stock_transfers_organization_id_id_unique',
            );
        });

        Schema::table('stock_transfer_lines', function (Blueprint $table): void {
            $table->foreign(
                ['organization_id', 'stock_transfer_id'],
                'stock_transfer_lines_organization_transfer_foreign',
            )
                ->references(['organization_id', 'id'])
                ->on('stock_transfers')
                ->restrictOnDelete();

            $table->foreign(
                ['organization_id', 'inventory_item_id'],
                'stock_transfer_lines_organization_item_foreign',
            )
                ->references(['organization_id', 'id'])
                ->on('inventory_items')
                ->restrictOnDelete();

            $table->foreign(
                ['organization_id', 'unit_id'],
                'stock_transfer_lines_organization_unit_foreign',
            )
                ->references(['organization_id', 'id'])
                ->on('units_of_measure')
                ->restrictOnDelete();
        });
    }

    /**
     * Remove stock-transfer-line tenant-containment constraints.
     */
    public function down(): void
    {
        Schema::table('stock_transfer_lines', function (Blueprint $table): void {
            $table->dropForeign(
                'stock_transfer_lines_organization_unit_foreign',
            );
            $table->dropForeign(
                'stock_transfer_lines_organization_item_foreign',
            );
            $table->dropForeign(
                'stock_transfer_lines_organization_transfer_foreign',
            );
        });

        Schema::table('stock_transfers', function (Blueprint $table): void {
            $table->dropUnique(
                'stock_transfers_organization_id_id_unique',
            );
        });

        Schema::table('stock_transfer_lines', function (Blueprint $table): void {
            $table->dropColumn('organization_id');
        });
    }
};
