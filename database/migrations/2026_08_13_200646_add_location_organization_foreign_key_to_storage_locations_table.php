<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Enforce that a storage location's tenant matches its restaurant location.
     */
    public function up(): void
    {
        Schema::table('locations', function (Blueprint $table): void {
            $table->unique(
                ['organization_id', 'id'],
                'locations_organization_id_id_unique',
            );
        });

        Schema::table('storage_locations', function (Blueprint $table): void {
            $table->foreign(
                ['organization_id', 'location_id'],
                'storage_locations_organization_location_foreign',
            )
                ->references(['organization_id', 'id'])
                ->on('locations')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
        });
    }

    /**
     * Remove the tenant/location containment constraint.
     */
    public function down(): void
    {
        Schema::table('storage_locations', function (Blueprint $table): void {
            $table->dropForeign(
                'storage_locations_organization_location_foreign',
            );
        });

        Schema::table('locations', function (Blueprint $table): void {
            $table->dropUnique('locations_organization_id_id_unique');
        });
    }
};
