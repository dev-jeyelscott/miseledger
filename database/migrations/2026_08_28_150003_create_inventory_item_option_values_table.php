<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create tenant-safe associations between a product-family variant item
     * and the controlled option values it represents.
     */
    public function up(): void
    {
        Schema::table('inventory_product_option_values', function (Blueprint $table): void {
            $table->unique(
                ['organization_id', 'id'],
                'inventory_product_option_values_organization_id_id_unique',
            );
        });

        Schema::create('inventory_item_option_values', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('organization_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->unsignedBigInteger('inventory_item_id');
            $table->unsignedBigInteger('inventory_product_option_value_id');

            $table->timestampsTz();

            $table->unique(
                ['inventory_item_id', 'inventory_product_option_value_id'],
                'inventory_item_option_values_item_value_unique',
            );
            $table->index(['organization_id', 'inventory_item_id']);

            $table->foreign(
                ['organization_id', 'inventory_item_id'],
                'inventory_item_option_values_organization_item_foreign',
            )
                ->references(['organization_id', 'id'])
                ->on('inventory_items')
                ->cascadeOnDelete();

            $table->foreign(
                ['organization_id', 'inventory_product_option_value_id'],
                'inventory_item_option_values_organization_value_foreign',
            )
                ->references(['organization_id', 'id'])
                ->on('inventory_product_option_values')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_item_option_values');

        Schema::table('inventory_product_option_values', function (Blueprint $table): void {
            $table->dropUnique(
                'inventory_product_option_values_organization_id_id_unique',
            );
        });
    }
};
