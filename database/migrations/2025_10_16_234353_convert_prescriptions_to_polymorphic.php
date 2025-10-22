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
        Schema::table('prescriptions', function (Blueprint $table) {
            // Add polymorphic columns
            $table->string('prescribable_type')->nullable()->after('consultation_id');
            $table->unsignedBigInteger('prescribable_id')->nullable()->after('prescribable_type');

            // Add index for polymorphic relationship
            $table->index(['prescribable_type', 'prescribable_id']);
        });

        // Migrate existing data: Set prescribable_type and prescribable_id for existing records
        DB::statement("
            UPDATE prescriptions
            SET prescribable_type = 'App\\\\Models\\\\Consultation',
                prescribable_id = consultation_id
            WHERE consultation_id IS NOT NULL
        ");

        // Now we can make consultation_id nullable or drop it
        // For safety, let's keep it for now but make it nullable
        Schema::table('prescriptions', function (Blueprint $table) {
            $table->unsignedBigInteger('consultation_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('prescriptions', function (Blueprint $table) {
            // Restore consultation_id from polymorphic data
            DB::statement("
                UPDATE prescriptions
                SET consultation_id = prescribable_id
                WHERE prescribable_type = 'App\\\\Models\\\\Consultation'
            ");

            // Remove polymorphic columns
            $table->dropIndex(['prescribable_type', 'prescribable_id']);
            $table->dropColumn(['prescribable_type', 'prescribable_id']);

            // Make consultation_id required again
            $table->unsignedBigInteger('consultation_id')->nullable(false)->change();
        });
    }
};
