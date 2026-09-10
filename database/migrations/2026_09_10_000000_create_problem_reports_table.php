<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('problem_reports', function (Blueprint $table): void {
            $table->id();
            $table->string('reference', 16)->unique();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('organization_id')->nullable();
            $table->string('organization_name_snapshot')->nullable();
            $table->string('title', 160)->nullable();
            $table->text('description');
            $table->string('status', 32)->default('submitted');
            $table->string('notion_id')->nullable();
            $table->timestamp('notion_synced_at')->nullable();
            $table->timestamp('email_notified_at')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
            $table->index('user_id');
            $table->index('organization_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('problem_reports');
    }
};
