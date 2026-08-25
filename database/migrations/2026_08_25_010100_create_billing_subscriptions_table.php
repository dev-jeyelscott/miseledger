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
        Schema::create('billing_subscriptions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->foreignId('billing_customer_id')->constrained()->restrictOnDelete();
            $table->string('provider');
            $table->string('external_subscription_id');
            $table->string('external_plan_id')->nullable();
            $table->string('plan_code')->nullable();
            $table->string('interval')->nullable();
            $table->string('provider_status')->nullable();
            $table->boolean('livemode')->default(false);
            $table->timestampTz('trial_ends_at')->nullable();
            $table->timestampTz('current_period_ends_at')->nullable();
            $table->timestampTz('next_billing_at')->nullable();
            $table->timestampTz('ends_at')->nullable();
            $table->timestampTz('cancelled_at')->nullable();
            $table->timestamps();

            $table->unique(['provider', 'external_subscription_id']);
            $table->index('organization_id');

            // Database-enforced: a subscription's (organization_id, provider)
            // must match its billing customer's (organization_id, provider),
            // so cross-organization/cross-provider association is impossible
            // rather than merely an application-only assumption.
            $table->foreign(['billing_customer_id', 'organization_id', 'provider'])
                ->references(['id', 'organization_id', 'provider'])
                ->on('billing_customers')
                ->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('billing_subscriptions');
    }
};
