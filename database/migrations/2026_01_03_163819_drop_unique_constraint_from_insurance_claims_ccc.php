<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Drop the unique constraint on claim_check_code because NHIS can regenerate
     * the same CCC after some months/years. Uniqueness is now enforced at the
     * application level for active claims only.
     */
    public function up(): void
    {
        Schema::table('insurance_claims', function (Blueprint $table) {
            $table->dropUnique(['claim_check_code']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('insurance_claims', function (Blueprint $table) {
            $table->unique('claim_check_code');
        });
    }
};
