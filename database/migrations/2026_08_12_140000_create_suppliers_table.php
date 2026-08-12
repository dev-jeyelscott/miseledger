<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create organization-scoped supplier master records.
     */
    public function up(): void
    {
        Schema::create('suppliers', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('organization_id')
                ->constrained()
                ->restrictOnDelete();

            $table->string('name', 180);
            $table->string('code', 60);
            $table->string('contact_name', 120)->nullable();
            $table->string('email', 180)->nullable();
            $table->string('phone', 60)->nullable();
            $table->string('payment_terms', 120)->nullable();
            $table->integer('lead_time_days')->nullable();
            $table->boolean('active')->default(true);
            $table->timestampsTz();

            $table->unique([
                'organization_id',
                'code',
            ]);

            $table->index([
                'organization_id',
                'active',
            ]);

            $table->index([
                'organization_id',
                'name',
            ]);
        });

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement(<<<'SQL'
                ALTER TABLE suppliers
                ADD CONSTRAINT suppliers_lead_time_non_negative
                CHECK (
                    lead_time_days IS NULL
                    OR lead_time_days >= 0
                )
            SQL);
        }
    }

    /**
     * Remove supplier master records after dependent records are removed.
     */
    public function down(): void
    {
        Schema::dropIfExists('suppliers');
    }
};
