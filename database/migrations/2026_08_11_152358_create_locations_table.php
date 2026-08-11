<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create organization-scoped restaurant locations.
     */
    public function up(): void
    {
        Schema::create('locations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->string('name', 160);
            $table->string('code', 32);
            $table->boolean('active')->default(true);
            $table->timestampsTz();

            $table->unique(['organization_id', 'code']);
            $table->index(['organization_id', 'active']);
        });
    }

    /**
     * Remove organization locations.
     */
    public function down(): void
    {
        Schema::dropIfExists('locations');
    }
};
