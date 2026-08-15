<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Enforce that every waste record's related records share its tenant context.
     */
    public function up(): void
    {
        Schema::table('waste_reasons', function (Blueprint $table): void {
            $table->unique(
                ['organization_id', 'id'],
                'waste_reasons_organization_id_id_unique',
            );
        });

        Schema::table('waste_records', function (Blueprint $table): void {
            $table->foreign(
                ['organization_id', 'location_id'],
                'waste_records_organization_location_foreign',
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
                'waste_records_organization_location_storage_foreign',
            )
                ->references([
                    'organization_id',
                    'location_id',
                    'id',
                ])
                ->on('storage_locations')
                ->restrictOnDelete();

            $table->foreign(
                ['organization_id', 'inventory_item_id'],
                'waste_records_organization_item_foreign',
            )
                ->references(['organization_id', 'id'])
                ->on('inventory_items')
                ->restrictOnDelete();

            $table->foreign(
                ['organization_id', 'waste_reason_id'],
                'waste_records_organization_waste_reason_foreign',
            )
                ->references(['organization_id', 'id'])
                ->on('waste_reasons')
                ->restrictOnDelete();

            $table->foreign(
                ['organization_id', 'unit_id'],
                'waste_records_organization_unit_foreign',
            )
                ->references(['organization_id', 'id'])
                ->on('units_of_measure')
                ->restrictOnDelete();
        });
    }

    /**
     * Remove tenant-containment constraints from waste records.
     */
    public function down(): void
    {
        Schema::table('waste_records', function (Blueprint $table): void {
            $table->dropForeign(
                'waste_records_organization_unit_foreign',
            );
            $table->dropForeign(
                'waste_records_organization_waste_reason_foreign',
            );
            $table->dropForeign(
                'waste_records_organization_item_foreign',
            );
            $table->dropForeign(
                'waste_records_organization_location_storage_foreign',
            );
            $table->dropForeign(
                'waste_records_organization_location_foreign',
            );
        });

        Schema::table('waste_reasons', function (Blueprint $table): void {
            $table->dropUnique(
                'waste_reasons_organization_id_id_unique',
            );
        });
    }
};
