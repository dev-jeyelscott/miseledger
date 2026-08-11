<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create the tenant root used by all organization-scoped data.
     */
    public function up(): void
    {
        Schema::create('organizations', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 160);
            $table->string('slug', 160)->unique();
            $table->string('timezone', 64)->default('Asia/Manila');
            $table->char('currency', 3)->default('PHP');
            $table->boolean('active')->default(true);
            $table->timestampsTz();

            $table->index('active');
        });
    }

    /**
     * Remove the organization tenant root.
     */
    public function down(): void
    {
        Schema::dropIfExists('organizations');
    }
};
