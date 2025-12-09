<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tables that receive migrated data from Mittag old system.
     */
    private array $tables = [
        'patients',
        'drugs',
        'patient_checkins',
        'consultations',
        'vital_signs',
        'patient_admissions',
        'ward_rounds',
        'prescriptions',
        'lab_orders',
    ];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            if (Schema::hasTable($table) && ! Schema::hasColumn($table, 'migrated_from_mittag')) {
                Schema::table($table, function (Blueprint $table) {
                    $table->boolean('migrated_from_mittag')->default(false)->after('id');
                });
            }
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'migrated_from_mittag')) {
                Schema::table($table, function (Blueprint $table) {
                    $table->dropColumn('migrated_from_mittag');
                });
            }
        }
    }
};
