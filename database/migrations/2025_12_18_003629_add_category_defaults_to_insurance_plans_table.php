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
        Schema::table('insurance_plans', function (Blueprint $table) {
            $table->decimal('consultation_default', 5, 2)->nullable()->after('default_copay_percentage');
            $table->decimal('drugs_default', 5, 2)->nullable()->after('consultation_default');
            $table->decimal('labs_default', 5, 2)->nullable()->after('drugs_default');
            $table->decimal('procedures_default', 5, 2)->nullable()->after('labs_default');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('insurance_plans', function (Blueprint $table) {
            $table->dropColumn([
                'consultation_default',
                'drugs_default',
                'labs_default',
                'procedures_default',
            ]);
        });
    }
};
