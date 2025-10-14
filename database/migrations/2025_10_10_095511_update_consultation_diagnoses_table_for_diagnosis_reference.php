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
        Schema::table('consultation_diagnoses', function (Blueprint $table) {
            // Drop index first
            $table->dropIndex(['consultation_id', 'is_primary']);

            // Drop old columns
            $table->dropColumn(['icd_code', 'diagnosis_description', 'is_primary']);

            // Add new columns
            $table->foreignId('diagnosis_id')->after('consultation_id')->constrained('diagnoses')->cascadeOnDelete();
            $table->enum('type', ['provisional', 'principal'])->after('diagnosis_id')->default('provisional');

            // Add index
            $table->index(['consultation_id', 'type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('consultation_diagnoses', function (Blueprint $table) {
            // Drop new index
            $table->dropIndex(['consultation_id', 'type']);

            // Drop new columns
            $table->dropForeign(['diagnosis_id']);
            $table->dropColumn(['diagnosis_id', 'type']);

            // Restore old columns
            $table->string('icd_code', 10);
            $table->text('diagnosis_description');
            $table->boolean('is_primary')->default(false);

            // Restore old index
            $table->index(['consultation_id', 'is_primary']);
        });
    }
};
