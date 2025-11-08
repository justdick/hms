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
            $table->dateTime('discontinued_at')->nullable()->after('status');
            $table->foreignId('discontinued_by_id')->nullable()->constrained('users')->after('discontinued_at');
            $table->text('discontinuation_reason')->nullable()->after('discontinued_by_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('prescriptions', function (Blueprint $table) {
            $table->dropForeign(['discontinued_by_id']);
            $table->dropColumn(['discontinued_at', 'discontinued_by_id', 'discontinuation_reason']);
        });
    }
};
