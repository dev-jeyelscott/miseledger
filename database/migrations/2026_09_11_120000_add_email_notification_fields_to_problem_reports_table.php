<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('problem_reports', function (Blueprint $table): void {
            $table->timestamp('email_notification_claimed_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('problem_reports', function (Blueprint $table): void {
            $table->dropColumn('email_notification_claimed_at');
        });
    }
};
