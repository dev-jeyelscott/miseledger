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
        Schema::create('billing_invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->foreignId('billing_subscription_id')->constrained()->restrictOnDelete();
            $table->string('provider');
            $table->string('invoice_number')->unique();
            $table->string('plan_code');
            $table->string('billing_interval');
            $table->string('currency', 3);
            $table->unsignedBigInteger('amount');
            $table->string('status')->default('pending');
            $table->timestampTz('period_starts_at');
            $table->timestampTz('period_ends_at');
            $table->timestampTz('due_at')->nullable();
            $table->timestampTz('paid_at')->nullable();
            $table->timestampTz('cancelled_at')->nullable();
            $table->timestamps();

            $table->unique(['billing_subscription_id', 'period_starts_at', 'period_ends_at'], 'billing_invoices_subscription_period_unique');
            $table->index(['organization_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('billing_invoices');
    }
};
