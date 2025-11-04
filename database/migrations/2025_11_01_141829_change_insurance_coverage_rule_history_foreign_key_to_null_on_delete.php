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
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            // SQLite requires recreating the table
            Schema::dropIfExists('insurance_coverage_rule_history');

            Schema::create('insurance_coverage_rule_history', function (Blueprint $table) {
                $table->id();
                $table->foreignId('insurance_coverage_rule_id')
                    ->nullable()
                    ->constrained('insurance_coverage_rules', 'id', 'icr_history_rule_fk')
                    ->nullOnDelete();
                $table->foreignId('user_id')
                    ->nullable()
                    ->constrained('users', 'id', 'icr_history_user_fk')
                    ->nullOnDelete();
                $table->string('action');
                $table->json('old_values')->nullable();
                $table->json('new_values')->nullable();
                $table->text('notes')->nullable();
                $table->string('batch_id')->nullable();
                $table->timestamps();

                $table->index(['insurance_coverage_rule_id', 'created_at'], 'icr_history_rule_created_idx');
                $table->index('batch_id', 'icr_history_batch_idx');
            });
        } else {
            Schema::table('insurance_coverage_rule_history', function (Blueprint $table) {
                // Drop the existing foreign key
                $table->dropForeign('icr_history_rule_fk');

                // Make the column nullable
                $table->unsignedBigInteger('insurance_coverage_rule_id')->nullable()->change();

                // Re-add the foreign key with nullOnDelete
                $table->foreign('insurance_coverage_rule_id', 'icr_history_rule_fk')
                    ->references('id')
                    ->on('insurance_coverage_rules')
                    ->nullOnDelete();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('insurance_coverage_rule_history', function (Blueprint $table) {
            $table->dropForeign('icr_history_rule_fk');

            $table->unsignedBigInteger('insurance_coverage_rule_id')->nullable(false)->change();

            $table->foreign('insurance_coverage_rule_id', 'icr_history_rule_fk')
                ->references('id')
                ->on('insurance_coverage_rules')
                ->cascadeOnDelete();
        });
    }
};
