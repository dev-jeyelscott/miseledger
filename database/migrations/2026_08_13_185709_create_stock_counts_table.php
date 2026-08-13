<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create organization-scoped physical stock-count headers.
     */
    public function up(): void
    {
        Schema::create('stock_counts', function (Blueprint $table): void {
            $table->id();

            $table
                ->foreignId('organization_id')
                ->constrained()
                ->restrictOnDelete();

            $table
                ->foreignId('location_id')
                ->constrained()
                ->restrictOnDelete();

            $table
                ->foreignId('storage_location_id')
                ->constrained()
                ->restrictOnDelete();

            $table->string('number', 80);
            $table->string('status', 20)->default('draft');

            $table->timestampTz('counted_at')->nullable();

            $table
                ->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table
                ->foreignId('submitted_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table
                ->foreignId('finalized_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestampTz('finalized_at')->nullable();

            $table->timestampsTz();

            $table->unique([
                'organization_id',
                'number',
            ]);

            $table->index([
                'organization_id',
                'location_id',
                'status',
            ]);

            $table->index([
                'storage_location_id',
                'status',
            ]);
        });

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement(
                <<<'SQL'
                ALTER TABLE stock_counts
                ADD CONSTRAINT stock_counts_status_check
                CHECK (
                    status IN (
                        'draft',
                        'submitted',
                        'finalized',
                        'cancelled'
                    )
                )
                SQL,
            );
        }
    }

    /**
     * Remove the stock-count aggregate.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_counts');
    }
};
