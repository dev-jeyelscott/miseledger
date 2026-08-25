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
        Schema::table('billing_subscriptions', function (Blueprint $table) {
            $table->string('external_subscription_id')->nullable()->change();
            $table->string('collection_method')->default('automatic')->after('interval');
            $table->index(['organization_id', 'collection_method']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('billing_subscriptions', function (Blueprint $table) {
            $table->dropIndex(['organization_id', 'collection_method']);
            $table->dropColumn('collection_method');
            $table->string('external_subscription_id')->nullable(false)->change();
        });
    }
};
