<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create the append-only evidence trail for consequential platform-owner
     * actions that are not organization-scoped (catalog/CMS/security
     * transitions). Distinct from the tenant-scoped `audit_logs` table and
     * from `platform_admin_audit_events` (grant/revoke history).
     */
    public function up(): void
    {
        Schema::create('platform_audit_events', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('actor_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->string('action', 60);
            $table->string('subject_type', 120);
            $table->string('subject_id', 120)->nullable();

            $table->jsonb('before')->nullable();
            $table->jsonb('after')->nullable();

            $table->string('correlation_key', 120)->nullable();
            $table->string('source', 120);

            $table->timestampTz('occurred_at');
            $table->timestampTz('created_at')->useCurrent();

            $table->index(['subject_type', 'subject_id', 'occurred_at']);
            $table->index(['action', 'occurred_at']);
            $table->index(['actor_user_id', 'occurred_at']);
        });
    }

    /**
     * Remove the platform audit event history.
     */
    public function down(): void
    {
        Schema::dropIfExists('platform_audit_events');
    }
};
