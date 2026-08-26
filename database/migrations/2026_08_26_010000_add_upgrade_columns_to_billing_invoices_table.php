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
        Schema::table('billing_invoices', function (Blueprint $table) {
            $table->string('invoice_type')->default('renewal')->after('plan_code');
            $table->string('target_plan_code')->nullable()->after('invoice_type');

            $table->index(['billing_subscription_id', 'invoice_type', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('billing_invoices', function (Blueprint $table) {
            $table->dropIndex(['billing_subscription_id', 'invoice_type', 'status']);
            $table->dropColumn(['invoice_type', 'target_plan_code']);
        });
    }
};
