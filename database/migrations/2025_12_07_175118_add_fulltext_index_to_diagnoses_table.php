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
        // Skip fulltext index for SQLite (used in testing)
        if (DB::connection()->getDriverName() === 'sqlite') {
            return;
        }

        Schema::table('diagnoses', function (Blueprint $table) {
            $table->fullText(['diagnosis', 'icd_10'], 'diagnoses_fulltext_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Skip fulltext index for SQLite (used in testing)
        if (DB::connection()->getDriverName() === 'sqlite') {
            return;
        }

        Schema::table('diagnoses', function (Blueprint $table) {
            $table->dropFullText('diagnoses_fulltext_index');
        });
    }
};
