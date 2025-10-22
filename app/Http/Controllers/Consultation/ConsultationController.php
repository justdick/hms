<?php

namespace App\Http\Controllers\Consultation;

use App\Http\Controllers\Controller;
use App\Models\Consultation;
use App\Models\Department;
use App\Models\Diagnosis;
use App\Models\Drug;
use App\Models\LabService;
use App\Models\PatientCheckin;
use App\Models\Prescription;
use App\Models\User;
use App\Models\Ward;
use App\Services\MedicationScheduleService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ConsultationController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        // Check if user can view consultations
        $this->authorize('viewAny', PatientCheckin::class);

        $search = $request->input('search');

        // Get total counts (always show)
        $totalAwaitingCount = PatientCheckin::accessibleTo($user)
            ->whereIn('status', ['checked_in', 'vitals_taken', 'awaiting_consultation'])
            ->count();

        $totalActiveCount = Consultation::accessibleTo($user)
            ->inProgress()
            ->count();

        // Only query if search is provided and at least 2 characters
        if ($search && strlen($search) >= 2) {
            // Get patient check-ins awaiting consultation (accessible to user)
            $awaitingConsultation = PatientCheckin::with([
                'patient:id,patient_number,first_name,last_name,date_of_birth,phone_number',
                'department:id,name',
                'vitalSigns' => function ($query) {
                    $query->latest()->limit(1);
                },
            ])
                ->accessibleTo($user)
                ->whereIn('status', ['checked_in', 'vitals_taken', 'awaiting_consultation'])
                ->whereHas('patient', function ($query) use ($search) {
                    $query->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('patient_number', 'like', "%{$search}%")
                        ->orWhere('phone_number', 'like', "%{$search}%");
                })
                ->orderBy('checked_in_at')
                ->get();

            // Get active consultations (accessible to user)
            $activeConsultations = Consultation::with([
                'patientCheckin.patient:id,patient_number,first_name,last_name,date_of_birth,phone_number',
                'patientCheckin.department:id,name',
            ])
                ->accessibleTo($user)
                ->inProgress()
                ->whereHas('patientCheckin.patient', function ($query) use ($search) {
                    $query->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('patient_number', 'like', "%{$search}%")
                        ->orWhere('phone_number', 'like', "%{$search}%");
                })
                ->orderBy('started_at')
                ->get();
        } else {
            $awaitingConsultation = collect();
            $activeConsultations = collect();
        }

        return Inertia::render('Consultation/Index', [
            'awaitingConsultation' => $awaitingConsultation,
            'activeConsultations' => $activeConsultations,
            'totalAwaitingCount' => $totalAwaitingCount,
            'totalActiveCount' => $totalActiveCount,
            'search' => $search,
        ]);
    }

    public function show(Consultation $consultation)
    {
        $this->authorize('view', $consultation);

        $consultation->load([
            'patientCheckin.patient',
            'patientCheckin.department',
            'patientCheckin.vitalSigns' => function ($query) {
                $query->latest();
            },
            'doctor:id,name',
            'diagnoses.diagnosis',
            'prescriptions',
            'labOrders.labService',
            'labOrders.orderedBy:id,name',
        ]);

        // Get patient history
        $patient = $consultation->patientCheckin->patient;

        $patientHistory = [
            'previousConsultations' => Consultation::with([
                'doctor:id,name',
                'patientCheckin.department:id,name',
                'patientCheckin.vitalSigns' => function ($query) {
                    $query->latest()->limit(1);
                },
                'diagnoses.diagnosis:id,diagnosis,code,icd_10,g_drg',
                'prescriptions:id,consultation_id,medication_name,dosage,frequency,duration,instructions,status',
                'labOrders.labService:id,name,code,category,price,sample_type',
                'labOrders.orderedBy:id,name',
            ])
                ->whereHas('patientCheckin', function ($query) use ($patient) {
                    $query->where('patient_id', $patient->id);
                })
                ->where('id', '!=', $consultation->id)
                ->where('status', 'completed')
                ->orderBy('started_at', 'desc')
                ->limit(10)
                ->get(),
            'previousPrescriptions' => Prescription::with('consultation.doctor:id,name')
                ->whereHas('consultation.patientCheckin', function ($query) use ($patient) {
                    $query->where('patient_id', $patient->id);
                })
                ->whereHas('consultation', function ($query) use ($consultation) {
                    $query->where('id', '!=', $consultation->id);
                })
                ->orderBy('created_at', 'desc')
                ->limit(20)
                ->get(),
            'allergies' => [], // Could be extended with actual allergy data
        ];

        // Patient-level medical histories
        $patientHistories = [
            'past_medical_surgical_history' => $patient->past_medical_surgical_history ?? '',
            'drug_history' => $patient->drug_history ?? '',
            'family_history' => $patient->family_history ?? '',
            'social_history' => $patient->social_history ?? '',
        ];

        // OPD consultations only - no admission context

        return Inertia::render('Consultation/Show', [
            'consultation' => $consultation,
            'labServices' => LabService::active()->get(['id', 'name', 'code', 'category', 'price', 'sample_type']),
            'patientHistory' => $patientHistory,
            'patientHistories' => $patientHistories,
            'availableWards' => Ward::active()->available()->get(['id', 'name', 'code', 'available_beds']),
            'availableDrugs' => Drug::active()->orderBy('name')->get(['id', 'name', 'generic_name', 'brand_name', 'drug_code', 'form', 'strength', 'unit_price', 'unit_type']),
            'availableDepartments' => Department::active()->opd()->get(['id', 'name', 'code']),
            'availableDiagnoses' => Diagnosis::orderBy('diagnosis')->get(['id', 'diagnosis', 'code', 'g_drg', 'icd_10']),
        ]);
    }

    public function showEnhanced(Consultation $consultation)
    {
        $this->authorize('view', $consultation);

        $consultation->load([
            'patientCheckin.patient',
            'patientCheckin.department',
            'patientCheckin.vitalSigns' => function ($query) {
                $query->latest();
            },
            'doctor:id,name',
            'diagnoses',
            'prescriptions',
            'labOrders.labService',
            'labOrders.orderedBy:id,name',
        ]);

        // Get lab services with extended details for enhanced interface
        $labServices = LabService::active()->get([
            'id', 'name', 'code', 'category', 'price', 'sample_type',
            'turnaround_time', 'description', 'preparation_instructions',
            'normal_range', 'clinical_significance',
        ]);

        // Mock data for demonstration - in real implementation, these would be actual database queries
        $previousConsultations = [
            [
                'id' => 1,
                'date' => '2024-01-15',
                'department' => 'Internal Medicine',
                'doctor' => 'Dr. Smith',
                'chief_complaint' => 'Annual physical examination',
                'diagnosis' => 'Healthy adult, routine screening',
                'status' => 'completed',
            ],
            [
                'id' => 2,
                'date' => '2023-11-20',
                'department' => 'Cardiology',
                'doctor' => 'Dr. Johnson',
                'chief_complaint' => 'Chest pain evaluation',
                'diagnosis' => 'Atypical chest pain, rule out cardiac cause',
                'status' => 'completed',
            ],
        ];

        $medications = [
            [
                'id' => 1,
                'name' => 'Lisinopril 10mg',
                'dosage' => '10mg daily',
                'prescribed_date' => '2024-01-15',
                'status' => 'active',
                'prescribing_doctor' => 'Dr. Smith',
            ],
            [
                'id' => 2,
                'name' => 'Metformin 500mg',
                'dosage' => '500mg twice daily',
                'prescribed_date' => '2023-08-10',
                'status' => 'discontinued',
                'prescribing_doctor' => 'Dr. Brown',
            ],
        ];

        $allergies = [
            [
                'id' => 1,
                'allergen' => 'Penicillin',
                'reaction' => 'Rash and hives',
                'severity' => 'moderate',
                'date_noted' => '2020-05-15',
            ],
        ];

        $familyHistory = [
            [
                'id' => 1,
                'relationship' => 'Father',
                'condition' => 'Hypertension',
                'age_of_onset' => 45,
                'notes' => 'Well controlled with medication',
            ],
            [
                'id' => 2,
                'relationship' => 'Mother',
                'condition' => 'Type 2 Diabetes',
                'age_of_onset' => 52,
                'notes' => null,
            ],
        ];

        return Inertia::render('Consultation/ShowEnhanced', [
            'consultation' => $consultation,
            'labServices' => $labServices,
            'drugs' => Drug::active()->orderBy('name')->get(['id', 'name', 'form', 'strength', 'unit_type']),
            'previousConsultations' => $previousConsultations,
            'medications' => $medications,
            'allergies' => $allergies,
            'familyHistory' => $familyHistory,
        ]);
    }

    public function store(Request $request)
    {
        $this->authorize('create', Consultation::class);

        $request->validate([
            'patient_checkin_id' => 'required|exists:patient_checkins,id',
            'presenting_complaint' => 'nullable|string',
        ]);

        $patientCheckin = PatientCheckin::findOrFail($request->patient_checkin_id);

        // Ensure user can access this patient check-in
        $this->authorize('view', $patientCheckin);

        $consultation = Consultation::create([
            'patient_checkin_id' => $patientCheckin->id,
            'doctor_id' => $request->user()->id,
            'started_at' => now(),
            'status' => 'in_progress',
            'presenting_complaint' => $request->presenting_complaint,
        ]);

        // Update patient check-in status
        $patientCheckin->update([
            'consultation_started_at' => now(),
            'status' => 'in_consultation',
        ]);

        return redirect()->route('consultation.show', $consultation);
    }

    public function update(Request $request, Consultation $consultation)
    {
        $this->authorize('update', $consultation);

        $request->validate([
            'presenting_complaint' => 'nullable|string',
            'history_presenting_complaint' => 'nullable|string',
            'on_direct_questioning' => 'nullable|string',
            'examination_findings' => 'nullable|string',
            'assessment_notes' => 'nullable|string',
            'plan_notes' => 'nullable|string',
            'follow_up_date' => 'nullable|date|after:today',
            'past_medical_surgical_history' => 'nullable|string',
            'drug_history' => 'nullable|string',
            'family_history' => 'nullable|string',
            'social_history' => 'nullable|string',
        ]);

        // Update consultation-specific notes
        $consultation->update($request->only([
            'presenting_complaint',
            'history_presenting_complaint',
            'on_direct_questioning',
            'examination_findings',
            'assessment_notes',
            'plan_notes',
            'follow_up_date',
        ]));

        // Update patient-level histories if provided
        $patient = $consultation->patientCheckin->patient;
        $historyFields = $request->only([
            'past_medical_surgical_history',
            'drug_history',
            'family_history',
            'social_history',
        ]);

        if (! empty(array_filter($historyFields))) {
            $patient->update($historyFields);
        }

        return redirect()->back()->with('success', 'Consultation updated successfully.');
    }

    public function storePrescription(Request $request, Consultation $consultation, MedicationScheduleService $scheduleService)
    {
        $this->authorize('update', $consultation);

        $request->validate([
            'medication_name' => 'required|string|max:255',
            'drug_id' => 'nullable|exists:drugs,id',
            'dose_quantity' => 'nullable|string|max:50',
            'frequency' => 'required|string|max:100',
            'duration' => 'required|string|max:100',
            'quantity_to_dispense' => 'nullable|integer|min:1',
            'instructions' => 'nullable|string|max:1000',
        ]);

        $prescription = Prescription::create([
            'consultation_id' => $consultation->id,
            'medication_name' => $request->medication_name,
            'drug_id' => $request->drug_id,
            'dose_quantity' => $request->dose_quantity,
            'frequency' => $request->frequency,
            'duration' => $request->duration,
            'quantity' => $request->quantity_to_dispense, // Set for billing
            'quantity_to_dispense' => $request->quantity_to_dispense, // Set for dispensing
            'instructions' => $request->instructions,
            'status' => 'prescribed',
        ]);

        // Generate medication administration schedule for admitted patients
        $consultation->load('patientAdmission');
        if ($consultation->patientAdmission) {
            $scheduleService->generateSchedule($prescription);
        }

        return redirect()->back()->with('success', 'Prescription added successfully.');
    }

    public function destroyPrescription(Request $request, Consultation $consultation, Prescription $prescription)
    {
        $this->authorize('update', $consultation);

        // Ensure prescription belongs to this consultation
        if ($prescription->consultation_id !== $consultation->id) {
            abort(404);
        }

        // Only allow deletion if prescription is still 'prescribed' (not yet dispensed)
        if ($prescription->status !== 'prescribed') {
            return redirect()->back()->with('error', 'Cannot delete a prescription that has already been dispensed.');
        }

        $prescription->delete();

        return redirect()->back()->with('success', 'Prescription deleted successfully.');
    }

    public function complete(Request $request, Consultation $consultation)
    {
        $this->authorize('complete', $consultation);

        $consultation->update([
            'status' => 'completed',
            'completed_at' => now(),
        ]);

        // Update patient check-in status
        $consultation->patientCheckin->update([
            'consultation_completed_at' => now(),
            'status' => 'completed',
        ]);

        // Generate billing if there are services
        $this->generateBilling($consultation);

        return redirect()->route('consultation.index')
            ->with('success', 'Consultation completed successfully.');
    }

    protected function generateBilling(Consultation $consultation): void
    {
        // New billing system uses Charges model
        // Charges are automatically created via events (LabTestOrdered, etc.)
        // For consultation charge, create it here if not already exists

        $existingCharge = \App\Models\Charge::where('patient_checkin_id', $consultation->patientCheckin->id)
            ->where('service_type', 'consultation')
            ->first();

        if ($existingCharge) {
            return; // Charge already created
        }

        // Get department from the patient checkin
        $department = $consultation->patientCheckin->department;

        // Get consultation billing configuration using department ID
        $departmentBilling = \App\Models\DepartmentBilling::getForDepartment($department->id);

        if (! $departmentBilling || ! $departmentBilling->consultation_fee) {
            return; // No billing configured for this department
        }

        // Create consultation charge
        \App\Models\Charge::create([
            'patient_checkin_id' => $consultation->patientCheckin->id,
            'service_type' => 'consultation',
            'service_code' => 'CONSULT',
            'description' => "Consultation - {$department->name}",
            'amount' => $departmentBilling->consultation_fee,
            'charge_type' => 'consultation_fee',
            'status' => 'pending',
            'charged_at' => now(),
            'due_date' => now()->addDays(30),
            'created_by_type' => 'App\Models\User',
            'created_by_id' => $consultation->doctor_id,
        ]);
    }
}
