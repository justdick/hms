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
        Schema::table('lab_services', function (Blueprint $table) {
            $table->boolean('is_imaging')->default(false)->after('is_active');
            $table->string('modality', 50)->nullable()->after('is_imaging');
        });

        // Update existing imaging services to set is_imaging = true
        DB::table('lab_services')
            ->where('category', 'Imaging')
            ->update(['is_imaging' => true]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('lab_services', function (Blueprint $table) {
            $table->dropColumn(['is_imaging', 'modality']);
        });
    }
};
