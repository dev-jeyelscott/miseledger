<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('supplier_items', function (Blueprint $table): void {
            $table->index(
                [
                    'organization_id',
                    'supplier_id',
                    'active',
                ],
                'supplier_items_org_supplier_active_idx',
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('supplier_items', function (Blueprint $table): void {
            $table->dropIndex('supplier_items_org_supplier_active_idx');
        });
    }
};
