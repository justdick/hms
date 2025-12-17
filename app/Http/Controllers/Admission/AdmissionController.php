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
}
