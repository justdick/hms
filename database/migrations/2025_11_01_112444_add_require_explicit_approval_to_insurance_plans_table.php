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
        Schema::table('insurance_plans', function (Blueprint $table) {
            $table->boolean('require_explicit_approval_for_new_items')
                ->default(false)
                ->after('requires_referral');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('insurance_plans', function (Blueprint $table) {
            $table->dropColumn('require_explicit_approval_for_new_items');
        });
    }
};
