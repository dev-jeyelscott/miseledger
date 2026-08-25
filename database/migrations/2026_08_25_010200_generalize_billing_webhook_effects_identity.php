<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('billing_webhook_effects', function (Blueprint $table): void {
            // Deterministic backfill: every existing row is a Stripe event.
            $table->string('provider')->default('stripe');
            $table->string('external_event_id')->nullable();
        });

        DB::table('billing_webhook_effects')->update(['external_event_id' => DB::raw('stripe_event_id')]);

        // No doctrine/dbal dependency in this project, so native ALTER
        // COLUMN statements are used instead of Blueprint::change().
        DB::statement('ALTER TABLE billing_webhook_effects ALTER COLUMN external_event_id SET NOT NULL');

        // stripe_event_id becomes Stripe-only from here: nullable so
        // non-Stripe provider rows (no Stripe event) can exist. Its existing
        // unique index still holds for every non-null (Stripe) row —
        // Postgres unique indexes permit multiple NULLs — so Stripe
        // deduplication is unaffected.
        DB::statement('ALTER TABLE billing_webhook_effects ALTER COLUMN stripe_event_id DROP NOT NULL');

        Schema::table('billing_webhook_effects', function (Blueprint $table): void {
            $table->unique(['provider', 'external_event_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('billing_webhook_effects', function (Blueprint $table): void {
            $table->dropUnique(['provider', 'external_event_id']);
            $table->dropColumn(['provider', 'external_event_id']);
        });

        DB::statement('ALTER TABLE billing_webhook_effects ALTER COLUMN stripe_event_id SET NOT NULL');
    }
};
