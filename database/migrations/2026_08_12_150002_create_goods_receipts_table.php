<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create goods receipt headers.
     */
    public function up(): void
    {
        Schema::create('goods_receipts', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('organization_id')
                ->constrained()
                ->restrictOnDelete();

            $table->foreignId('location_id')
                ->constrained()
                ->restrictOnDelete();

            $table->foreignId('purchase_order_id')
                ->constrained()
                ->restrictOnDelete();

            $table->foreignId('supplier_id')
                ->constrained()
                ->restrictOnDelete();

            $table->string('number', 80);
            $table->string('status', 20)->default('draft');

            $table->timestampTz('received_at')->nullable();
            $table->string('supplier_reference', 120)->nullable();

            $table->foreignId('received_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->text('notes')->nullable();
            $table->timestampsTz();

            $table->unique([
                'organization_id',
                'number',
            ]);

            $table->index([
                'purchase_order_id',
                'status',
            ]);

            $table->index([
                'organization_id',
                'location_id',
                'received_at',
            ]);
        });

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement(<<<'SQL'
                ALTER TABLE goods_receipts
                ADD CONSTRAINT goods_receipts_status_valid
                CHECK (
                    status IN (
                        'draft',
                        'finalized',
                        'cancelled'
                    )
                )
            SQL);
        }
    }

    /**
     * Remove goods receipt headers.
     */
    public function down(): void
    {
        Schema::dropIfExists('goods_receipts');
    }
};
