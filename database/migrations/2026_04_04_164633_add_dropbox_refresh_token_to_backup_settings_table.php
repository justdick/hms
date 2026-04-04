<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('backup_settings', function (Blueprint $table) {
            $table->text('dropbox_refresh_token')->nullable()->after('dropbox_access_token');
            $table->string('dropbox_app_key', 255)->nullable()->after('dropbox_refresh_token');
            $table->string('dropbox_app_secret', 255)->nullable()->after('dropbox_app_key');
        });
    }

    public function down(): void
    {
        Schema::table('backup_settings', function (Blueprint $table) {
            $table->dropColumn(['dropbox_refresh_token', 'dropbox_app_key', 'dropbox_app_secret']);
        });
    }
};
