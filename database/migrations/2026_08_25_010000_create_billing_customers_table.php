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
        Schema::create('billing_customers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->string('provider');
            $table->string('external_customer_id');
            $table->boolean('livemode')->default(false);
            $table->timestamps();

            $table->unique(['organization_id', 'provider']);
            $table->unique(['provider', 'external_customer_id']);

            // Referenced by billing_subscriptions' composite foreign key so a
            // subscription's (organization_id, provider) is database-enforced
            // to match the billing customer it belongs to.
            $table->unique(['id', 'organization_id', 'provider']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('billing_customers');
    }
};
