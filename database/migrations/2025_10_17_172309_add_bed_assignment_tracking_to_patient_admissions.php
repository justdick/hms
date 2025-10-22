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
        Schema::table('patient_admissions', function (Blueprint $table) {
            $table->foreignId('bed_assigned_by_id')->nullable()->after('bed_id')->constrained('users')->nullOnDelete();
            $table->timestamp('bed_assigned_at')->nullable()->after('bed_assigned_by_id');
            $table->boolean('is_overflow_patient')->default(false)->after('bed_assigned_at')->comment('Patient admitted without bed due to capacity');
            $table->text('overflow_notes')->nullable()->after('is_overflow_patient')->comment('Notes about overflow situation');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('patient_admissions', function (Blueprint $table) {
            $table->dropForeign(['bed_assigned_by_id']);
            $table->dropColumn(['bed_assigned_by_id', 'bed_assigned_at', 'is_overflow_patient', 'overflow_notes']);
        });
    }
};
