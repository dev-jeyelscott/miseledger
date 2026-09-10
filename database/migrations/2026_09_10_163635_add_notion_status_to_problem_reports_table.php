<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Store the raw remote Notion status separately from the local report lifecycle.
     */
    public function up(): void
    {
        Schema::table('problem_reports', function (Blueprint $table): void {
            $table->string('notion_status')->nullable();
        });
    }

    /**
     * Remove the remote Notion status snapshot when rolling back this migration.
     */
    public function down(): void
    {
        Schema::table('problem_reports', function (Blueprint $table): void {
            $table->dropColumn('notion_status');
        });
    }
};
