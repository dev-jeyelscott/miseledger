<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add the minimum durable workflow metadata that first-time setup cannot
     * derive from existing business records: organization-creation retry
     * protection, the first-readiness marker, explicit optional-step skips,
     * and the explicit "no opening stock" resolution per inventory item.
     *
     * Organizations that already exist are operating tenants, so they are
     * marked as having completed setup to avoid blocking live workflows.
     */
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table): void {
            $table->uuid('creation_operation_id')
                ->nullable()
                ->unique();
            $table->timestamp('onboarding_completed_at')->nullable();
            $table->timestamp('onboarding_suppliers_skipped_at')->nullable();
            $table->timestamp('onboarding_team_skipped_at')->nullable();
        });

        Schema::table('inventory_items', function (Blueprint $table): void {
            $table->timestamp('opening_stock_waived_at')->nullable();
            $table->foreignId('opening_stock_waived_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
        });

        DB::table('organizations')
            ->whereNull('onboarding_completed_at')
            ->update(['onboarding_completed_at' => now()]);
    }

    /**
     * Remove the setup workflow metadata.
     */
    public function down(): void
    {
        Schema::table('inventory_items', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('opening_stock_waived_by');
            $table->dropColumn('opening_stock_waived_at');
        });

        Schema::table('organizations', function (Blueprint $table): void {
            $table->dropUnique(['creation_operation_id']);
            $table->dropColumn([
                'creation_operation_id',
                'onboarding_completed_at',
                'onboarding_suppliers_skipped_at',
                'onboarding_team_skipped_at',
            ]);
        });
    }
};
