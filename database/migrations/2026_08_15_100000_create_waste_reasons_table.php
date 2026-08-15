<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create organization-scoped configurable waste reasons.
     */
    public function up(): void
    {
        Schema::create('waste_reasons', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('organization_id')
                ->constrained()
                ->restrictOnDelete();

            $table->string('name', 100);
            $table->boolean('active')->default(true);
            $table->timestampsTz();

            $table->unique([
                'organization_id',
                'name',
            ]);

            $table->index([
                'organization_id',
                'active',
            ]);
        });
    }

    /**
     * Remove waste-reason configuration.
     */
    public function down(): void
    {
        Schema::dropIfExists('waste_reasons');
    }
};
