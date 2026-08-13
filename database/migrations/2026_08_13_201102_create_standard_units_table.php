<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create the global standard-unit reference catalog.
     */
    public function up(): void
    {
        Schema::create('standard_units', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 32)->unique();
            $table->string('name', 100);
            $table->enum('dimension', ['weight', 'volume', 'count']);
            $table->decimal('canonical_factor', 20, 12)->nullable();
            $table->boolean('active')->default(true);
            $table->timestampsTz();
        });
    }

    /**
     * Remove the global standard-unit reference catalog.
     */
    public function down(): void
    {
        Schema::dropIfExists('standard_units');
    }
};
