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
        DB::statement("ALTER TABLE nursing_notes MODIFY COLUMN type ENUM('assessment', 'care', 'observation', 'incident', 'handover', 'admission')");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("ALTER TABLE nursing_notes MODIFY COLUMN type ENUM('assessment', 'care', 'observation', 'incident', 'handover')");
    }
};
