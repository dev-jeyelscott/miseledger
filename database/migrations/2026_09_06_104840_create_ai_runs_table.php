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
        Schema::create('ai_runs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('ai_conversation_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ai_provider_connection_id')
                ->nullable();
            $table->foreignId('ai_provider_connection_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->string('provider', 32);
            $table->string('provider_run_id', 160)->nullable();
            $table->string('model', 120)->nullable();
            $table->string('status', 32);
            $table->string('error_code', 80)->nullable();
            $table->unsignedInteger('input_tokens')->nullable();
            $table->unsignedInteger('output_tokens')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestampTz('started_at')->nullable();
            $table->timestampTz('finished_at')->nullable();
            $table->timestampsTz();

            $table->unique(['provider', 'provider_run_id']);
            $table->index(['ai_conversation_id', 'created_at']);
            $table->index(['status', 'created_at']);

            $table->foreign(['ai_conversation_id', 'user_id'])
                ->references(['id', 'user_id'])
                ->on('ai_conversations')
                ->cascadeOnDelete();

            $table->foreign([
                'ai_provider_connection_id',
                'ai_provider_connection_user_id',
            ])
                ->references(['id', 'user_id'])
                ->on('ai_provider_connections')
                ->nullOnDelete();
        });

        DB::statement(<<<'SQL'
            ALTER TABLE ai_runs
            ADD CONSTRAINT ai_runs_provider_connection_owner_matches
            CHECK (
                (
                    ai_provider_connection_id IS NULL
                    AND ai_provider_connection_user_id IS NULL
                )
                OR (
                    ai_provider_connection_id IS NOT NULL
                    AND ai_provider_connection_user_id = user_id
                )
            )
        SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement(
            'ALTER TABLE ai_runs '
            .'DROP CONSTRAINT IF EXISTS ai_runs_provider_connection_owner_matches',
        );

        Schema::dropIfExists('ai_runs');
    }
};
