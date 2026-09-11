<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Track successful Notion status reads and any safe transient/permanent failures.
     */
    public function up(): void
    {
        Schema::table('problem_reports', function (Blueprint $table): void {
            $table->timestamp('notion_last_checked_at')->nullable();
        });
    }

    /**
     * Remove the reconciliation tracking field when rolling back this migration.
     */
    public function down(): void
    {
        Schema::table('problem_reports', function (Blueprint $table): void {
            $table->dropColumn('notion_last_checked_at');
        });
    }
};
