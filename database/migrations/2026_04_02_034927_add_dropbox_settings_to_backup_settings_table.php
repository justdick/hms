<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('backup_settings', function (Blueprint $table) {
            $table->boolean('dropbox_enabled')->default(false)->after('google_credentials');
            $table->text('dropbox_access_token')->nullable()->after('dropbox_enabled');
            $table->string('dropbox_folder_path', 255)->nullable()->default('/HMS Backups')->after('dropbox_access_token');
        });
    }

    public function down(): void
    {
        Schema::table('backup_settings', function (Blueprint $table) {
            $table->dropColumn(['dropbox_enabled', 'dropbox_access_token', 'dropbox_folder_path']);
        });
    }
};
