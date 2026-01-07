<?php

namespace App\Http\Controllers\Admission;

use App\Events\PatientAdmitted;
use App\Http\Controllers\Controller;
use App\Models\Bed;
use App\Models\Consultation;
use App\Models\PatientAdmission;
use App\Models\Ward;
use App\Services\VitalsScheduleService;
use Illuminate\Http\Request;

class AdmissionController extends Controller
{
    public function __construct(
        protected VitalsScheduleService $vitalsScheduleService,
    ) {}

    public function store(Request $request, Consultation $consultation)
    {
        $this->authorize('update', $consultation);

        $request->validate([
            'ward_id' => 'required|exists:wards,id',
            'admission_notes' => 'nullable|string|max:2000',
        ]);

        // Check if patient already has an active admission from this consultation
        $existingAdmission = PatientAdmission::where('consultation_id', $consultation->id)
            ->where('status', 'admitted')
            ->first();

        if ($existingAdmission) {
            return redirect()->route('wards.patients.show', [$existingAdmission->ward_id, $existingAdmission->id])
                ->with('info', "Patient already admitted. Admission Number: {$existingAdmission->admission_number}");
        }

        // Also check if patient has any active admission (regardless of consultation)
        $activeAdmission = PatientAdmission::where('patient_id', $consultation->patientCheckin->patient_id)
            ->where('status', 'admitted')
            ->first();

        if ($activeAdmission) {
            return redirect()->back()->withErrors([
                'admission' => "Patient already has an active admission ({$activeAdmission->admission_number}). Please discharge first before creating a new admission.",
            ]);
        }

        $ward = Ward::findOrFail($request->ward_id);

        // Check if ward has available beds
        if ($ward->available_beds <= 0) {
            return redirect()->back()->withErrors(['ward_id' => 'No available beds in the selected ward.']);
        }

        // Use consultation's service_date for admitted_at to maintain date consistency
        // This ensures backdated consultations result in backdated admissions
        $admittedAt = $consultation->service_date
            ? $consultation->service_date->startOfDay()
            : now();

        $admission = PatientAdmission::create([
            'admission_number' => PatientAdmission::generateAdmissionNumber(),
            'patient_id' => $consultation->patientCheckin->patient->id,
            'consultation_id' => $consultation->id,
            'bed_id' => null, // Nurses will assign bed later
            'ward_id' => $ward->id,
            'attending_doctor_id' => $request->user()->id,
            'status' => 'admitted',
            'admission_notes' => $request->admission_notes,
            'admitted_at' => $admittedAt,
        ]);

        // Decrease available beds count (nurses will assign specific bed later)
        $ward->decrement('available_beds');

        // Update consultation status
        $consultation->update(['status' => 'completed', 'completed_at' => now()]);
        $consultation->patientCheckin->update([
            'consultation_completed_at' => now(),
            'status' => 'admitted',
        ]);

        // Create default vitals schedule (4 hours interval)
        $this->vitalsScheduleService->createSchedule(
            $admission,
            240, // 4 hours = 240 minutes
            $request->user()
        );

        // Note: Medication schedules are NOT auto-generated here.
        // Ward staff must configure medication schedules via "Medication History" tab
        // to ensure appropriate timing for ward rounds.

        // Fire admission event for billing
        event(new PatientAdmitted(
            checkin: $consultation->patientCheckin,
            wardType: $ward->name, // Use ward name instead of type
            bedNumber: null
        ));

        return redirect()->route('consultation.index')
            ->with('success', "Patient admitted successfully. Admission Number: {$admission->admission_number}. Default vitals schedule (4 hours) created.");
    }

    public function getAvailableWards()
    {
        $wards = Ward::active()
            ->available()
            ->get(['id', 'name', 'code', 'available_beds']);

        return response()->json([
            'wards' => $wards,
        ]);
    }

    /**
     * Transfer patient to a different ward.
     */
    public function transfer(Request $request, PatientAdmission $admission)
    {
        $this->authorize('transfer', $admission);

        // Ensure patient is still admitted
        if ($admission->status !== 'admitted') {
            return redirect()->back()->withErrors(['error' => 'Cannot transfer a patient who is not currently admitted.']);
        }

        $request->validate([
            'to_ward_id' => 'required|exists:wards,id|different:current_ward',
            'transfer_reason' => 'required|string|max:1000',
            'transfer_notes' => 'nullable|string|max:2000',
        ], [
            'to_ward_id.different' => 'Please select a different ward to transfer to.',
        ]);

        $toWard = Ward::findOrFail($request->to_ward_id);

        // Check if destination ward has available beds
        if ($toWard->available_beds <= 0) {
            return redirect()->back()->withErrors(['to_ward_id' => 'No available beds in the selected ward.']);
        }

        // Cannot transfer to same ward
        if ($toWard->id === $admission->ward_id) {
            return redirect()->back()->withErrors(['to_ward_id' => 'Patient is already in this ward.']);
        }

        // Capture the old ward before transfer
        $fromWard = $admission->ward;

        $admission->transferToWard(
            $toWard,
            $request->user(),
            $request->transfer_reason,
            $request->transfer_notes
        );

        // Redirect back to the old ward (where the user was working)
        return redirect()->route('wards.show', $fromWard)
            ->with('success', "Patient transferred to {$toWard->name} successfully. Bed assignment pending.");
    }

    /**
     * Get transfer history for an admission.
     */
    public function transferHistory(PatientAdmission $admission)
    {
        $this->authorize('viewTransfers', $admission);

        $transfers = $admission->wardTransfers()
            ->with(['fromWard', 'fromBed', 'toWard', 'transferredBy'])
            ->orderBy('transferred_at', 'desc')
            ->get();

        return response()->json([
            'transfers' => $transfers,
        ]);
    }
}
