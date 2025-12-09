<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('nursing_notes', function (Blueprint $table) {
            $table->boolean('migrated_from_mittag')->default(false)->after('id');
        });
    }

    public function down(): void
    {
        Schema::table('nursing_notes', function (Blueprint $table) {
            $table->dropColumn('migrated_from_mittag');
        });
    }
};
