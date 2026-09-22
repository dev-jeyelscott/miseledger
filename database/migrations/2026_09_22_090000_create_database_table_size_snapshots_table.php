<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Minimal daily storage-metadata snapshots that let Platform Health
     * measure real table growth over time. Only catalog-reported byte sizes
     * are stored here, never table contents or row data.
     */
    public function up(): void
    {
        Schema::create('database_table_size_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->date('captured_on');
            $table->string('schema_name');
            $table->string('table_name');
            $table->unsignedBigInteger('total_bytes');
            $table->unsignedBigInteger('table_bytes');
            $table->unsignedBigInteger('index_bytes');
            $table->timestamp('created_at')->nullable();

            $table->unique(['captured_on', 'schema_name', 'table_name'], 'database_table_size_snapshots_captured_table_unique');
            $table->index(['schema_name', 'table_name', 'captured_on'], 'database_table_size_snapshots_lookup_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('database_table_size_snapshots');
    }
};
