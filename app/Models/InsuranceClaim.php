<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class InsuranceClaim extends Model
{
    /** @use HasFactory<\Database\Factories\InsuranceClaimFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'claim_check_code',
        'folder_id',
        'patient_id',
        'patient_insurance_id',
        'patient_checkin_id',
        'consultation_id',
        'patient_admission_id',
        'patient_surname',
        'patient_other_names',
        'patient_dob',
        'patient_gender',
        'membership_id',
        'date_of_attendance',
        'date_of_discharge',
        'type_of_service',
        'type_of_attendance',
        'specialty_attended',
        'attending_prescriber',
        'is_unbundled',
        'is_pharmacy_included',
        'primary_diagnosis_code',
        'primary_diagnosis_description',
        'secondary_diagnoses',
        'c_drg_code',
        'gdrg_tariff_id',
        'gdrg_amount',
        'hin_number',
        'total_claim_amount',
        'approved_amount',
        'patient_copay_amount',
        'insurance_covered_amount',
        'status',
        'vetted_by',
        'vetted_at',
        'submitted_by',
        'submitted_at',
        'submission_date',
        'approval_date',
        'payment_date',
        'rejection_reason',
        'notes',
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
    ];

    protected function casts(): array
    {
        return [
            'patient_dob' => 'date',
            'date_of_attendance' => 'date',
            'date_of_discharge' => 'date',
            'is_unbundled' => 'boolean',
            'is_pharmacy_included' => 'boolean',
            'secondary_diagnoses' => 'json',
            'gdrg_amount' => 'decimal:2',
            'total_claim_amount' => 'decimal:2',
            'approved_amount' => 'decimal:2',
            'patient_copay_amount' => 'decimal:2',
            'insurance_covered_amount' => 'decimal:2',
            'payment_amount' => 'decimal:2',
            'vetted_at' => 'datetime',
            'submitted_at' => 'datetime',
            'submission_date' => 'date',
            'approval_date' => 'date',
            'payment_date' => 'date',
            'last_resubmitted_at' => 'datetime',
            'batch_submitted_at' => 'datetime',
            'rejected_at' => 'datetime',
        ];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function patientInsurance(): BelongsTo
    {
        return $this->belongsTo(PatientInsurance::class);
    }

    public function checkin(): BelongsTo
    {
        return $this->belongsTo(PatientCheckin::class, 'patient_checkin_id');
    }

    public function consultation(): BelongsTo
    {
        return $this->belongsTo(Consultation::class);
    }

    public function admission(): BelongsTo
    {
        return $this->belongsTo(PatientAdmission::class, 'patient_admission_id');
    }

    public function vettedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'vetted_by');
    }

    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(InsuranceClaimItem::class);
    }

    public function claimDiagnoses(): HasMany
    {
        return $this->hasMany(InsuranceClaimDiagnosis::class);
    }

    public function gdrgTariff(): BelongsTo
    {
        return $this->belongsTo(GdrgTariff::class);
    }

    /**
     * Get the batch items for this claim.
     */
    public function batchItems(): HasMany
    {
        return $this->hasMany(ClaimBatchItem::class);
    }

    /**
     * Check if this claim is for an NHIS patient.
     */
    public function isNhisClaim(): bool
    {
        return $this->patientInsurance?->plan?->provider?->isNhis() ?? false;
    }

    /**
     * Check if this claim requires a G-DRG selection.
     * NHIS claims require G-DRG selection for approval.
     */
    public function requiresGdrg(): bool
    {
        return $this->isNhisClaim();
    }
}
