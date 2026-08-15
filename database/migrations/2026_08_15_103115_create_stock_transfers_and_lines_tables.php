<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create tenant-contained stock transfer headers and evidence lines.
     */
    public function up(): void
    {
        Schema::create('stock_transfers', function (Blueprint $table): void {
            $table->id();

            $table
                ->foreignId('organization_id')
                ->constrained()
                ->restrictOnDelete();

            $table
                ->foreignId('from_location_id')
                ->constrained('locations')
                ->restrictOnDelete();

            $table
                ->foreignId('from_storage_location_id')
                ->constrained('storage_locations')
                ->restrictOnDelete();

            $table
                ->foreignId('to_location_id')
                ->constrained('locations')
                ->restrictOnDelete();

            $table
                ->foreignId('to_storage_location_id')
                ->constrained('storage_locations')
                ->restrictOnDelete();

            $table->string('number', 80);
            $table->string('status', 20)->default('draft');

            $table->timestampTz('requested_at')->nullable();
            $table->timestampTz('shipped_at')->nullable();
            $table->timestampTz('received_at')->nullable();

            $table
                ->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table
                ->foreignId('shipped_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table
                ->foreignId('received_by')
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
                'organization_id',
                'status',
            ]);

            $table->index([
                'from_location_id',
                'status',
            ]);

            $table->index([
                'to_location_id',
                'status',
            ]);

            $table->foreign(
                [
                    'organization_id',
                    'from_location_id',
                ],
                'stock_transfers_org_from_location_fk',
            )
                ->references([
                    'organization_id',
                    'id',
                ])
                ->on('locations')
                ->restrictOnDelete();

            $table->foreign(
                [
                    'organization_id',
                    'from_location_id',
                    'from_storage_location_id',
                ],
                'stock_transfers_org_from_storage_fk',
            )
                ->references([
                    'organization_id',
                    'location_id',
                    'id',
                ])
                ->on('storage_locations')
                ->restrictOnDelete();

            $table->foreign(
                [
                    'organization_id',
                    'to_location_id',
                ],
                'stock_transfers_org_to_location_fk',
            )
                ->references([
                    'organization_id',
                    'id',
                ])
                ->on('locations')
                ->restrictOnDelete();

            $table->foreign(
                [
                    'organization_id',
                    'to_location_id',
                    'to_storage_location_id',
                ],
                'stock_transfers_org_to_storage_fk',
            )
                ->references([
                    'organization_id',
                    'location_id',
                    'id',
                ])
                ->on('storage_locations')
                ->restrictOnDelete();
        });

        Schema::create('stock_transfer_lines', function (Blueprint $table): void {
            $table->id();

            $table
                ->foreignId('stock_transfer_id')
                ->constrained()
                ->restrictOnDelete();

            $table
                ->foreignId('inventory_item_id')
                ->constrained()
                ->restrictOnDelete();

            $table->decimal('requested_quantity', 15, 6);

            $table
                ->foreignId('unit_id')
                ->constrained('units_of_measure')
                ->restrictOnDelete();

            $table->decimal('requested_base_quantity', 15, 6);

            $table
                ->decimal('shipped_base_quantity', 15, 6)
                ->nullable();

            $table
                ->decimal('received_base_quantity', 15, 6)
                ->nullable();

            $table
                ->decimal('unit_cost', 15, 4)
                ->nullable();

            $table
                ->decimal('variance_base_quantity', 15, 6)
                ->nullable();

            $table->timestampsTz();

            $table->unique(
                [
                    'stock_transfer_id',
                    'inventory_item_id',
                ],
                'stock_transfer_lines_transfer_item_unique',
            );
        });

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement(
                <<<'SQL'
                ALTER TABLE stock_transfers
                ADD CONSTRAINT stock_transfers_status_check
                CHECK (
                    status IN (
                        'draft',
                        'shipped',
                        'received',
                        'cancelled'
                    )
                )
                SQL,
            );

            DB::statement(
                <<<'SQL'
                ALTER TABLE stock_transfers
                ADD CONSTRAINT stock_transfers_storage_diff_check
                CHECK (
                    from_storage_location_id
                    <> to_storage_location_id
                )
                SQL,
            );

            DB::statement(
                <<<'SQL'
                ALTER TABLE stock_transfer_lines
                ADD CONSTRAINT stock_transfer_lines_requested_quantity_check
                CHECK (requested_quantity > 0)
                SQL,
            );

            DB::statement(
                <<<'SQL'
                ALTER TABLE stock_transfer_lines
                ADD CONSTRAINT stock_transfer_lines_requested_base_check
                CHECK (requested_base_quantity > 0)
                SQL,
            );

            DB::statement(
                <<<'SQL'
                ALTER TABLE stock_transfer_lines
                ADD CONSTRAINT stock_transfer_lines_shipped_base_check
                CHECK (
                    shipped_base_quantity IS NULL
                    OR shipped_base_quantity > 0
                )
                SQL,
            );

            DB::statement(
                <<<'SQL'
                ALTER TABLE stock_transfer_lines
                ADD CONSTRAINT stock_transfer_lines_received_base_check
                CHECK (
                    received_base_quantity IS NULL
                    OR received_base_quantity >= 0
                )
                SQL,
            );

            DB::statement(
                <<<'SQL'
                ALTER TABLE stock_transfer_lines
                ADD CONSTRAINT stock_transfer_lines_unit_cost_check
                CHECK (
                    unit_cost IS NULL
                    OR unit_cost >= 0
                )
                SQL,
            );
        }
    }

    /**
     * Remove stock transfer persistence in dependency order.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_transfer_lines');
        Schema::dropIfExists('stock_transfers');
    }
};
