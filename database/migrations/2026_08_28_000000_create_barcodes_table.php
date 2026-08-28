<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create organization-scoped barcode identities for inventory items.
     */
    public function up(): void
    {
        Schema::table('inventory_item_units', function (Blueprint $table): void {
            $table->unique(
                ['inventory_item_id', 'id'],
                'inventory_item_units_inventory_item_id_id_unique',
            );
        });

        Schema::create('barcodes', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('organization_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('inventory_item_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('inventory_item_unit_id')
                ->nullable()
                ->constrained()
                ->cascadeOnDelete();

            $table->string('value', 64);
            $table->string('symbology', 20);
            $table->boolean('is_primary')->default(false);
            $table->boolean('active')->default(true);
            $table->timestampsTz();

            $table->unique(['organization_id', 'value']);
            $table->index(['inventory_item_id', 'active']);

            $table->foreign(
                ['organization_id', 'inventory_item_id'],
                'barcodes_organization_item_foreign',
            )
                ->references(['organization_id', 'id'])
                ->on('inventory_items')
                ->restrictOnDelete();

            $table->foreign(
                ['inventory_item_id', 'inventory_item_unit_id'],
                'barcodes_item_unit_foreign',
            )
                ->references(['inventory_item_id', 'id'])
                ->on('inventory_item_units')
                ->restrictOnDelete();
        });

        /*
         * Ensure only one primary barcode can exist per item at a time.
         */
        DB::statement(
            <<<'SQL'
            CREATE UNIQUE INDEX barcodes_one_primary_per_item
            ON barcodes (inventory_item_id)
            WHERE is_primary = true
            SQL,
        );
    }

    /**
     * Remove barcode identities.
     */
    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS barcodes_one_primary_per_item');

        Schema::dropIfExists('barcodes');

        Schema::table('inventory_item_units', function (Blueprint $table): void {
            $table->dropUnique(
                'inventory_item_units_inventory_item_id_id_unique',
            );
        });
    }
};
