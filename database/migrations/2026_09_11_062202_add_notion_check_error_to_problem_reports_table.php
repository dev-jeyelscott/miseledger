<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Track safe, non-secret integration diagnostics from failed Notion reads.
     */
    public function up(): void
    {
        Schema::table('problem_reports', function (Blueprint $table) {
            $table->string('notion_check_error')->nullable()->default(null);
        });
    }

    /**
     * Remove the safe error diagnostic field when rolling back this migration.
     */
    public function down(): void
    {
        Schema::table('problem_reports', function (Blueprint $table) {
            $table->dropColumn('notion_check_error');
        });
    }
};
