<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Allow a component to reference a published nested recipe version
     * instead of an inventory item.
     */
    public function up(): void
    {
        Schema::table('recipe_version_components', function (Blueprint $table): void {
            $table->foreignId('component_recipe_version_id')
                ->nullable()
                ->after('recipe_version_id')
                ->constrained('recipe_versions')
                ->restrictOnDelete();

            $table->unique(['recipe_version_id', 'component_recipe_version_id']);
        });

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement(
                <<<'SQL'
                ALTER TABLE recipe_version_components
                ALTER COLUMN inventory_item_id DROP NOT NULL
                SQL,
            );

            DB::statement(
                <<<'SQL'
                ALTER TABLE recipe_version_components
                ADD CONSTRAINT recipe_version_components_target_check
                CHECK (
                    (inventory_item_id IS NOT NULL AND component_recipe_version_id IS NULL)
                    OR (inventory_item_id IS NULL AND component_recipe_version_id IS NOT NULL)
                )
                SQL,
            );
        }
    }

    /**
     * Remove nested recipe version component support.
     */
    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement(
                <<<'SQL'
                ALTER TABLE recipe_version_components
                DROP CONSTRAINT recipe_version_components_target_check
                SQL,
            );

            DB::statement(
                <<<'SQL'
                ALTER TABLE recipe_version_components
                ALTER COLUMN inventory_item_id SET NOT NULL
                SQL,
            );
        }

        Schema::table('recipe_version_components', function (Blueprint $table): void {
            $table->dropUnique(['recipe_version_id', 'component_recipe_version_id']);
            $table->dropConstrainedForeignId('component_recipe_version_id');
        });
    }
};
