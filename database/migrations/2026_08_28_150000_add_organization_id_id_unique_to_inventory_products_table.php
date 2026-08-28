<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Expose a composite key so child option dimensions can enforce
     * organization containment against their owning product family.
     */
    public function up(): void
    {
        Schema::table('inventory_products', function (Blueprint $table): void {
            $table->unique(
                ['organization_id', 'id'],
                'inventory_products_organization_id_id_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::table('inventory_products', function (Blueprint $table): void {
            $table->dropUnique(
                'inventory_products_organization_id_id_unique',
            );
        });
    }
};
