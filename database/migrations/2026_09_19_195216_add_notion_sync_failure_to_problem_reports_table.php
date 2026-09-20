<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Track terminal outbound Notion sync failures so recovery can exclude them.
     */
    public function up(): void
    {
        Schema::table('problem_reports', function (Blueprint $table): void {
            $table->timestamp('notion_sync_failed_at')->nullable();
            $table->string('notion_sync_failure_reason')->nullable();
        });
    }

    /**
     * Remove the outbound sync failure tracking fields when rolling back this migration.
     */
    public function down(): void
    {
        Schema::table('problem_reports', function (Blueprint $table): void {
            $table->dropColumn(['notion_sync_failed_at', 'notion_sync_failure_reason']);
        });
    }
};
