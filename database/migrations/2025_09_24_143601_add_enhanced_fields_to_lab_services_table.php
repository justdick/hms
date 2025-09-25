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
        Schema::table('lab_services', function (Blueprint $table) {
            $table->text('preparation_instructions')->nullable()->after('description');
            $table->string('normal_range')->nullable()->after('turnaround_time');
            $table->text('clinical_significance')->nullable()->after('normal_range');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('lab_services', function (Blueprint $table) {
            $table->dropColumn([
                'preparation_instructions',
                'normal_range',
                'clinical_significance',
            ]);
        });
    }
};
