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
        Schema::table('charges', function (Blueprint $table) {
            $table->boolean('is_waived')->default(false)->after('is_emergency_override');
            $table->foreignId('waived_by')->nullable()->constrained('users')->after('is_waived');
            $table->timestamp('waived_at')->nullable()->after('waived_by');
            $table->text('waived_reason')->nullable()->after('waived_at');
            $table->decimal('adjustment_amount', 10, 2)->default(0.00)->after('waived_reason');
            $table->decimal('original_amount', 10, 2)->nullable()->after('adjustment_amount');

            // Indexes for new columns
            $table->index('is_waived');
            $table->index('waived_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('charges', function (Blueprint $table) {
            $table->dropIndex(['is_waived']);
            $table->dropIndex(['waived_by']);
            $table->dropForeign(['waived_by']);
            $table->dropColumn([
                'is_waived',
                'waived_by',
                'waived_at',
                'waived_reason',
                'adjustment_amount',
                'original_amount',
            ]);
        });
    }
};
