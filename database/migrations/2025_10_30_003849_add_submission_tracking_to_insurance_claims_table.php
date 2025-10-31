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
        Schema::table('insurance_claims', function (Blueprint $table) {
            // Add payment tracking fields
            $table->string('payment_reference')->nullable()->after('payment_date');
            $table->decimal('payment_amount', 12, 2)->nullable()->after('payment_reference');
            $table->foreignId('payment_recorded_by')->nullable()->constrained('users')->after('payment_amount');

            // Add resubmission tracking
            $table->integer('resubmission_count')->default(0)->after('payment_recorded_by');
            $table->timestamp('last_resubmitted_at')->nullable()->after('resubmission_count');

            // Add batch submission tracking
            $table->string('batch_reference')->nullable()->after('last_resubmitted_at');
            $table->timestamp('batch_submitted_at')->nullable()->after('batch_reference');

            // Add approval tracking
            $table->foreignId('approved_by')->nullable()->constrained('users')->after('batch_submitted_at');
            $table->foreignId('rejected_by')->nullable()->constrained('users')->after('approved_by');
            $table->timestamp('rejected_at')->nullable()->after('rejected_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('insurance_claims', function (Blueprint $table) {
            $table->dropForeign(['payment_recorded_by']);
            $table->dropForeign(['approved_by']);
            $table->dropForeign(['rejected_by']);

            $table->dropColumn([
                'payment_reference',
                'payment_amount',
                'payment_recorded_by',
                'resubmission_count',
                'last_resubmitted_at',
                'batch_reference',
                'batch_submitted_at',
                'approved_by',
                'rejected_by',
                'rejected_at',
            ]);
        });
    }
};
