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
        Schema::table('insurance_claim_items', function (Blueprint $table) {
            $table->boolean('is_unmapped')->default(false)->after('is_approved');
            $table->boolean('has_flexible_copay')->default(false)->after('is_unmapped');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('insurance_claim_items', function (Blueprint $table) {
            $table->dropColumn(['is_unmapped', 'has_flexible_copay']);
        });
    }
};
