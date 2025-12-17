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
        Schema::table('diagnoses', function (Blueprint $table) {
            $table->boolean('is_custom')->default(false)->after('icd_10');
            $table->foreignId('created_by')->nullable()->after('is_custom')->constrained('users')->nullOnDelete();
        });

        // Make code nullable for custom diagnoses (they won't have official codes)
        Schema::table('diagnoses', function (Blueprint $table) {
            $table->string('code')->nullable()->change();
            $table->string('icd_10')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('diagnoses', function (Blueprint $table) {
            $table->dropForeign(['created_by']);
            $table->dropColumn(['is_custom', 'created_by']);
        });

        Schema::table('diagnoses', function (Blueprint $table) {
            $table->string('code')->nullable(false)->change();
            $table->string('icd_10')->nullable(false)->change();
        });
    }
};
