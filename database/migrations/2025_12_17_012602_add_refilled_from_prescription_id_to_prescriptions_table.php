<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('prescriptions', function (Blueprint $table) {
            $table->foreignId('refilled_from_prescription_id')
                ->nullable()
                ->after('consultation_id')
                ->constrained('prescriptions')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('prescriptions', function (Blueprint $table) {
            $table->dropForeign(['refilled_from_prescription_id']);
            $table->dropColumn('refilled_from_prescription_id');
        });
    }
};
