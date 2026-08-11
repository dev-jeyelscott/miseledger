<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create organization-scoped units of measure.
     */
    public function up(): void
    {
        Schema::create('units_of_measure', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('organization_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('name', 100);
            $table->string('symbol', 32);
            $table->boolean('active')->default(true);
            $table->timestampsTz();

            $table->unique(['organization_id', 'name']);
            $table->unique(['organization_id', 'symbol']);
            $table->index(['organization_id', 'active']);
        });
    }

    /**
     * Remove the unit-of-measure master.
     */
    public function down(): void
    {
        Schema::dropIfExists('units_of_measure');
    }
};
