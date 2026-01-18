<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Sets is_imaging = true for all lab_services where category = 'Imaging'.
     * This ensures the imaging search scope works correctly.
     */
    public function up(): void
    {
        DB::table('lab_services')
            ->where('category', 'Imaging')
            ->update(['is_imaging' => true]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('lab_services')
            ->where('category', 'Imaging')
            ->update(['is_imaging' => false]);
    }
};
