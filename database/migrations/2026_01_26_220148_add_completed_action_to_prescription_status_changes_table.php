<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Modify the enum to include 'completed'
        DB::statement("ALTER TABLE prescription_status_changes MODIFY COLUMN action ENUM('discontinued', 'resumed', 'completed')");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert to original enum values
        DB::statement("ALTER TABLE prescription_status_changes MODIFY COLUMN action ENUM('discontinued', 'resumed')");
    }
};
