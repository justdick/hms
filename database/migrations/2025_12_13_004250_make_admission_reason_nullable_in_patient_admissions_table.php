<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('patient_admissions', function (Blueprint $table) {
            $table->text('admission_reason')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('patient_admissions', function (Blueprint $table) {
            $table->text('admission_reason')->nullable(false)->change();
        });
    }
};
