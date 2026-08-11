<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create organization-scoped inventory item master records.
     */
    public function up(): void
    {
        Schema::create('inventory_items', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('organization_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('base_unit_of_measure_id')
                ->constrained('units_of_measure')
                ->restrictOnDelete();

            $table->string('name', 160);
            $table->string('sku', 80);
            $table->boolean('active')->default(true);
            $table->timestampsTz();

            $table->unique(['organization_id', 'sku']);
            $table->index(['organization_id', 'active']);
        });
    }

    /**
     * Remove inventory item master records.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_items');
    }
};
