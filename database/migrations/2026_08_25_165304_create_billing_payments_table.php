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
        Schema::create('billing_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->foreignId('billing_invoice_id')->constrained()->restrictOnDelete();
            $table->string('provider');
            $table->string('payment_method');
            $table->string('provider_request_key')->unique();
            $table->string('external_payment_intent_id')->nullable();
            $table->string('external_payment_id')->nullable();
            $table->string('currency', 3);
            $table->unsignedBigInteger('amount');
            $table->string('status')->default('pending');
            $table->boolean('livemode')->default(false);
            $table->timestampTz('expires_at')->nullable();
            $table->string('qr_code_url')->nullable();
            $table->timestampTz('paid_at')->nullable();
            $table->timestampTz('receipt_notification_claimed_at')->nullable();
            $table->timestampTz('receipt_notification_dispatched_at')->nullable();
            $table->timestampTz('failed_at')->nullable();
            $table->string('provider_error_code')->nullable();
            $table->timestamps();

            $table->unique(['provider', 'external_payment_intent_id']);
            $table->unique(['provider', 'external_payment_id']);
            $table->index(['billing_invoice_id', 'status']);
            $table->index(['organization_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('billing_payments');
    }
};
