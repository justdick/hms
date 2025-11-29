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
        Schema::table('patients', function (Blueprint $table) {
            $table->boolean('is_credit_eligible')->default(false)->after('status');
            $table->text('credit_reason')->nullable()->after('is_credit_eligible');
            $table->foreignId('credit_authorized_by')->nullable()->after('credit_reason')->constrained('users');
            $table->timestamp('credit_authorized_at')->nullable()->after('credit_authorized_by');

            $table->index('is_credit_eligible');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->dropIndex(['is_credit_eligible']);
            $table->dropForeign(['credit_authorized_by']);
            $table->dropColumn([
                'is_credit_eligible',
                'credit_reason',
                'credit_authorized_by',
                'credit_authorized_at',
            ]);
        });
    }
};
