<?php

namespace App\Http\Controllers\Checkin;

use App\Events\PatientCheckedIn;
use App\Http\Controllers\Controller;
use App\Models\Consultation;
use App\Models\Department;
use App\Models\InsuranceClaim;
use App\Models\InsurancePlan;
use App\Models\NhisSettings;
use App\Models\Patient;
use App\Models\PatientCheckin;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CheckinController extends Controller
{
    public function index(Request $request)
    {
        // Check permission using policy
        $this->authorize('viewAny', PatientCheckin::class);

        $user = auth()->user();

        // Show today's completed check-ins and all incomplete check-ins
        // FIFO ordering: oldest first (lower ID = checked in earlier)
        $todayCheckins = PatientCheckin::with(['patient', 'department', 'insuranceClaim', 'vitalSigns'])
            ->where(function ($query) {
                $query->whereDate('checked_in_at', today())
                    ->orWhereIn('status', ['checked_in', 'vitals_taken', 'awaiting_consultation', 'in_consultation']);
            })
            ->when(! $user->can('viewAnyDepartment', PatientCheckin::class), function ($query) use ($user) {
                // Restrict to user's departments if they don't have cross-department permission
                $query->whereIn('department_id', $user->departments->pluck('id'));
            })
            ->orderBy('id', 'asc')
            ->get();

        // Get departments based on permissions
        $departments = $user->can('viewAnyDepartment', PatientCheckin::class)
            ? Department::active()->opd()->get()
            : $user->departments()->active()->opd()->get();

        // Get active insurance plans
        $insurancePlans = InsurancePlan::with('provider')
            ->active()
            ->orderBy('plan_name')
            ->get()
            ->map(fn ($plan) => [
                'id' => $plan->id,
                'plan_name' => $plan->plan_name,
                'plan_code' => $plan->plan_code,
                'provider' => [
                    'id' => $plan->provider->id,
                    'name' => $plan->provider->name,
                    'code' => $plan->provider->code,
                    'is_nhis' => $plan->provider->is_nhis ?? false,
                ],
            ]);

        // Get NHIS settings for registration form
        $nhisSettings = NhisSettings::getInstance();
        $nhisCredentials = null;
        if ($nhisSettings->verification_mode === 'extension' && $nhisSettings->nhia_username) {
            try {
                $nhisCredentials = [
                    'username' => $nhisSettings->nhia_username,
                    'password' => $nhisSettings->nhia_password,
                ];
            } catch (\Illuminate\Contracts\Encryption\DecryptException $e) {
                // Password was encrypted with a different APP_KEY, skip credentials
                $nhisCredentials = null;
            }
        }

        return Inertia::render('Checkin/Index', [
            'todayCheckins' => $todayCheckins,
            'departments' => $departments,
            'insurancePlans' => $insurancePlans,
            'nhisSettings' => [
                'verification_mode' => $nhisSettings->verification_mode,
                'nhia_portal_url' => $nhisSettings->nhia_portal_url,
                'auto_open_portal' => $nhisSettings->auto_open_portal,
                'credentials' => $nhisCredentials,
            ],
            'permissions' => [
                'canViewAnyDate' => $user->can('viewAnyDate', PatientCheckin::class),
                'canViewAnyDepartment' => $user->can('viewAnyDepartment', PatientCheckin::class),
                'canUpdateDate' => $user->can('checkins.update-date'),
                'canCancelCheckin' => $user->can('checkins.cancel') || $user->can('checkins.update') || $user->hasRole('Admin'),
                'canEditVitals' => $user->can('vitals.update') || $user->can('checkins.update') || $user->hasRole('Admin'),
            ],
        ]);
    }

    public function dashboard()
    {
        $this->authorize('viewAny', PatientCheckin::class);

        return $this->index();
    }

    public function store(Request $request)
    {
        $this->authorize('create', PatientCheckin::class);

        $validated = $request->validate([
            'patient_id' => 'required|exists:patients,id',
            'department_id' => 'required|exists:departments,id',
            'notes' => 'nullable|string',
            'has_insurance' => 'nullable|boolean',
            'claim_check_code' => [
                'nullable',
                'required_if:has_insurance,true',
                'string',
                'max:50',
            ],
            'service_date' => 'nullable|date|before_or_equal:today',
            'confirm_admission_override' => 'nullable|boolean',
        ]);

        // Validate department exists and get its name for error messages
        $department = Department::find($validated['department_id']);
        if (! $department) {
            return back()->withErrors([
                'department_id' => 'Invalid department selected. Please choose a valid department.',
            ])->withInput();
        }

        // Check for duplicate CCC - must be unique for active (non-completed) claims
        // NHIS can regenerate same CCC after months/years, so we only block if CCC is in active use
        // Migrated data from Mittag is excluded - those CCCs can be reused
        if (! empty($validated['claim_check_code'])) {
            // Check if CCC already exists in insurance_claims for an active claim
            // Active = not yet submitted/approved/paid/rejected (still in workflow)
            // Exclude claims linked to migrated checkins
            $existingClaim = InsuranceClaim::where('claim_check_code', $validated['claim_check_code'])
                ->whereIn('status', ['draft', 'pending_vetting', 'vetted'])
                ->whereHas('checkin', fn ($q) => $q->where('migrated_from_mittag', false))
                ->first();

            if ($existingClaim) {
                return back()->withErrors([
                    'claim_check_code' => 'This CCC is currently in use by an active claim that has not been submitted yet.',
                ])->withInput();
            }

            // Also check patient_checkins for active check-ins (not completed/cancelled) that haven't created claims yet
            // Exclude migrated checkins - those CCCs can be reused
            $duplicateCcc = PatientCheckin::where('claim_check_code', $validated['claim_check_code'])
                ->where('migrated_from_mittag', false)
                ->whereNotIn('status', ['completed', 'cancelled'])
                ->whereDoesntHave('insuranceClaim') // No claim created yet
                ->exists();

            if ($duplicateCcc) {
                return back()->withErrors([
                    'claim_check_code' => 'This CCC is currently in use by another active check-in.',
                ])->withInput();
            }
        }

        $patient = Patient::with(['activeInsurance.plan.provider', 'activeAdmission.ward'])->find($validated['patient_id']);

        if (! $patient) {
            return back()->withErrors([
                'patient_id' => 'Patient not found. Please search for a valid patient.',
            ])->withInput();
        }

        // Use provided service_date or default to today
        $serviceDate = ! empty($validated['service_date'])
            ? $validated['service_date']
            : now()->toDateString();

        // Check for same-department same-day check-in (BLOCK)
        // This allows multi-department same-day check-ins while preventing duplicates to the same department
        $sameDeptCheckin = PatientCheckin::where('patient_id', $validated['patient_id'])
            ->where('department_id', $validated['department_id'])
            ->whereDate('service_date', $serviceDate)
            ->whereNotIn('status', ['cancelled'])
            ->first();

        if ($sameDeptCheckin) {
            return back()->withErrors([
                'department_id' => "Patient already checked in to {$department->name} today. Please select a different department.",
            ])->withInput();
        }

        // Check if patient is currently admitted (WARN, allow proceed with confirmation)
        $activeAdmission = $patient->activeAdmission;
        if ($activeAdmission && ! $request->boolean('confirm_admission_override')) {
            return back()->withErrors([
                'admission_warning' => true,
            ])->with('admission_details', [
                'id' => $activeAdmission->id,
                'admission_number' => $activeAdmission->admission_number,
                'ward' => $activeAdmission->ward->name ?? 'Unknown Ward',
                'admitted_at' => $activeAdmission->admitted_at?->toIso8601String(),
            ])->withInput();
        }

        // Auto-complete any in-progress consultations for this patient
        // Also update the associated check-in status
        $inProgressConsultations = Consultation::whereHas('patientCheckin', function ($query) use ($validated) {
            $query->where('patient_id', $validated['patient_id']);
        })
            ->where('status', 'in_progress')
            ->get();

        foreach ($inProgressConsultations as $consultation) {
            $consultation->update([
                'status' => 'completed',
                'completed_at' => now(),
            ]);

            $consultation->patientCheckin->update([
                'status' => 'completed',
                'consultation_completed_at' => now(),
            ]);
        }

        // Check if patient has an incomplete check-in to a DIFFERENT department (regardless of date)
        // Same-department same-day is already blocked above, so this catches incomplete check-ins to other departments
        $incompleteCheckin = PatientCheckin::where('patient_id', $validated['patient_id'])
            ->whereIn('status', ['checked_in', 'vitals_taken', 'awaiting_consultation', 'in_consultation'])
            ->first();

        if ($incompleteCheckin) {
            $incompleteDept = $incompleteCheckin->department;

            return back()->withErrors([
                'patient_id' => "Patient has an incomplete check-in at {$incompleteDept->name} (Status: {$incompleteCheckin->status}) that needs to be completed or cancelled first.",
            ])->with('existing_checkin', [
                'id' => $incompleteCheckin->id,
                'checked_in_at' => $incompleteCheckin->checked_in_at,
                'status' => $incompleteCheckin->status,
                'department' => $incompleteDept->name,
            ])->withInput();
        }

        $checkin = PatientCheckin::create([
            'patient_id' => $validated['patient_id'],
            'department_id' => $validated['department_id'],
            'checked_in_by' => auth()->id(),
            'checked_in_at' => now(),
            'service_date' => $serviceDate,
            'status' => 'checked_in',
            'notes' => $validated['notes'] ?? null,
            'claim_check_code' => $validated['claim_check_code'] ?? null,
            'created_during_admission' => $activeAdmission ? true : false,
        ]);

        $checkin->load(['patient', 'department', 'checkedInBy']);

        // Create insurance claim if patient has insurance and opted to use it
        if (! empty($validated['has_insurance']) && ! empty($validated['claim_check_code'])) {
            $activeInsurance = $patient->activeInsurance;

            if ($activeInsurance) {
                InsuranceClaim::create([
                    'claim_check_code' => $validated['claim_check_code'],
                    'folder_id' => $patient->patient_number,
                    'patient_id' => $patient->id,
                    'patient_insurance_id' => $activeInsurance->id,
                    'patient_checkin_id' => $checkin->id,
                    'patient_surname' => $patient->last_name,
                    'patient_other_names' => $patient->first_name,
                    'patient_dob' => $patient->date_of_birth,
                    'patient_gender' => $patient->gender,
                    'membership_id' => $activeInsurance->membership_id,
                    'date_of_attendance' => $checkin->checked_in_at,
                    'type_of_service' => 'outpatient',
                    'type_of_attendance' => 'routine',
                    'status' => 'pending_vetting',
                    'total_claim_amount' => 0,
                    'approved_amount' => 0,
                    'patient_copay_amount' => 0,
                    'insurance_covered_amount' => 0,
                ]);
            }
        }

        // Fire check-in event for billing
        event(new PatientCheckedIn($checkin));

        return redirect()->back()->with('success', 'Patient checked in successfully');
    }

    public function show(PatientCheckin $checkin)
    {
        $this->authorize('view', $checkin);

        $checkin->load([
            'patient',
            'department',
            'checkedInBy',
            'vitalSigns.recordedBy',
            'consultation.doctor',
        ]);

        return response()->json(['checkin' => $checkin]);
    }

    public function updateStatus(PatientCheckin $checkin, Request $request)
    {
        $this->authorize('update', $checkin);

        $validated = $request->validate([
            'status' => 'required|in:checked_in,vitals_taken,awaiting_consultation,in_consultation,completed,cancelled',
            'notes' => 'nullable|string',
        ]);

        $checkin->update($validated);

        return response()->json([
            'checkin' => $checkin->fresh(['patient', 'department']),
            'message' => 'Check-in status updated successfully',
        ]);
    }

    public function todayCheckins()
    {
        $this->authorize('viewAny', PatientCheckin::class);

        $checkins = PatientCheckin::with(['patient', 'department', 'checkedInBy'])
            ->today()
            ->orderBy('checked_in_at', 'desc')
            ->get();

        return response()->json(['checkins' => $checkins]);
    }

    public function departmentQueue(Department $department)
    {
        $this->authorize('viewAny', PatientCheckin::class);

        // Show all incomplete check-ins for this department regardless of date
        // FIFO ordering: oldest check-in first by ID
        $checkins = PatientCheckin::with(['patient', 'vitalSigns'])
            ->where('department_id', $department->id)
            ->whereIn('status', ['checked_in', 'vitals_taken', 'awaiting_consultation'])
            ->orderBy('id', 'asc')
            ->get();

        return response()->json([
            'department' => $department,
            'queue' => $checkins,
        ]);
    }

    public function cancel(PatientCheckin $checkin, Request $request)
    {
        $this->authorize('cancel', $checkin);

        // Note: Policy already checks if check-in can be cancelled
        // But we keep this validation for explicit error message
        if ($checkin->isCompleted() || $checkin->isCancelled()) {
            return back()->withErrors([
                'error' => 'Cannot cancel a completed or already cancelled check-in.',
            ]);
        }

        $validated = $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);

        // Cancel the check-in (this will also void pending charges)
        $checkin->cancel($validated['reason'] ?? null);

        return redirect()->back()->with('success', 'Check-in cancelled successfully. Any unpaid charges have been voided.');
    }

    public function updateDepartment(PatientCheckin $checkin, Request $request)
    {
        $this->authorize('update', $checkin);

        // Only allow department change if check-in hasn't started consultation
        if ($checkin->status === 'in_consultation') {
            return back()->withErrors([
                'error' => 'Cannot change department during consultation. Doctor should use transfer functionality.',
            ]);
        }

        $validated = $request->validate([
            'department_id' => 'required|exists:departments,id',
        ]);

        $checkin->update([
            'department_id' => $validated['department_id'],
        ]);

        return redirect()->back()->with('success', 'Department updated successfully.');
    }

    public function search(Request $request)
    {
        $this->authorize('viewAny', PatientCheckin::class);

        $user = auth()->user();

        // Check if user can view any date
        if (! $user->can('viewAnyDate', PatientCheckin::class)) {
            return response()->json([
                'error' => 'You do not have permission to view historical check-ins.',
            ], 403);
        }

        $validated = $request->validate([
            'date' => 'required|date',
            'department_id' => 'nullable|exists:departments,id',
        ]);

        $query = PatientCheckin::with(['patient', 'department', 'checkedInBy', 'vitalSigns'])
            ->whereDate('checked_in_at', $validated['date']);

        // Apply department filter if provided
        if (! empty($validated['department_id'])) {
            $query->where('department_id', $validated['department_id']);
        }

        // Restrict to user's departments if they don't have cross-department permission
        if (! $user->can('viewAnyDepartment', PatientCheckin::class)) {
            $query->whereIn('department_id', $user->departments->pluck('id'));
        }

        $checkins = $query->orderBy('checked_in_at', 'desc')->get();

        return response()->json([
            'checkins' => $checkins,
            'date' => $validated['date'],
            'department_id' => $validated['department_id'] ?? null,
        ]);
    }

    public function updateDate(PatientCheckin $checkin, Request $request)
    {
        $this->authorize('updateDate', $checkin);

        $validated = $request->validate([
            'checked_in_at' => 'required|date|before_or_equal:today',
        ]);

        $checkin->update([
            'checked_in_at' => $validated['checked_in_at'],
        ]);

        return redirect()->back()->with('success', 'Check-in date updated successfully.');
    }

    public function checkInsurance(Patient $patient)
    {
        $this->authorize('viewAny', PatientCheckin::class);

        $activeInsurance = $patient->activeInsurance;
        $nhisSettings = NhisSettings::getInstance();

        // Include credentials for extension mode (only if user has permission)
        $credentials = null;
        if ($nhisSettings->verification_mode === 'extension' && $nhisSettings->nhia_username) {
            try {
                $credentials = [
                    'username' => $nhisSettings->nhia_username,
                    'password' => $nhisSettings->nhia_password, // Decrypted automatically by model
                ];
            } catch (\Illuminate\Contracts\Encryption\DecryptException $e) {
                // Password was encrypted with different key, skip credentials
                \Log::warning('Failed to decrypt NHIA password for facility', [
                    'error' => $e->getMessage(),
                ]);
                $credentials = null;
            }
        }

        if (! $activeInsurance) {
            return response()->json([
                'has_insurance' => false,
                'nhis_settings' => [
                    'verification_mode' => $nhisSettings->verification_mode,
                    'nhia_portal_url' => $nhisSettings->nhia_portal_url,
                    'auto_open_portal' => $nhisSettings->auto_open_portal,
                    'credentials' => $credentials,
                ],
            ]);
        }

        $activeInsurance->load(['plan.provider']);

        // Check if this is an NHIS provider
        $isNhisProvider = $activeInsurance->plan->provider->is_nhis ?? false;

        // Check if coverage has expired
        $isExpired = $activeInsurance->coverage_end_date && $activeInsurance->coverage_end_date->isPast();

        return response()->json([
            'has_insurance' => true,
            'insurance' => [
                'id' => $activeInsurance->id,
                'membership_id' => $activeInsurance->membership_id,
                'policy_number' => $activeInsurance->policy_number,
                'plan' => [
                    'id' => $activeInsurance->plan->id,
                    'plan_name' => $activeInsurance->plan->plan_name,
                    'plan_code' => $activeInsurance->plan->plan_code,
                    'provider' => [
                        'id' => $activeInsurance->plan->provider->id,
                        'name' => $activeInsurance->plan->provider->name,
                        'code' => $activeInsurance->plan->provider->code,
                        'is_nhis' => $isNhisProvider,
                    ],
                ],
                'coverage_start_date' => $activeInsurance->coverage_start_date,
                'coverage_end_date' => $activeInsurance->coverage_end_date,
                'is_expired' => $isExpired,
            ],
            'nhis_settings' => [
                'verification_mode' => $nhisSettings->verification_mode,
                'nhia_portal_url' => $nhisSettings->nhia_portal_url,
                'auto_open_portal' => $nhisSettings->auto_open_portal,
                'credentials' => $credentials,
            ],
        ]);
    }

    /**
     * Get existing same-day CCC for a patient.
     * Used to auto-populate CCC field for multi-department same-day check-ins.
     */
    public function getSameDayCcc(Patient $patient, Request $request)
    {
        $this->authorize('viewAny', PatientCheckin::class);

        $validated = $request->validate([
            'service_date' => 'nullable|date',
        ]);

        // Use provided service_date or default to today
        $serviceDate = ! empty($validated['service_date'])
            ? $validated['service_date']
            : now()->toDateString();

        // Look for existing same-day check-in with a CCC
        $existingCheckin = PatientCheckin::where('patient_id', $patient->id)
            ->whereDate('service_date', $serviceDate)
            ->whereNotIn('status', ['cancelled'])
            ->whereNotNull('claim_check_code')
            ->where('claim_check_code', '!=', '')
            ->first();

        if ($existingCheckin) {
            return response()->json([
                'has_same_day_ccc' => true,
                'claim_check_code' => $existingCheckin->claim_check_code,
                'department' => $existingCheckin->department->name ?? 'Unknown',
                'checked_in_at' => $existingCheckin->checked_in_at?->toIso8601String(),
            ]);
        }

        return response()->json([
            'has_same_day_ccc' => false,
        ]);
    }
}
