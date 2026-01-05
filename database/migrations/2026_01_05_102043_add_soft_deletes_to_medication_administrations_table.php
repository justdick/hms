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
        Schema::table('medication_administrations', function (Blueprint $table) {
            $table->softDeletes();
            $table->foreignId('deleted_by_id')->nullable()->constrained('users');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('medication_administrations', function (Blueprint $table) {
            $table->dropForeign(['deleted_by_id']);
            $table->dropColumn(['deleted_at', 'deleted_by_id']);
        });
    }
};
