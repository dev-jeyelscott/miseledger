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
        Schema::create('ai_provider_connections', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 32);
            $table->string('external_account_id', 160)->nullable();
            $table->string('account_label', 160)->nullable();
            $table->jsonb('metadata')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestampTz('activated_at')->useCurrent();
            $table->timestampTz('deactivated_at')->nullable();
            $table->timestampsTz();

            $table->index(['user_id', 'provider']);
            $table->index(['user_id', 'is_active']);
            $table->unique(['id', 'user_id']);
        });

        DB::statement(
            'CREATE UNIQUE INDEX ai_provider_connections_one_active_per_user '
            .'ON ai_provider_connections (user_id) WHERE is_active = true',
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement(
            'DROP INDEX IF EXISTS ai_provider_connections_one_active_per_user',
        );

        Schema::dropIfExists('ai_provider_connections');
    }
};
