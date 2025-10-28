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
        Schema::create('insurance_claims', function (Blueprint $table) {
            $table->id();
            $table->string('claim_check_code', 50)->unique();
            $table->string('folder_id', 50)->nullable();
            $table->foreignId('patient_id')->constrained('patients');
            $table->foreignId('patient_insurance_id')->constrained('patient_insurance');
            $table->foreignId('patient_checkin_id')->nullable()->constrained('patient_checkins');
            $table->foreignId('consultation_id')->nullable()->constrained('consultations');
            $table->foreignId('patient_admission_id')->nullable()->constrained('patient_admissions');

            // Patient details snapshot (denormalized)
            $table->string('patient_surname')->nullable();
            $table->string('patient_other_names')->nullable();
            $table->date('patient_dob')->nullable();
            $table->enum('patient_gender', ['male', 'female'])->nullable();
            $table->string('membership_id')->nullable();

            // Visit details
            $table->date('date_of_attendance');
            $table->date('date_of_discharge')->nullable();
            $table->enum('type_of_service', ['inpatient', 'outpatient']);
            $table->enum('type_of_attendance', ['emergency', 'acute', 'routine'])->default('routine');
            $table->string('specialty_attended')->nullable();
            $table->string('attending_prescriber')->nullable();
            $table->boolean('is_unbundled')->default(false);
            $table->boolean('is_pharmacy_included')->default(true);

            // Diagnosis
            $table->string('primary_diagnosis_code', 20)->nullable();
            $table->string('primary_diagnosis_description')->nullable();
            $table->json('secondary_diagnoses')->nullable();
            $table->string('c_drg_code', 50)->nullable();
            $table->string('hin_number')->nullable();

            // Financial
            $table->decimal('total_claim_amount', 12, 2)->default(0.00);
            $table->decimal('approved_amount', 12, 2)->default(0.00);
            $table->decimal('patient_copay_amount', 12, 2)->default(0.00);
            $table->decimal('insurance_covered_amount', 12, 2)->default(0.00);

            // Workflow status
            $table->enum('status', ['draft', 'pending_vetting', 'vetted', 'submitted', 'approved', 'rejected', 'paid', 'partial'])->default('draft');
            $table->foreignId('vetted_by')->nullable()->constrained('users');
            $table->timestamp('vetted_at')->nullable();
            $table->foreignId('submitted_by')->nullable()->constrained('users');
            $table->timestamp('submitted_at')->nullable();
            $table->date('submission_date')->nullable();
            $table->date('approval_date')->nullable();
            $table->date('payment_date')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();

            $table->index('claim_check_code', 'idx_claim_check_code');
            $table->index('status', 'idx_status');
            $table->index('date_of_attendance', 'idx_attendance_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('insurance_claims');
    }
};
