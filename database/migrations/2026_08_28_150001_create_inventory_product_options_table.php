<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create controlled option dimensions (e.g. Size, Color) owned by a
     * single organization-scoped product family.
     */
    public function up(): void
    {
        Schema::create('inventory_product_options', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('organization_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->unsignedBigInteger('inventory_product_id');

            $table->string('name', 100);
            $table->boolean('active')->default(true);
            $table->timestampsTz();

            $table->unique(['inventory_product_id', 'name']);
            $table->unique(
                ['organization_id', 'id'],
                'inventory_product_options_organization_id_id_unique',
            );
            $table->index(['organization_id', 'active']);

            $table->foreign(
                ['organization_id', 'inventory_product_id'],
                'inventory_product_options_organization_product_foreign',
            )
                ->references(['organization_id', 'id'])
                ->on('inventory_products')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_product_options');
    }
};
