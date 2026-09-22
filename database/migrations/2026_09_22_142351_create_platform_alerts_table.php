<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Persistent, deduplicated platform-owner alerts (POC-V9.1). A partial
     * unique index enforces the dedupe contract at the database level: at
     * most one OPEN row may exist per fingerprint at any time, even under
     * concurrent evaluator runs, while resolved history for the same
     * fingerprint is preserved indefinitely across recurrences.
     */
    public function up(): void
    {
        Schema::create('platform_alerts', function (Blueprint $table): void {
            $table->id();
            $table->string('fingerprint');
            $table->string('type');
            $table->string('source');
            $table->string('severity');
            $table->string('state')->default('open');
            $table->string('title');
            $table->text('summary');
            $table->jsonb('context');
            $table->timestamp('first_seen_at');
            $table->timestamp('last_seen_at');
            $table->timestamp('resolved_at')->nullable();
            $table->unsignedInteger('occurrence_count')->default(1);
            $table->timestamps();

            $table->index(['state', 'severity', 'last_seen_at'], 'platform_alerts_console_listing_index');
            $table->index(['type', 'state'], 'platform_alerts_type_state_index');
            $table->index('fingerprint', 'platform_alerts_fingerprint_index');
        });

        // Postgres partial unique index: uniqueness applies only to rows
        // still in the "open" state, which is exactly the concurrency-safe
        // dedupe guarantee POC-V9.1 requires.
        DB::statement(
            'CREATE UNIQUE INDEX platform_alerts_open_fingerprint_unique '
            .'ON platform_alerts (fingerprint) WHERE (state = \'open\')',
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_alerts');
    }
};
