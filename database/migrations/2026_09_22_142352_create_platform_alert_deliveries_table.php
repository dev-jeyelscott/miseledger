<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Minimal, idempotent per-recipient email delivery evidence for
     * platform-owner alerts (POC-V9.3). The unique dedupe key guarantees a
     * given recipient receives at most one email per alert per severity
     * level reached, even under concurrent or repeated evaluator runs, and
     * is the sole idempotency mechanism: this is intentionally not a
     * general notification center.
     */
    public function up(): void
    {
        Schema::create('platform_alert_deliveries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('platform_alert_id')->constrained()->cascadeOnDelete();
            $table->foreignId('recipient_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('channel')->default('mail');
            $table->string('event_kind');
            $table->string('dedupe_key')->unique();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamps();

            $table->index(['platform_alert_id', 'recipient_user_id'], 'platform_alert_deliveries_alert_recipient_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_alert_deliveries');
    }
};
