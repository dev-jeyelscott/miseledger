<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create organization-scoped recipe master records.
     */
    public function up(): void
    {
        Schema::create('recipes', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('organization_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('code', 80);
            $table->string('name', 160);
            $table->enum('type', [
                'menu_item',
                'prepared_item',
                'batch',
            ]);
            $table->boolean('active')->default(true);
            $table->timestampsTz();

            $table->unique(['organization_id', 'code']);
            $table->index(['organization_id', 'active']);
            $table->index(['organization_id', 'type']);
        });
    }

    /**
     * Remove recipe master records.
     */
    public function down(): void
    {
        Schema::dropIfExists('recipes');
    }
};
