<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_categories', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('organization_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('name', 100);
            $table->boolean('active')->default(true);
            $table->timestampsTz();

            $table->unique(['organization_id', 'name']);
            $table->index(['organization_id', 'active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_categories');
    }
};
