<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('problem_report_attachments', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('problem_report_id');
            $table->string('disk');
            $table->string('path');
            $table->string('original_name');
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('size');
            $table->timestamps();

            $table->foreign('problem_report_id')->references('id')->on('problem_reports')->cascadeOnDelete();
            $table->index('problem_report_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('problem_report_attachments');
    }
};
