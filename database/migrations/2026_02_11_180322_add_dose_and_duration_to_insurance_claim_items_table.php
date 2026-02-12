<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('insurance_claim_items', function (Blueprint $table) {
            $table->string('dose', 100)->nullable()->after('frequency');
            $table->string('duration', 100)->nullable()->after('dose');
        });
    }

    public function down(): void
    {
        Schema::table('insurance_claim_items', function (Blueprint $table) {
            $table->dropColumn(['dose', 'duration']);
        });
    }
};
