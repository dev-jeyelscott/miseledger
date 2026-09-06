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
        Schema::create('ai_messages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('ai_conversation_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->string('role', 16);
            $table->unsignedInteger('sequence');
            $table->text('content');
            $table->timestampTz('created_at')->useCurrent();

            $table->unique(['ai_conversation_id', 'sequence']);
            $table->index(['ai_conversation_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_messages');
    }
};
