<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add immutable commercial versions and rollout-safe version pins.
     */
    public function up(): void
    {
        Schema::create('billing_plan_versions', function (Blueprint $table): void {
            $table->id();
            $table->string('plan_code');
            $table->unsignedInteger('version');
            $table->string('name');
            $table->unsignedInteger('tier');
            $table->jsonb('feature_codes');
            $table->jsonb('limits');
            $table->timestampTz('published_at')->nullable();
            $table->timestampTz('superseded_at')->nullable();
            $table->foreignId('created_by_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->foreignId('published_by_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestampsTz();

            $table->unique(
                ['plan_code', 'version'],
                'bpv_plan_code_version_unique',
            );

            // Required for composite version + plan-code foreign keys.
            $table->unique(
                ['id', 'plan_code'],
                'bpv_id_plan_code_unique',
            );
        });

        DB::statement(
            "ALTER TABLE billing_plan_versions
             ADD CONSTRAINT bpv_plan_code_format_chk
             CHECK (plan_code ~ '^[a-z][a-z0-9_]*$')",
        );

        DB::statement(
            'ALTER TABLE billing_plan_versions
             ADD CONSTRAINT bpv_version_positive_chk
             CHECK (version > 0)',
        );

        DB::statement(
            'ALTER TABLE billing_plan_versions
             ADD CONSTRAINT bpv_tier_positive_chk
             CHECK (tier > 0)',
        );

        DB::statement(
            'ALTER TABLE billing_plan_versions
             ADD CONSTRAINT bpv_lifecycle_chk
             CHECK (
                 superseded_at IS NULL
                 OR (
                     published_at IS NOT NULL
                     AND superseded_at >= published_at
                 )
             )',
        );

        DB::statement(
            'CREATE UNIQUE INDEX bpv_one_current_per_plan
             ON billing_plan_versions (plan_code)
             WHERE published_at IS NOT NULL
               AND superseded_at IS NULL',
        );

        Schema::create('billing_plan_version_prices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('billing_plan_version_id')
                ->constrained('billing_plan_versions')
                ->restrictOnDelete();
            $table->string('provider');
            $table->string('collection_method');
            $table->string('interval');
            $table->char('currency', 3);
            $table->unsignedBigInteger('amount_minor');
            $table->timestampsTz();

            $table->unique(
                [
                    'billing_plan_version_id',
                    'provider',
                    'collection_method',
                    'interval',
                    'currency',
                ],
                'bpvp_version_tuple_unique',
            );
        });

        DB::statement(
            "ALTER TABLE billing_plan_version_prices
             ADD CONSTRAINT bpvp_provider_chk
             CHECK (provider IN ('stripe', 'paymongo'))",
        );

        DB::statement(
            "ALTER TABLE billing_plan_version_prices
             ADD CONSTRAINT bpvp_collection_chk
             CHECK (collection_method IN ('automatic', 'manual'))",
        );

        DB::statement(
            "ALTER TABLE billing_plan_version_prices
             ADD CONSTRAINT bpvp_interval_chk
             CHECK (interval IN ('monthly', 'yearly'))",
        );

        DB::statement(
            "ALTER TABLE billing_plan_version_prices
             ADD CONSTRAINT bpvp_currency_chk
             CHECK (currency ~ '^[A-Z]{3}$')",
        );

        DB::statement(
            'ALTER TABLE billing_plan_version_prices
             ADD CONSTRAINT bpvp_amount_positive_chk
             CHECK (amount_minor > 0)',
        );

        Schema::table('billing_subscriptions', function (Blueprint $table): void {
            $table->unsignedBigInteger('plan_version_id')
                ->nullable()
                ->index();
        });

        DB::statement(
            'ALTER TABLE billing_subscriptions
             ADD CONSTRAINT bs_plan_version_plan_fk
             FOREIGN KEY (plan_version_id, plan_code)
             REFERENCES billing_plan_versions (id, plan_code)
             ON DELETE RESTRICT',
        );

        Schema::table('billing_invoices', function (Blueprint $table): void {
            $table->unsignedBigInteger('plan_version_id')
                ->nullable()
                ->index();

            $table->unsignedBigInteger('target_plan_version_id')
                ->nullable()
                ->index();
        });

        DB::statement(
            'ALTER TABLE billing_invoices
             ADD CONSTRAINT bi_source_version_plan_fk
             FOREIGN KEY (plan_version_id, plan_code)
             REFERENCES billing_plan_versions (id, plan_code)
             ON DELETE RESTRICT',
        );

        DB::statement(
            'ALTER TABLE billing_invoices
             ADD CONSTRAINT bi_target_version_plan_fk
             FOREIGN KEY (target_plan_version_id, target_plan_code)
             REFERENCES billing_plan_versions (id, plan_code)
             ON DELETE RESTRICT',
        );
    }

    /**
     * Remove Phase 5 additive persistence.
     */
    public function down(): void
    {
        DB::statement(
            'ALTER TABLE billing_invoices
             DROP CONSTRAINT IF EXISTS bi_target_version_plan_fk',
        );

        DB::statement(
            'ALTER TABLE billing_invoices
             DROP CONSTRAINT IF EXISTS bi_source_version_plan_fk',
        );

        Schema::table('billing_invoices', function (Blueprint $table): void {
            $table->dropColumn([
                'target_plan_version_id',
                'plan_version_id',
            ]);
        });

        DB::statement(
            'ALTER TABLE billing_subscriptions
             DROP CONSTRAINT IF EXISTS bs_plan_version_plan_fk',
        );

        Schema::table('billing_subscriptions', function (Blueprint $table): void {
            $table->dropColumn('plan_version_id');
        });

        Schema::dropIfExists('billing_plan_version_prices');
        Schema::dropIfExists('billing_plan_versions');
    }
};
