<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add indexes to optimize dashboard queries.
 *
 * These indexes target the most common dashboard query patterns:
 * - Date-based filtering (today's data)
 * - Status-based filtering
 * - Combined status + date queries
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Patient check-ins: optimize date + status queries for receptionist/doctor dashboards
        Schema::table('patient_checkins', function (Blueprint $table) {
            // Index for today's check-ins with status filtering
            $table->index(['checked_in_at', 'status'], 'idx_checkins_date_status');
            // Index for vitals_taken_at ordering in consultation queue
            $table->index('vitals_taken_at', 'idx_checkins_vitals_taken');
        });

        // Charges: optimize payment queries for cashier/finance dashboards
        Schema::table('charges', function (Blueprint $table) {
            // Index for today's paid charges (revenue calculations)
            $table->index(['paid_at', 'status'], 'idx_charges_paid_date_status');
            // Index for pending charges count
            $table->index(['status', 'is_waived'], 'idx_charges_status_waived');
        });

        // Medication administrations: optimize nurse dashboard queries
        Schema::table('medication_administrations', function (Blueprint $table) {
            // Index for scheduled medications by status and time
            $table->index(['status', 'scheduled_time'], 'idx_med_admin_status_scheduled');
        });

        // Patient admissions: optimize active admissions count
        Schema::table('patient_admissions', function (Blueprint $table) {
            // Index for status filtering
            $table->index('status', 'idx_admissions_status');
        });

        // Prescriptions: optimize pharmacy dashboard queries
        Schema::table('prescriptions', function (Blueprint $table) {
            // Index for pending prescriptions (status + reviewed_at)
            $table->index(['status', 'reviewed_at'], 'idx_prescriptions_status_reviewed');
        });

        // Lab orders: optimize pending results queries
        Schema::table('lab_orders', function (Blueprint $table) {
            // Index for completed orders with results
            $table->index(['status', 'result_entered_at'], 'idx_lab_orders_status_result');
        });

        // Insurance claims: optimize claims dashboard queries
        Schema::table('insurance_claims', function (Blueprint $table) {
            // Index for monthly approved claims
            $table->index(['status', 'updated_at'], 'idx_claims_status_updated');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('patient_checkins', function (Blueprint $table) {
            $table->dropIndex('idx_checkins_date_status');
            $table->dropIndex('idx_checkins_vitals_taken');
        });

        Schema::table('charges', function (Blueprint $table) {
            $table->dropIndex('idx_charges_paid_date_status');
            $table->dropIndex('idx_charges_status_waived');
        });

        Schema::table('medication_administrations', function (Blueprint $table) {
            $table->dropIndex('idx_med_admin_status_scheduled');
        });

        Schema::table('patient_admissions', function (Blueprint $table) {
            $table->dropIndex('idx_admissions_status');
        });

        Schema::table('prescriptions', function (Blueprint $table) {
            $table->dropIndex('idx_prescriptions_status_reviewed');
        });

        Schema::table('lab_orders', function (Blueprint $table) {
            $table->dropIndex('idx_lab_orders_status_result');
        });

        Schema::table('insurance_claims', function (Blueprint $table) {
            $table->dropIndex('idx_claims_status_updated');
        });
    }
};
