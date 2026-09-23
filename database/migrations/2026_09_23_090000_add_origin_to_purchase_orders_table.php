<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add a reporting-only origin flag so desktop can tell mobile ad-hoc
     * receiving POs apart from normal desktop-planned purchasing, without
     * touching any existing non-null foreign key or ledger-critical column.
     */
    public function up(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table): void {
            $table->string('origin', 20)
                ->default('desktop')
                ->after('status');
        });

        DB::table('purchase_orders')->update(['origin' => 'desktop']);

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement(<<<'SQL'
                ALTER TABLE purchase_orders
                ADD CONSTRAINT purchase_orders_origin_valid
                CHECK (
                    origin IN (
                        'desktop',
                        'mobile_ad_hoc'
                    )
                )
            SQL);
        }
    }

    /**
     * Remove the origin flag.
     */
    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement(
                'ALTER TABLE purchase_orders DROP CONSTRAINT IF EXISTS purchase_orders_origin_valid',
            );
        }

        Schema::table('purchase_orders', function (Blueprint $table): void {
            $table->dropColumn('origin');
        });
    }
};
