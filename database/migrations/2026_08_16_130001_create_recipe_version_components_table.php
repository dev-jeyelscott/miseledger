<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create item components consumed by a recipe version.
     */
    public function up(): void
    {
        Schema::create('recipe_version_components', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('recipe_version_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('inventory_item_id')
                ->constrained()
                ->restrictOnDelete();

            $table->decimal('quantity', 15, 6);

            $table->foreignId('unit_of_measure_id')
                ->constrained('units_of_measure')
                ->restrictOnDelete();

            $table->decimal('base_quantity', 15, 6);
            $table->decimal('yield_percentage', 5, 2)->default(100);

            $table->text('notes')->nullable();

            $table->timestampsTz();

            $table->unique(['recipe_version_id', 'inventory_item_id']);
        });

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement(
                <<<'SQL'
                ALTER TABLE recipe_version_components
                ADD CONSTRAINT recipe_version_components_quantity_check
                CHECK (quantity > 0)
                SQL,
            );

            DB::statement(
                <<<'SQL'
                ALTER TABLE recipe_version_components
                ADD CONSTRAINT recipe_version_components_base_quantity_check
                CHECK (base_quantity > 0)
                SQL,
            );

            DB::statement(
                <<<'SQL'
                ALTER TABLE recipe_version_components
                ADD CONSTRAINT recipe_version_components_yield_percentage_check
                CHECK (yield_percentage > 0 AND yield_percentage <= 100)
                SQL,
            );
        }
    }

    /**
     * Remove recipe version component storage.
     */
    public function down(): void
    {
        Schema::dropIfExists('recipe_version_components');
    }
};
