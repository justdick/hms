<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nhis_settings', function (Blueprint $table) {
            $table->id();
            $table->enum('verification_mode', ['manual', 'extension'])->default('manual');
            $table->string('nhia_portal_url')->default('https://ccc.nhia.gov.gh/');
            $table->string('facility_code')->nullable(); // HP Id from NHIA
            $table->boolean('auto_open_portal')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nhis_settings');
    }
};
