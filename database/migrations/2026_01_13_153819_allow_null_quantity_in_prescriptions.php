<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('prescriptions', function (Blueprint $table) {
            // Allow null quantity for injections/vials - pharmacy determines at dispensing
            $table->integer('quantity')->nullable()->default(null)->change();
        });
    }

    public function down(): void
    {
        Schema::table('prescriptions', function (Blueprint $table) {
            $table->integer('quantity')->nullable(false)->default(1)->change();
        });
    }
};
