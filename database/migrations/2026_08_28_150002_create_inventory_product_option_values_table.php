<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create controlled values (e.g. Small, Red) owned by a single
     * organization-scoped product option dimension.
     */
    public function up(): void
    {
        Schema::create('inventory_product_option_values', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('organization_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->unsignedBigInteger('inventory_product_option_id');

            $table->string('value', 100);
            $table->boolean('active')->default(true);
            $table->timestampsTz();

            $table->unique(['inventory_product_option_id', 'value']);
            $table->index(['organization_id', 'active']);

            $table->foreign(
                ['organization_id', 'inventory_product_option_id'],
                'inventory_product_option_values_organization_option_foreign',
            )
                ->references(['organization_id', 'id'])
                ->on('inventory_product_options')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_product_option_values');
    }
};
