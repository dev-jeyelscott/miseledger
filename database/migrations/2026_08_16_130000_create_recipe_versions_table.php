<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create sequential, editable-while-draft recipe versions.
     */
    public function up(): void
    {
        Schema::create('recipe_versions', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('recipe_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->unsignedInteger('version_number');
            $table->string('status', 20)->default('draft');

            $table->decimal('yield_quantity', 15, 6);

            $table->foreignId('yield_unit_id')
                ->constrained('units_of_measure')
                ->restrictOnDelete();

            $table->text('notes')->nullable();

            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignId('published_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestampTz('published_at')->nullable();

            $table->timestampsTz();

            $table->unique(['recipe_id', 'version_number']);
            $table->index(['recipe_id', 'status']);
        });

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement(
                <<<'SQL'
                ALTER TABLE recipe_versions
                ADD CONSTRAINT recipe_versions_status_check
                CHECK (status IN ('draft', 'published'))
                SQL,
            );

            DB::statement(
                <<<'SQL'
                ALTER TABLE recipe_versions
                ADD CONSTRAINT recipe_versions_version_number_check
                CHECK (version_number > 0)
                SQL,
            );

            DB::statement(
                <<<'SQL'
                ALTER TABLE recipe_versions
                ADD CONSTRAINT recipe_versions_yield_quantity_check
                CHECK (yield_quantity > 0)
                SQL,
            );
        }
    }

    /**
     * Remove recipe version storage.
     */
    public function down(): void
    {
        Schema::dropIfExists('recipe_versions');
    }
};
