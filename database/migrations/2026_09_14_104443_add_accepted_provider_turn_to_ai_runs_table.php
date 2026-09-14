<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_runs', function (Blueprint $table): void {
            $table->string('accepted_provider_turn_id', 160)->nullable()->after('provider_run_id');
            $table->timestampTz('accepted_provider_turn_at')->nullable()->after('accepted_provider_turn_id');
        });
    }

    public function down(): void
    {
        Schema::table('ai_runs', function (Blueprint $table): void {
            $table->dropColumn(['accepted_provider_turn_id', 'accepted_provider_turn_at']);
        });
    }
};
