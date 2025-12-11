<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('nhis_settings', function (Blueprint $table) {
            $table->string('nhia_username')->nullable()->after('facility_code');
            $table->text('nhia_password')->nullable()->after('nhia_username'); // Will be encrypted
        });
    }

    public function down(): void
    {
        Schema::table('nhis_settings', function (Blueprint $table) {
            $table->dropColumn(['nhia_username', 'nhia_password']);
        });
    }
};
