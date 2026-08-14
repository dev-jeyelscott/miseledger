<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Enforce that every movement's related records share its tenant context.
     */
    public function up(): void
    {
        Schema::table('storage_locations', function (Blueprint $table): void {
            $table->unique(
                ['organization_id', 'location_id', 'id'],
                'storage_locations_organization_location_id_unique',
            );
        });

        Schema::table('inventory_items', function (Blueprint $table): void {
            $table->unique(
                ['organization_id', 'id'],
                'inventory_items_organization_id_id_unique',
            );
        });

        Schema::table('units_of_measure', function (Blueprint $table): void {
            $table->unique(
                ['organization_id', 'id'],
                'units_of_measure_organization_id_id_unique',
            );
        });

        Schema::table('stock_movements', function (Blueprint $table): void {
            $table->foreign(
                ['organization_id', 'location_id'],
                'stock_movements_organization_location_foreign',
            )
                ->references(['organization_id', 'id'])
                ->on('locations')
                ->restrictOnDelete();

            $table->foreign(
                [
                    'organization_id',
                    'location_id',
                    'storage_location_id',
                ],
                'stock_movements_organization_location_storage_foreign',
            )
                ->references(['organization_id', 'location_id', 'id'])
                ->on('storage_locations')
                ->restrictOnDelete();

            $table->foreign(
                ['organization_id', 'inventory_item_id'],
                'stock_movements_organization_item_foreign',
            )
                ->references(['organization_id', 'id'])
                ->on('inventory_items')
                ->restrictOnDelete();

            $table->foreign(
                ['organization_id', 'base_unit_of_measure_id'],
                'stock_movements_organization_base_unit_foreign',
            )
                ->references(['organization_id', 'id'])
                ->on('units_of_measure')
                ->restrictOnDelete();
        });
    }

    /**
     * Remove tenant-containment constraints from the ledger.
     */
    public function down(): void
    {
        Schema::table('stock_movements', function (Blueprint $table): void {
            $table->dropForeign(
                'stock_movements_organization_base_unit_foreign',
            );
            $table->dropForeign(
                'stock_movements_organization_item_foreign',
            );
            $table->dropForeign(
                'stock_movements_organization_location_storage_foreign',
            );
            $table->dropForeign(
                'stock_movements_organization_location_foreign',
            );
        });

        Schema::table('units_of_measure', function (Blueprint $table): void {
            $table->dropUnique(
                'units_of_measure_organization_id_id_unique',
            );
        });

        Schema::table('inventory_items', function (Blueprint $table): void {
            $table->dropUnique(
                'inventory_items_organization_id_id_unique',
            );
        });

        Schema::table('storage_locations', function (Blueprint $table): void {
            $table->dropUnique(
                'storage_locations_organization_location_id_unique',
            );
        });
    }
};
