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
        Schema::table('inventory_items', function (Blueprint $table): void {
            $table->foreignId('inventory_category_id')
                ->nullable()
                ->constrained('inventory_categories')
                ->nullOnDelete();

            $table->enum('type', [
                'ingredient',
                'finished_item',
                'prepared_item',
                'packaging',
                'consumable',
            ])->default('ingredient');

            $table->decimal('yield_percentage', 5, 2)->default(100);

            $table->index(['organization_id', 'type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('inventory_items', function (Blueprint $table): void {
            $table->dropIndex(['organization_id', 'type']);
            $table->dropConstrainedForeignId('inventory_category_id');
            $table->dropColumn(['type', 'yield_percentage']);
        });
    }
};
