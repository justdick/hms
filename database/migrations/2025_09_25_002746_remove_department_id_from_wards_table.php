<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wards', function (Blueprint $table) {
            // Check if foreign key exists before dropping
            $foreignKeys = DB::select("
                SELECT CONSTRAINT_NAME 
                FROM information_schema.KEY_COLUMN_USAGE 
                WHERE TABLE_SCHEMA = DATABASE() 
                AND TABLE_NAME = 'wards' 
                AND COLUMN_NAME = 'department_id'
                AND REFERENCED_TABLE_NAME IS NOT NULL
            ");

            if (count($foreignKeys) > 0) {
                $table->dropForeign(['department_id']);
            }

            if (Schema::hasColumn('wards', 'department_id')) {
                $table->dropColumn('department_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('wards', function (Blueprint $table) {
            $table->foreignId('department_id')->nullable()->constrained()->onDelete('set null');
        });
    }
};
