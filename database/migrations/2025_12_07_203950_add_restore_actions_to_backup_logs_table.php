<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // For SQLite, we need to recreate the table since it doesn't support ALTER COLUMN
        // For MySQL, we can modify the enum directly
        $connection = config('database.default');

        if ($connection === 'sqlite') {
            // SQLite doesn't support enum, so we'll change to string
            Schema::table('backup_logs', function (Blueprint $table) {
                // Drop the old column and add a new string column
            });

            // For SQLite, the enum is stored as a string anyway, so no change needed
            // The validation happens at the application level
        } else {
            // MySQL - modify the enum to include new values
            DB::statement("ALTER TABLE backup_logs MODIFY COLUMN action ENUM(
                'created',
                'deleted',
                'restored',
                'downloaded',
                'settings_changed',
                'retention_cleanup',
                'restore_started',
                'pre_restore_backup_created',
                'restore_completed',
                'restore_failed',
                'recovery_started',
                'recovery_completed',
                'recovery_failed'
            )");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $connection = config('database.default');

        if ($connection !== 'sqlite') {
            DB::statement("ALTER TABLE backup_logs MODIFY COLUMN action ENUM(
                'created',
                'deleted',
                'restored',
                'downloaded',
                'settings_changed',
                'retention_cleanup'
            )");
        }
    }
};
