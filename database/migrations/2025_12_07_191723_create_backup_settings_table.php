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
        Schema::create('backup_settings', function (Blueprint $table) {
            $table->id();
            $table->boolean('schedule_enabled')->default(false);
            $table->enum('schedule_frequency', ['daily', 'weekly', 'custom'])->default('daily');
            $table->time('schedule_time')->default('02:00:00');
            $table->string('cron_expression')->nullable();
            $table->unsignedInteger('retention_daily')->default(7);
            $table->unsignedInteger('retention_weekly')->default(4);
            $table->unsignedInteger('retention_monthly')->default(3);
            $table->boolean('google_drive_enabled')->default(false);
            $table->string('google_drive_folder_id')->nullable();
            $table->text('google_credentials')->nullable(); // Encrypted JSON
            $table->json('notification_emails')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('backup_settings');
    }
};
