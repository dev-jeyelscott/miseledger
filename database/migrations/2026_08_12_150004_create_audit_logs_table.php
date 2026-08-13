<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create the shared append-only audit log.
     */
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('organization_id')
                ->constrained()
                ->restrictOnDelete();

            $table->foreignId('actor_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->string('action', 80);
            $table->string('entity_type', 80);
            $table->unsignedBigInteger('entity_id');

            $table->jsonb('before_data')->nullable();
            $table->jsonb('after_data')->nullable();

            $table->string('correlation_id', 120)->nullable();
            $table->timestampTz('created_at')->useCurrent();

            $table->index([
                'organization_id',
                'created_at',
            ]);

            $table->index([
                'entity_type',
                'entity_id',
            ]);

            $table->index([
                'actor_id',
                'created_at',
            ]);

            $table->index('correlation_id');
        });
    }

    /**
     * Remove the audit log.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
