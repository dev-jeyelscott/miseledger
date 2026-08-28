<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Align the existing barcode schema with the inventory-item barcode domain.
     */
    public function up(): void
    {
        Schema::rename('barcodes', 'inventory_item_barcodes');

        Schema::table(
            'inventory_item_barcodes',
            function (Blueprint $table): void {
                $table->renameColumn('value', 'barcode');
                $table->renameColumn('is_primary', 'primary');
            },
        );

        DB::statement(
            <<<'SQL'
            ALTER INDEX IF EXISTS barcodes_one_primary_per_item
            RENAME TO inventory_item_barcodes_one_primary_per_item
            SQL,
        );
    }

    /**
     * Restore the previous barcode schema names.
     */
    public function down(): void
    {
        DB::statement(
            <<<'SQL'
            ALTER INDEX IF EXISTS inventory_item_barcodes_one_primary_per_item
            RENAME TO barcodes_one_primary_per_item
            SQL,
        );

        Schema::table(
            'inventory_item_barcodes',
            function (Blueprint $table): void {
                $table->renameColumn('barcode', 'value');
                $table->renameColumn('primary', 'is_primary');
            },
        );

        Schema::rename('inventory_item_barcodes', 'barcodes');
    }
};
