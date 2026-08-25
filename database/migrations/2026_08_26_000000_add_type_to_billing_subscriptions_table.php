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
        Schema::table('billing_subscriptions', function (Blueprint $table): void {
            // Mirrors Cashier's own `subscriptions.type` — the stable
            // application-level subscription-slot identifier. Nullable
            // rather than backfilled: no authoritative local evidence
            // exists for rows synchronized before this column existed.
            $table->string('type')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('billing_subscriptions', function (Blueprint $table): void {
            $table->dropColumn('type');
        });
    }
};
