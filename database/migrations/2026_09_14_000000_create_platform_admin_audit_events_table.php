<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create the append-only history of platform administrator grant and revoke transitions.
     */
    public function up(): void
    {
        Schema::create('platform_admin_audit_events', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->string('target_email', 255);

            $table->string('action', 20);

            $table->foreignId('actor_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->string('source', 120);
            $table->text('reason');

            $table->timestampTz('created_at')->useCurrent();

            $table->index([
                'user_id',
                'created_at',
            ]);

            $table->index([
                'action',
                'created_at',
            ]);
        });
    }

    /**
     * Remove the platform administrator audit event history.
     */
    public function down(): void
    {
        Schema::dropIfExists('platform_admin_audit_events');
    }
};
