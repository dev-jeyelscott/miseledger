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
            $table->foreignId('inventory_brand_id')
                ->nullable()
                ->constrained('inventory_brands')
                ->nullOnDelete();

            $table->string('model_number', 120)->nullable();
            $table->string('manufacturer_part_number', 120)->nullable();
            $table->text('description')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('inventory_items', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('inventory_brand_id');
            $table->dropColumn([
                'model_number',
                'manufacturer_part_number',
                'description',
            ]);
        });
    }
};
