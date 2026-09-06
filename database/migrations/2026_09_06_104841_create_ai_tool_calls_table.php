<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('ai_tool_calls', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('ai_run_id')->constrained()->cascadeOnDelete();
            $table->string('tool_name', 120);
            $table->string('provider_tool_call_id', 160)->nullable();
            $table->string('status', 32);
            $table->jsonb('metadata')->nullable();
            $table->timestampTz('created_at')->useCurrent();

            $table->unique(['ai_run_id', 'provider_tool_call_id']);
            $table->index(['ai_run_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_tool_calls');
    }
};
