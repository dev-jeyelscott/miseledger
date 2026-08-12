<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create organization/location-scoped physical storage locations.
     */
    public function up(): void
    {
        Schema::create('storage_locations', function (Blueprint $table): void {
            $table->id();

            $table
                ->foreignId('organization_id')
                ->constrained()
                ->cascadeOnDelete();

            $table
                ->foreignId('location_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('name', 160);
            $table->string('code', 60);
            $table->boolean('active')->default(true);
            $table->timestampsTz();

            $table->unique([
                'location_id',
                'code',
            ]);

            $table->index([
                'organization_id',
                'location_id',
                'active',
            ]);
        });

        $this->backfillDefaultStorageLocations();
    }

    /**
     * Drop the storage table before any ledger tables depend on it.
     */
    public function down(): void
    {
        Schema::dropIfExists('storage_locations');
    }

    /**
     * Give every existing location one usable default storage location.
     */
    private function backfillDefaultStorageLocations(): void
    {
        DB::table('locations')
            ->select([
                'id',
                'organization_id',
                'active',
            ])
            ->orderBy('id')
            ->chunkById(200, function ($locations): void {
                $timestamp = now();
                $rows = [];

                foreach ($locations as $location) {
                    $rows[] = [
                        'organization_id' => $location->organization_id,
                        'location_id' => $location->id,
                        'name' => 'Default Storage',
                        'code' => 'DEFAULT',
                        'active' => (bool) $location->active,
                        'created_at' => $timestamp,
                        'updated_at' => $timestamp,
                    ];
                }

                if ($rows !== []) {
                    DB::table('storage_locations')->insert($rows);
                }
            });
    }
};
