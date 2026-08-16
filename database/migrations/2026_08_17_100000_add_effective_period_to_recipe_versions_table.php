<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Track the effective period a published recipe version is active for.
     */
    public function up(): void
    {
        Schema::table('recipe_versions', function (Blueprint $table): void {
            $table->date('effective_start_date')->nullable()->after('published_at');
            $table->date('effective_end_date')->nullable()->after('effective_start_date');
        });

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement(
                <<<'SQL'
                ALTER TABLE recipe_versions
                ADD CONSTRAINT recipe_versions_effective_period_check
                CHECK (
                    effective_end_date IS NULL
                    OR effective_start_date IS NULL
                    OR effective_end_date >= effective_start_date
                )
                SQL,
            );
        }
    }

    /**
     * Remove effective period tracking.
     */
    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement(
                <<<'SQL'
                ALTER TABLE recipe_versions
                DROP CONSTRAINT recipe_versions_effective_period_check
                SQL,
            );
        }

        Schema::table('recipe_versions', function (Blueprint $table): void {
            $table->dropColumn(['effective_start_date', 'effective_end_date']);
        });
    }
};
