<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Index draft-receipt dependency lookups by inventory item.
     */
    public function up(): void
    {
        Schema::table('goods_receipt_lines', function (Blueprint $table): void {
            $table->index('inventory_item_id');
        });
    }

    /**
     * Remove the inventory-item dependency index.
     */
    public function down(): void
    {
        Schema::table('goods_receipt_lines', function (Blueprint $table): void {
            $table->dropIndex(['inventory_item_id']);
        });
    }
};
