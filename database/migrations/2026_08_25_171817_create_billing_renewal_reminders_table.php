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
        Schema::create('billing_renewal_reminders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('billing_invoice_id')->constrained()->restrictOnDelete();
            $table->unsignedTinyInteger('days_before_due');
            $table->timestampTz('sent_at')->nullable();
            $table->timestamps();

            $table->unique(['billing_invoice_id', 'days_before_due']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('billing_renewal_reminders');
    }
};
