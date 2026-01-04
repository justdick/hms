<?php

namespace App\Http\Controllers\Consultation;

use App\Http\Controllers\Controller;
use App\Http\Requests\Prescription\RefillPrescriptionsRequest;
use App\Http\Requests\Prescription\StorePrescriptionRequest;
use App\Http\Requests\Prescription\UpdatePrescriptionRequest;
use App\Models\Consultation;
use App\Models\Department;
use App\Models\Drug;
use App\Models\MinorProcedureType;
use App\Models\PatientCheckin;
use App\Models\Prescription;
use App\Models\User;
use App\Models\Ward;
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
        $departmentFilter = $request->input('department_id');

        // Exclude Minor Procedures department from consultation queue
        $minorProceduresDept = Department::where('code', 'ZOOM')->first();
        $excludeDeptId = $minorProceduresDept?->id;

        // Base query for awaiting consultation
        $awaitingQuery = PatientCheckin::with([
            'patient:id,patient_number,first_name,last_name,date_of_birth,phone_number',
            'department:id,name',
            'vitalSigns' => function ($query) {
                $query->latest()->limit(1);
            },
        ])
            ->accessibleTo($user)
            ->whereIn('status', ['checked_in', 'vitals_taken', 'awaiting_consultation'])
            ->when($excludeDeptId, fn ($q) => $q->where('department_id', '!=', $excludeDeptId));

        // Base query for active consultations
        $activeQuery = Consultation::with([
            'patientCheckin.patient:id,patient_number,first_name,last_name,date_of_birth,phone_number',
            'patientCheckin.department:id,name',
            'doctor:id,name',
        ])
            ->accessibleTo($user)
            ->inProgress();

        // Apply department filter if provided
        if ($departmentFilter) {
            $awaitingQuery->where('department_id', $departmentFilter);
            $activeQuery->whereHas('patientCheckin', fn ($q) => $q->where('department_id', $departmentFilter));
        }

        // Apply search filter if provided (for search tab)
        if ($search && strlen($search) >= 2) {
            $awaitingQuery->whereHas('patient', function ($query) use ($search) {
                $query->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('patient_number', 'like', "%{$search}%")
                    ->orWhere('phone_number', 'like', "%{$search}%");
            });

            $activeQuery->whereHas('patientCheckin.patient', function ($query) use ($search) {
                $query->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('patient_number', 'like', "%{$search}%")
                    ->orWhere('phone_number', 'like', "%{$search}%");
            });
        }

        // Get results
        $awaitingConsultation = $awaitingQuery->orderBy('checked_in_at')->get();
        $activeConsultations = $activeQuery->orderBy('started_at')->get();

        // Query for completed consultations (last 24 hours)
        $completedQuery = Consultation::with([
            'patientCheckin.patient:id,patient_number,first_name,last_name,date_of_birth,phone_number',
            'patientCheckin.department:id,name',
            'doctor:id,name',
        ])
            ->accessibleTo($user)
            ->where('status', 'completed')
            ->where('completed_at', '>=', now()->subHours(24));

        // Apply department filter if provided
        if ($departmentFilter) {
            $completedQuery->whereHas('patientCheckin', fn ($q) => $q->where('department_id', $departmentFilter));
        }

        // Apply search filter if provided
        if ($search && strlen($search) >= 2) {
            $completedQuery->whereHas('patientCheckin.patient', function ($query) use ($search) {
                $query->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('patient_number', 'like', "%{$search}%")
                    ->orWhere('phone_number', 'like', "%{$search}%");
            });
        }

        $completedConsultations = $completedQuery->orderBy('completed_at', 'desc')->limit(50)->get();

        // Get departments for filter dropdown (only departments user has access to)
        $departments = Department::active()
            ->opd()
            ->when($excludeDeptId, fn ($q) => $q->where('id', '!=', $excludeDeptId))
            ->orderBy('name')
            ->get(['id', 'name', 'code']);

        return Inertia::render('Consultation/Index', [
            'awaitingConsultation' => $awaitingConsultation,
            'activeConsultations' => $activeConsultations,
            'completedConsultations' => $completedConsultations,
            'totalAwaitingCount' => $awaitingConsultation->count(),
            'totalActiveCount' => $activeConsultations->count(),
            'totalCompletedCount' => $completedConsultations->count(),
            'departments' => $departments,
            'filters' => [
                'search' => $search,
                'department_id' => $departmentFilter,
            ],
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
            'procedures.procedureType',
            'procedures.doctor:id,name',
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
                'prescriptions:id,consultation_id,medication_name,dose_quantity,frequency,duration,instructions,status',
                'labOrders' => function ($query) {
                    // Only include laboratory tests (non-imaging) in previous consultations
                    $query->laboratory();
                },
                'labOrders.labService:id,name,code,category,price,sample_type,is_imaging',
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
            'previousMinorProcedures' => \App\Models\MinorProcedure::with([
                'nurse:id,name',
                'procedureType:id,name,code',
                'patientCheckin.department:id,name',
                'diagnoses:id,diagnosis,code,icd_10,g_drg',
                'supplies.drug:id,name,form,strength',
            ])
                ->whereHas('patientCheckin', function ($query) use ($patient) {
                    $query->where('patient_id', $patient->id);
                })
                ->where('status', 'completed')
                ->orderBy('performed_at', 'desc')
                ->limit(10)
                ->get(),
            'previousPrescriptions' => Prescription::with([
                'drug:id,name,form,strength,unit_type,bottle_size',
                'consultation.doctor:id,name',
                'consultation.patientCheckin.department:id,name',
            ])
                ->whereHas('consultation.patientCheckin', function ($query) use ($patient) {
                    $query->where('patient_id', $patient->id);
                })
                ->whereHas('consultation', function ($query) use ($consultation) {
                    $query->where('id', '!=', $consultation->id)
                        ->where('status', 'completed');
                })
                // Only show prescriptions created in this system, not migrated ones
                ->where(function ($query) {
                    $query->whereNull('migrated_from_mittag')
                        ->orWhere('migrated_from_mittag', false);
                })
                ->orderBy('created_at', 'desc')
                ->get(),
            // Separate imaging history - includes both internal and external imaging studies
            'previousImagingStudies' => \App\Models\LabOrder::with([
                'labService:id,name,code,category,modality,is_imaging',
                'orderedBy:id,name',
                'imagingAttachments' => function ($query) {
                    $query->select('id', 'lab_order_id', 'file_name', 'file_type', 'is_external', 'external_facility_name', 'external_study_date');
                },
            ])
                ->imaging() // Only imaging orders
                ->where(function ($query) use ($patient, $consultation) {
                    // Include orders from consultations
                    $query->whereHasMorph('orderable', [\App\Models\Consultation::class], function ($q) use ($patient, $consultation) {
                        $q->whereHas('patientCheckin', function ($checkinQuery) use ($patient) {
                            $checkinQuery->where('patient_id', $patient->id);
                        })->where('id', '!=', $consultation->id);
                    })
                    // Also include orders from ward rounds
                        ->orWhereHasMorph('orderable', [\App\Models\WardRound::class], function ($q) use ($patient) {
                            $q->whereHas('patientAdmission', function ($admissionQuery) use ($patient) {
                                $admissionQuery->where('patient_id', $patient->id);
                            });
                        });
                })
                ->whereIn('status', ['completed', 'in_progress']) // Include completed and in-progress imaging
                ->orderBy('ordered_at', 'desc')
                ->limit(20)
                ->get()
                ->map(function ($order) {
                    // Add has_images flag for frontend
                    $order->has_images = $order->imagingAttachments->count() > 0;
                    // Check if any attachment is external
                    $order->is_external = $order->imagingAttachments->contains('is_external', true);

                    return $order;
                }),
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

        $user = auth()->user();

        return Inertia::render('Consultation/Show', [
            'consultation' => $consultation,
            // Lab services loaded via async search - too many to load upfront
            'patientHistory' => $patientHistory,
            'patientHistories' => $patientHistories,
            'availableWards' => Ward::active()->available()->get(['id', 'name', 'code', 'available_beds']),
            'availableDrugs' => Drug::active()->orderBy('name')->get(['id', 'name', 'generic_name', 'brand_name', 'drug_code', 'form', 'strength', 'unit_price', 'unit_type', 'bottle_size']),
            'availableDepartments' => Department::active()->opd()->get(['id', 'name', 'code']),
            // Diagnoses loaded via async search - too many to load upfront
            'availableProcedures' => MinorProcedureType::active()->orderBy('type')->orderBy('name')->get(['id', 'name', 'code', 'type', 'category', 'price']),
            'can' => [
                'editVitals' => $user->can('vitals.update') || $user->can('update', $consultation) || $user->hasRole('Admin'),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $this->authorize('create', Consultation::class);

        $patientCheckin = PatientCheckin::findOrFail($request->patient_checkin_id);

        $request->validate([
            'patient_checkin_id' => 'required|exists:patient_checkins,id',
            'presenting_complaint' => 'nullable|string',
            'service_date' => [
                'nullable',
                'date',
                'before_or_equal:today',
                // Service date must be >= check-in's service_date
                function ($attribute, $value, $fail) use ($patientCheckin) {
                    if ($value && $patientCheckin->service_date) {
                        if ($value < $patientCheckin->service_date->toDateString()) {
                            $fail('Consultation date cannot be before the check-in date ('.$patientCheckin->service_date->format('Y-m-d').').');
                        }
                    }
                },
            ],
        ]);

        // Ensure user can access this patient check-in
        $this->authorize('view', $patientCheckin);

        // Use provided service_date, or inherit from check-in, or default to today
        $serviceDate = $request->service_date
            ?? $patientCheckin->service_date?->toDateString()
            ?? now()->toDateString();

        $consultation = Consultation::create([
            'patient_checkin_id' => $patientCheckin->id,
            'doctor_id' => $request->user()->id,
            'started_at' => now(),
            'service_date' => $serviceDate,
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

        // Return JSON for AJAX requests (autosave)
        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Consultation autosaved successfully.',
            ]);
        }

        return redirect()->back()->with('success', 'Consultation updated successfully.');
    }

    public function storePrescription(StorePrescriptionRequest $request, Consultation $consultation)
    {
        $this->authorize('update', $consultation);

        // Get prescription data (handles both Smart and Classic modes)
        $prescriptionData = $request->getPrescriptionData();

        $prescription = Prescription::create([
            'consultation_id' => $consultation->id,
            'medication_name' => $prescriptionData['medication_name'],
            'drug_id' => $prescriptionData['drug_id'],
            'dose_quantity' => $prescriptionData['dose_quantity'],
            'frequency' => $prescriptionData['frequency'],
            'duration' => $prescriptionData['duration'],
            'quantity' => $prescriptionData['quantity_to_dispense'], // Set for billing
            'quantity_to_dispense' => $prescriptionData['quantity_to_dispense'], // Set for dispensing
            'instructions' => $prescriptionData['instructions'],
            'status' => 'prescribed',
        ]);

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

    public function updatePrescription(UpdatePrescriptionRequest $request, Consultation $consultation, Prescription $prescription)
    {
        $this->authorize('update', $consultation);

        // Ensure prescription belongs to this consultation
        if ($prescription->consultation_id !== $consultation->id) {
            abort(404);
        }

        // Only allow editing if prescription is still 'prescribed' (not yet reviewed/dispensed)
        if ($prescription->status !== 'prescribed') {
            return redirect()->back()->with('error', 'Cannot edit a prescription that has already been reviewed or dispensed.');
        }

        $prescription->update([
            'drug_id' => $request->drug_id,
            'medication_name' => $request->medication_name,
            'dose_quantity' => $request->dose_quantity,
            'frequency' => $request->frequency,
            'duration' => $request->duration,
            'quantity' => $request->quantity_to_dispense,
            'quantity_to_dispense' => $request->quantity_to_dispense,
            'instructions' => $request->instructions,
        ]);

        return redirect()->back()->with('success', 'Prescription updated successfully.');
    }

    public function refillPrescriptions(RefillPrescriptionsRequest $request, Consultation $consultation)
    {
        $this->authorize('update', $consultation);

        $prescriptionIds = $request->prescription_ids;
        $patient = $consultation->patientCheckin->patient;

        // Fetch the original prescriptions with drug info
        $originalPrescriptions = Prescription::with('drug')
            ->whereIn('id', $prescriptionIds)
            ->whereHas('consultation.patientCheckin', function ($query) use ($patient) {
                $query->where('patient_id', $patient->id);
            })
            ->get();

        if ($originalPrescriptions->isEmpty()) {
            return redirect()->back()->with('error', 'No valid prescriptions found to refill.');
        }

        $refillCount = 0;
        $skippedDrugs = [];

        foreach ($originalPrescriptions as $original) {
            // Check if drug still exists and is active
            if ($original->drug && ! $original->drug->is_active) {
                $skippedDrugs[] = $original->medication_name;

                continue;
            }

            // Create new prescription as refill
            $newPrescription = Prescription::create([
                'consultation_id' => $consultation->id,
                'refilled_from_prescription_id' => $original->id,
                'drug_id' => $original->drug_id,
                'medication_name' => $original->medication_name,
                'dose_quantity' => $original->dose_quantity,
                'frequency' => $original->frequency,
                'duration' => $original->duration,
                'quantity' => $original->quantity_to_dispense ?? $original->quantity,
                'quantity_to_dispense' => $original->quantity_to_dispense ?? $original->quantity,
                'instructions' => $original->instructions,
                'status' => 'prescribed',
            ]);

            $refillCount++;
        }

        $message = $refillCount === 1
            ? '1 prescription refilled successfully.'
            : "{$refillCount} prescriptions refilled successfully.";

        if (! empty($skippedDrugs)) {
            $message .= ' Skipped inactive drugs: '.implode(', ', $skippedDrugs);
        }

        return redirect()->back()->with('success', $message);
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
