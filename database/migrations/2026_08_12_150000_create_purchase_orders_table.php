<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create organization-scoped purchase order headers.
     */
    public function up(): void
    {
        Schema::create('purchase_orders', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('organization_id')
                ->constrained()
                ->restrictOnDelete();

            $table->foreignId('location_id')
                ->constrained()
                ->restrictOnDelete();

            $table->foreignId('supplier_id')
                ->constrained()
                ->restrictOnDelete();

            $table->string('number', 80);
            $table->string('status', 32)->default('draft');

            $table->date('order_date');
            $table->date('expected_delivery_date')->nullable();

            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('tax_total', 15, 2)->default(0);
            $table->decimal('discount_total', 15, 2)->default(0);
            $table->decimal('total', 15, 2)->default(0);

            $table->text('notes')->nullable();

            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignId('approved_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestampTz('approved_at')->nullable();
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
                'organization_id',
                'supplier_id',
                'order_date',
            ]);
        });

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement(<<<'SQL'
                ALTER TABLE purchase_orders
                ADD CONSTRAINT purchase_orders_status_valid
                CHECK (
                    status IN (
                        'draft',
                        'approved',
                        'partially_received',
                        'received',
                        'cancelled'
                    )
                )
            SQL);

            DB::statement(<<<'SQL'
                ALTER TABLE purchase_orders
                ADD CONSTRAINT purchase_orders_totals_non_negative
                CHECK (
                    subtotal >= 0
                    AND tax_total >= 0
                    AND discount_total >= 0
                    AND total >= 0
                )
            SQL);
        }
    }

    /**
     * Remove purchase order headers.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_orders');
    }
};
