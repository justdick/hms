<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ward_billing_templates', function (Blueprint $table) {
            $table->decimal('nhis_amount', 10, 2)->nullable()->after('base_amount')
                ->comment('Amount charged to NHIS patients (null = use base_amount, 0 = free for NHIS)');
        });
    }

    public function down(): void
    {
        Schema::table('ward_billing_templates', function (Blueprint $table) {
            $table->dropColumn('nhis_amount');
        });
    }
};
