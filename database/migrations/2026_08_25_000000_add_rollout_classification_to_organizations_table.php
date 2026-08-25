<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Nullable and unbackfilled by design: assigning a classification is an
     * approved operational decision (see
     * docs/existing-organization-rollout-plan.md), never something this
     * migration infers from existing column values.
     */
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->string('rollout_classification')->nullable()->after('trial_ends_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->dropColumn('rollout_classification');
        });
    }
};
