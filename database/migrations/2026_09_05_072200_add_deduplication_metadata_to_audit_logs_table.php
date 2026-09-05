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
        Schema::table('audit_logs', function (Blueprint $table): void {
            $table->boolean('is_deduplication_key')
                ->default(false)
                ->after('correlation_id');
        });

        DB::statement(
            'CREATE UNIQUE INDEX audit_logs_deduplication_unique '
            .'ON audit_logs (organization_id, correlation_id) '
            .'WHERE is_deduplication_key = true AND correlation_id IS NOT NULL',
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement(
            'DROP INDEX IF EXISTS audit_logs_deduplication_unique',
        );

        Schema::table('audit_logs', function (Blueprint $table): void {
            $table->dropColumn('is_deduplication_key');
        });
    }
};
