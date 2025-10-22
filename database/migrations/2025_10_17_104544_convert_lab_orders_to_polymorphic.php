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
        Schema::table('lab_orders', function (Blueprint $table) {
            // Add polymorphic columns
            $table->string('orderable_type')->nullable()->after('consultation_id');
            $table->unsignedBigInteger('orderable_id')->nullable()->after('orderable_type');

            // Add index for polymorphic relationship
            $table->index(['orderable_type', 'orderable_id']);
        });

        // Migrate existing data: Set orderable_type and orderable_id for existing records
        DB::statement("
            UPDATE lab_orders
            SET orderable_type = 'App\\\\Models\\\\Consultation',
                orderable_id = consultation_id
            WHERE consultation_id IS NOT NULL
        ");

        // Now we can make consultation_id nullable
        // For safety, let's keep it for now but make it nullable
        Schema::table('lab_orders', function (Blueprint $table) {
            $table->unsignedBigInteger('consultation_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('lab_orders', function (Blueprint $table) {
            // Restore consultation_id from polymorphic data
            DB::statement("
                UPDATE lab_orders
                SET consultation_id = orderable_id
                WHERE orderable_type = 'App\\\\Models\\\\Consultation'
            ");

            // Remove polymorphic columns
            $table->dropIndex(['orderable_type', 'orderable_id']);
            $table->dropColumn(['orderable_type', 'orderable_id']);

            // Make consultation_id required again
            $table->unsignedBigInteger('consultation_id')->nullable(false)->change();
        });
    }
};
