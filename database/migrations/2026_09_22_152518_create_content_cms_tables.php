<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add the versioned marketing/legal CMS: immutable revisions with a
     * page-level pointer to the active published revision (POC-V6.1).
     */
    public function up(): void
    {
        Schema::create('content_pages', function (Blueprint $table): void {
            $table->id();
            $table->string('kind', 20);
            $table->string('key', 120)->unique();
            $table->string('slug', 160)->unique();
            $table->string('title', 200);
            // Added below once content_revisions exists (circular reference).
            $table->unsignedBigInteger('published_revision_id')->nullable();
            $table->timestampsTz();
        });

        DB::statement(
            "ALTER TABLE content_pages
             ADD CONSTRAINT cp_kind_chk
             CHECK (kind IN ('marketing', 'legal'))",
        );

        DB::statement(
            "ALTER TABLE content_pages
             ADD CONSTRAINT cp_key_format_chk
             CHECK (key ~ '^[a-z][a-z0-9_.]*$')",
        );

        DB::statement(
            "ALTER TABLE content_pages
             ADD CONSTRAINT cp_slug_format_chk
             CHECK (slug ~ '^[a-z0-9]+(-[a-z0-9]+)*$')",
        );

        Schema::create('content_revisions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('content_page_id')
                ->constrained('content_pages')
                ->restrictOnDelete();
            $table->unsignedInteger('revision');
            $table->string('title', 200);
            $table->text('body_markdown');
            $table->jsonb('metadata')->nullable();
            $table->foreignId('created_by_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->foreignId('published_by_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestampTz('published_at')->nullable();
            $table->timestampsTz();

            $table->unique(
                ['content_page_id', 'revision'],
                'cr_page_revision_unique',
            );
        });

        DB::statement(
            'ALTER TABLE content_revisions
             ADD CONSTRAINT cr_revision_positive_chk
             CHECK (revision > 0)',
        );

        Schema::table('content_pages', function (Blueprint $table): void {
            $table->foreign('published_revision_id', 'cp_published_revision_fk')
                ->references('id')
                ->on('content_revisions')
                ->restrictOnDelete();
        });
    }

    /**
     * Remove the versioned CMS persistence.
     */
    public function down(): void
    {
        Schema::table('content_pages', function (Blueprint $table): void {
            $table->dropForeign('cp_published_revision_fk');
        });

        Schema::dropIfExists('content_revisions');
        Schema::dropIfExists('content_pages');
    }
};
