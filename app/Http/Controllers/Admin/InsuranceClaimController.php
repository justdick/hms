<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\RecordInsurancePaymentRequest;
use App\Http\Requests\RejectInsuranceClaimRequest;
use App\Http\Requests\SubmitInsuranceClaimRequest;
use App\Http\Requests\VetClaimRequest;
use App\Http\Resources\InsuranceClaimResource;
use App\Http\Resources\InsuranceProviderResource;
use App\Models\InsuranceClaim;
use App\Models\InsuranceClaimItem;
use App\Models\InsuranceProvider;
use App\Models\NhisTariff;
use App\Services\ClaimVettingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class InsuranceClaimController extends Controller
{
    public function __construct(
        protected ClaimVettingService $claimVettingService
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', InsuranceClaim::class);

        $query = InsuranceClaim::query()
            ->with(['patientInsurance.plan.provider', 'vettedBy', 'submittedBy']);

        // Filter by status - default to pending_vetting if no status specified
        $status = $request->input('status', 'pending_vetting');
        if ($status && $status !== 'all') {
            $query->where('status', $status);
        }

        // Filter by insurance provider
        if ($request->filled('provider_id')) {
            $query->whereHas('patientInsurance.plan', function ($q) use ($request) {
                $q->where('insurance_provider_id', $request->provider_id);
            });
        }

        // Filter by date range
        if ($request->filled('date_from')) {
            $query->where('date_of_attendance', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->where('date_of_attendance', '<=', $request->date_to);
        }

        // Filter by service type (OPD/IPD)
        if ($request->filled('service_type')) {
            $query->where('type_of_service', $request->service_type);
        }

        // Search by claim code, patient name, membership ID, or patient number
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('claim_check_code', $search)
                    ->orWhere('patient_surname', 'like', "%{$search}%")
                    ->orWhere('patient_other_names', 'like', "%{$search}%")
                    ->orWhere('membership_id', $search)
                    ->orWhere('folder_id', $search)
                    ->orWhereHas('patient', function ($q) use ($search) {
                        $q->where('patient_number', $search);
                    });
            });
        }

        // Sort by claim_check_code first (for CCC grouping), then by date_of_attendance
        // This ensures same-day claims with the same CCC appear together
        $sortBy = $request->get('sort_by', 'date_of_attendance');
        $sortOrder = $request->get('sort_order', 'asc');

        // Sort by date_of_attendance first (default ASC), then group by claim_check_code
        // This ensures oldest claims appear first, with same-CCC claims grouped together
        $query->orderBy($sortBy, $sortOrder)
            ->orderBy('claim_check_code', 'asc')
            ->orderBy('id', 'asc');

        $perPage = $request->input('per_page', 5);
        $paginated = $query->paginate($perPage)->withQueryString();

        // Transform data while keeping flat pagination structure
        $claims = $paginated->through(fn ($claim) => (new InsuranceClaimResource($claim))->resolve());

        // Get all providers for filter dropdown
        $providers = InsuranceProvider::orderBy('name')->get();

        return Inertia::render('Admin/Insurance/Claims/Index', [
            'claims' => $claims,
            'providers' => InsuranceProviderResource::collection($providers),
            'filters' => array_merge(
                $request->only(['provider_id', 'date_from', 'date_to', 'search', 'service_type', 'date_preset']),
                ['status' => $status]
            ),
            'stats' => [
                'total' => InsuranceClaim::count(),
                'pending_vetting' => InsuranceClaim::where('status', 'pending_vetting')->count(),
                'vetted' => InsuranceClaim::where('status', 'vetted')->count(),
                'submitted' => InsuranceClaim::where('status', 'submitted')->count(),
            ],
        ]);
    }

    /**
     * Get all data needed for the vetting modal.
     * Returns patient info, attendance details, diagnoses, items, and totals.
     */
    public function getVettingData(InsuranceClaim $claim): JsonResponse
    {
        $this->authorize('view', $claim);

        $vettingData = $this->claimVettingService->getVettingData($claim);

        return response()->json([
            'claim' => InsuranceClaimResource::make($vettingData['claim'])->resolve(),
            'patient' => $vettingData['patient'],
            'attendance' => $vettingData['attendance'],
            'diagnoses' => $vettingData['diagnoses'],
            'items' => [
                'investigations' => $vettingData['items']['investigations']->map(fn ($item) => [
                    'id' => $item->id,
                    'name' => $item->description,
                    'code' => $item->code,
                    'nhis_code' => $item->nhis_code,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                    'nhis_price' => $item->nhis_price,
                    'subtotal' => $item->subtotal,
                    'is_covered' => $item->nhis_price !== null || ! $vettingData['is_nhis'],
                    'item_date' => $item->item_date?->format('Y-m-d'),
                ]),
                'prescriptions' => $vettingData['items']['prescriptions']->map(fn ($item) => [
                    'id' => $item->id,
                    'name' => $item->description,
                    'code' => $item->code,
                    'nhis_code' => $item->nhis_code,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                    'nhis_price' => $item->nhis_price,
                    'subtotal' => $item->subtotal,
                    'is_covered' => $item->nhis_price !== null || ! $vettingData['is_nhis'],
                    'frequency' => $item->frequency ?? $item->charge?->prescription?->frequency,
                    'dose' => $item->dose ?? $item->charge?->prescription?->dose_quantity,
                    'duration' => $item->duration ?? $item->charge?->prescription?->duration,
                    'item_date' => $item->item_date?->format('Y-m-d'),
                ]),
                'procedures' => $vettingData['items']['procedures']->map(fn ($item) => [
                    'id' => $item->id,
                    'name' => $item->description,
                    'code' => $item->code,
                    'nhis_code' => $item->nhis_code,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                    'nhis_price' => $item->nhis_price,
                    'subtotal' => $item->subtotal,
                    'is_covered' => $item->nhis_price !== null || ! $vettingData['is_nhis'],
                    'item_date' => $item->item_date?->format('Y-m-d'),
                ]),
            ],
            'totals' => $vettingData['totals'],
            'is_nhis' => $vettingData['is_nhis'],
            'gdrg_tariffs' => $vettingData['gdrg_tariffs']->map(fn ($tariff) => [
                'id' => $tariff->id,
                'code' => $tariff->code,
                'name' => $tariff->name,
                'tariff_price' => $tariff->tariff_price,
                'display_name' => $tariff->display_name,
            ]),
            // Diagnoses loaded via async search - too many to load upfront
            'can' => [
                'vet' => auth()->user()->can('vetClaim', $claim),
            ],
        ]);
    }

    /**
     * Get medical history for the patient associated with a claim.
     */
    public function getMedicalHistory(InsuranceClaim $claim): JsonResponse
    {
        $this->authorize('view', $claim);

        $patient = $claim->patient;

        if (! $patient) {
            return response()->json(['error' => 'Patient not found'], 404);
        }

        // Get consultations with related data
        $consultations = \App\Models\Consultation::with([
            'doctor:id,name',
            'patientCheckin:id,department_id,checked_in_at',
            'patientCheckin.department:id,name',
            'patientCheckin.vitalSigns' => fn ($q) => $q->with('recordedBy:id,name')->orderByDesc('recorded_at'),
            'diagnoses.diagnosis:id,code,diagnosis',
            'prescriptions.drug:id,name,generic_name,form,strength',
            'labOrders.labService:id,name,code,is_imaging,test_parameters',
            'procedures.procedureType:id,name,code',
        ])
            ->whereHas('patientCheckin', fn ($q) => $q->where('patient_id', $patient->id))
            ->where('status', 'completed')
            ->orderByDesc('started_at')
            ->limit(50)
            ->get()
            ->map(function ($consultation) {
                $vitals = $consultation->patientCheckin?->vitalSigns?->first();

                return [
                    'id' => $consultation->id,
                    'date' => $consultation->patientCheckin?->checked_in_at?->format('Y-m-d H:i'),
                    'doctor' => $consultation->doctor?->name,
                    'department' => $consultation->patientCheckin?->department?->name,
                    'presenting_complaint' => $consultation->presenting_complaint,
                    'history_presenting_complaint' => $consultation->history_presenting_complaint,
                    'examination_findings' => $consultation->examination_findings,
                    'assessment_notes' => $consultation->assessment_notes,
                    'plan_notes' => $consultation->plan_notes,
                    'vitals' => $vitals ? [
                        'blood_pressure' => $vitals->blood_pressure,
                        'temperature' => $vitals->temperature,
                        'pulse_rate' => $vitals->pulse_rate,
                        'respiratory_rate' => $vitals->respiratory_rate,
                        'oxygen_saturation' => $vitals->oxygen_saturation,
                        'weight' => $vitals->weight,
                        'height' => $vitals->height,
                        'bmi' => $vitals->bmi,
                        'recorded_at' => $vitals->recorded_at?->format('Y-m-d H:i'),
                        'recorded_by' => $vitals->recordedBy?->name,
                    ] : null,
                    'diagnoses' => $consultation->diagnoses->map(fn ($d) => [
                        'type' => $d->type,
                        'code' => $d->diagnosis?->code,
                        'description' => $d->diagnosis?->diagnosis,
                        'notes' => $d->notes,
                    ]),
                    'prescriptions' => $consultation->prescriptions->map(fn ($p) => [
                        'drug_name' => $p->drug?->name ?? $p->medication_name,
                        'generic_name' => $p->drug?->generic_name,
                        'form' => $p->drug?->form ?? $p->dosage_form,
                        'strength' => $p->drug?->strength,
                        'dose_quantity' => $p->dose_quantity,
                        'frequency' => $p->frequency,
                        'duration' => $p->duration,
                        'quantity' => $p->quantity,
                        'instructions' => $p->instructions,
                        'status' => $p->status,
                    ]),
                    'lab_orders' => $consultation->labOrders->map(fn ($l) => [
                        'id' => $l->id,
                        'service_name' => $l->labService?->name,
                        'code' => $l->labService?->code,
                        'is_imaging' => $l->labService?->is_imaging,
                        'test_parameters' => $l->labService?->test_parameters,
                        'status' => $l->status,
                        'result_values' => $l->result_values,
                        'result_notes' => $l->result_notes,
                        'ordered_at' => $l->ordered_at?->format('Y-m-d H:i'),
                        'result_entered_at' => $l->result_entered_at?->format('Y-m-d H:i'),
                    ]),
                    'procedures' => $consultation->procedures->map(fn ($p) => [
                        'name' => $p->procedureType?->name,
                        'code' => $p->procedureType?->code,
                        'notes' => $p->notes,
                    ]),
                ];
            });

        // Get admissions history
        $admissions = $patient->admissions()
            ->with([
                'ward:id,name',
                'bed:id,bed_number',
                'consultation.doctor:id,name',
                'diagnoses',
                'wardRounds' => fn ($q) => $q->with([
                    'doctor:id,name',
                    'prescriptions.drug:id,name,generic_name,form,strength',
                    'labOrders.labService:id,name,code,is_imaging,test_parameters',
                    'procedures.procedureType:id,name,code',
                    'diagnoses',
                ])->orderByDesc('round_datetime'),
            ])
            ->orderByDesc('admitted_at')
            ->limit(20)
            ->get()
            ->map(fn ($a) => [
                'id' => $a->id,
                'admission_number' => $a->admission_number,
                'admitted_at' => $a->admitted_at?->format('Y-m-d H:i'),
                'discharged_at' => $a->discharged_at?->format('Y-m-d H:i'),
                'status' => $a->status,
                'ward' => $a->ward?->name,
                'bed' => $a->bed?->bed_number,
                'admission_reason' => $a->admission_reason,
                'discharge_notes' => $a->discharge_notes,
                'admitting_doctor' => $a->consultation?->doctor?->name,
                'diagnoses' => $a->diagnoses->map(fn ($d) => [
                    'type' => $d->diagnosis_type,
                    'code' => $d->icd_code,
                    'description' => $d->diagnosis_name,
                    'is_active' => $d->is_active,
                ]),
                'ward_rounds' => $a->wardRounds
                    // Deduplicate: merge near-duplicate rounds per day+doctor+date combo
                    // Keep latest SOAP notes but aggregate prescriptions/labs/procedures from all
                    ->groupBy(fn ($wr) => $wr->day_number.'-'.$wr->doctor_id.'-'.$wr->round_datetime?->format('Y-m-d'))
                    ->map(function ($group) {
                        $latest = $group->sortByDesc('id')->first();
                        $allPrescriptions = $group->flatMap(fn ($wr) => $wr->prescriptions)->unique('id');
                        $allLabOrders = $group->flatMap(fn ($wr) => $wr->labOrders)->unique('id');
                        $allProcedures = $group->flatMap(fn ($wr) => $wr->procedures)->unique('id');

                        return [
                            'id' => $latest->id,
                            'date' => $latest->round_datetime?->format('Y-m-d H:i'),
                            'doctor' => $latest->doctor?->name,
                            'day_number' => $latest->day_number,
                            'round_type' => $latest->round_type,
                            'presenting_complaint' => $latest->presenting_complaint,
                            'examination_findings' => $latest->examination_findings,
                            'assessment_notes' => $latest->assessment_notes,
                            'plan_notes' => $latest->plan_notes,
                            'patient_status' => $latest->patient_status,
                            'prescriptions' => $allPrescriptions->values()->map(fn ($p) => [
                                'drug_name' => $p->drug?->name ?? $p->medication_name,
                                'generic_name' => $p->drug?->generic_name,
                                'form' => $p->drug?->form ?? $p->dosage_form,
                                'strength' => $p->drug?->strength,
                                'dose_quantity' => $p->dose_quantity,
                                'frequency' => $p->frequency,
                                'duration' => $p->duration,
                                'quantity' => $p->quantity,
                                'instructions' => $p->instructions,
                                'status' => $p->status,
                            ]),
                            'lab_orders' => $allLabOrders->values()->map(fn ($l) => [
                                'id' => $l->id,
                                'service_name' => $l->labService?->name,
                                'code' => $l->labService?->code,
                                'is_imaging' => $l->labService?->is_imaging,
                                'status' => $l->status,
                                'result_values' => $l->result_values,
                                'result_notes' => $l->result_notes,
                                'ordered_at' => $l->ordered_at?->format('Y-m-d H:i'),
                                'result_entered_at' => $l->result_entered_at?->format('Y-m-d H:i'),
                            ]),
                            'procedures' => $allProcedures->values()->map(fn ($p) => [
                                'name' => $p->procedureType?->name,
                                'code' => $p->procedureType?->code,
                                'notes' => $p->comments,
                            ]),
                        ];
                    })
                    ->values(),
            ]);

        return response()->json([
            'patient_name' => $patient->full_name ?? ($patient->first_name.' '.$patient->last_name),
            'background_history' => [
                'past_medical_surgical_history' => $patient->past_medical_surgical_history,
                'drug_history' => $patient->drug_history,
                'family_history' => $patient->family_history,
                'social_history' => $patient->social_history,
            ],
            'medical_history' => [
                'consultations' => $consultations,
                'admissions' => $admissions,
                'minor_procedures' => $this->getMinorProcedures($patient),
            ],
        ]);
    }

    /**
     * Vet (approve or reject) a claim.
     * For NHIS claims, G-DRG selection is required for approval.
     */
    public function vet(VetClaimRequest $request, InsuranceClaim $claim): RedirectResponse
    {
        $this->authorize('vetClaim', $claim);

        $validated = $request->validated();

        // Handle rejection
        if ($validated['action'] === 'reject') {
            $claim->status = 'rejected';
            $claim->rejection_reason = $validated['rejection_reason'];
            $claim->vetted_by = auth()->id();
            $claim->vetted_at = now();
            $claim->save();

            return redirect()->back()
                ->with('success', 'Claim has been rejected.');
        }

        // Handle approval using ClaimVettingService
        try {
            // Update attendance details if provided
            $attendanceFields = ['type_of_attendance', 'type_of_service', 'specialty_attended', 'attending_prescriber', 'date_of_attendance', 'date_of_discharge'];
            foreach ($attendanceFields as $field) {
                if (isset($validated[$field])) {
                    // Map NHIS codes back to enum values for type_of_service
                    if ($field === 'type_of_service') {
                        $claim->$field = match (strtoupper($validated[$field])) {
                            'IPD' => 'inpatient',
                            'OPD' => 'outpatient',
                            default => $validated[$field],
                        };
                    } else {
                        $claim->$field = $validated[$field];
                    }
                }
            }
            $claim->save();

            $this->claimVettingService->vetClaim(
                $claim,
                auth()->user(),
                $validated['gdrg_tariff_id'] ?? null,
                $validated['diagnoses'] ?? null
            );

            // Refresh claim to get updated values from vetClaim
            $claim->refresh();

            // Process item-level approvals if provided
            if (! empty($validated['items'])) {
                $approvedAmount = 0;

                foreach ($validated['items'] as $itemData) {
                    $item = $claim->items()->find($itemData['id']);
                    if ($item) {
                        $item->is_approved = $itemData['is_approved'];
                        $item->rejection_reason = $itemData['is_approved'] ? null : ($itemData['rejection_reason'] ?? null);
                        $item->save();

                        // Sum up approved items
                        if ($itemData['is_approved']) {
                            $approvedAmount += (float) $item->insurance_pays;
                        }
                    }
                }

                // Update claim amounts based on approved items
                // Copay = total claim amount - approved amount (what insurance pays)
                $totalClaimAmount = (float) $claim->total_claim_amount;
                $claim->approved_amount = number_format($approvedAmount, 2, '.', '');
                $claim->insurance_covered_amount = number_format($approvedAmount, 2, '.', '');
                $claim->patient_copay_amount = number_format($totalClaimAmount - $approvedAmount, 2, '.', '');
                $claim->save();
            }

            return redirect()->back()
                ->with('success', 'Claim has been vetted and approved.');
        } catch (\InvalidArgumentException $e) {
            return redirect()->back()
                ->withErrors(['gdrg_tariff_id' => $e->getMessage()]);
        }
    }

    /**
     * Update diagnoses on a claim without affecting the original consultation.
     * This allows claims officers to add/remove diagnoses specific to the claim.
     */
    public function updateDiagnoses(Request $request, InsuranceClaim $claim): JsonResponse
    {
        $this->authorize('vetClaim', $claim);

        $validated = $request->validate([
            'diagnoses' => ['required', 'array'],
            'diagnoses.*.diagnosis_id' => ['required', 'integer', 'exists:diagnoses,id'],
            'diagnoses.*.is_primary' => ['nullable', 'boolean'],
        ]);

        DB::beginTransaction();

        try {
            // Remove existing claim diagnoses
            $claim->claimDiagnoses()->delete();

            // Add new diagnoses to the claim only (not the consultation)
            foreach ($validated['diagnoses'] as $diagnosisData) {
                $claim->claimDiagnoses()->create([
                    'diagnosis_id' => $diagnosisData['diagnosis_id'],
                    'is_primary' => $diagnosisData['is_primary'] ?? false,
                ]);
            }

            DB::commit();

            // Reload diagnoses with their relationships
            $claim->load('claimDiagnoses.diagnosis');

            return response()->json([
                'success' => true,
                'message' => 'Claim diagnoses updated successfully.',
                'diagnoses' => $claim->claimDiagnoses->map(fn ($cd) => [
                    'id' => $cd->id,
                    'diagnosis_id' => $cd->diagnosis_id,
                    'name' => $cd->diagnosis->name ?? $cd->diagnosis->diagnosis ?? '',
                    'icd_code' => $cd->diagnosis->icd_code ?? $cd->diagnosis->code ?? '',
                    'is_primary' => $cd->is_primary,
                ]),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to update diagnoses: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Add an item to a claim from NHIS or GDRG tariff.
     * This creates a claim-only item that doesn't affect the original consultation.
     */
    public function addItem(Request $request, InsuranceClaim $claim): JsonResponse
    {
        $this->authorize('vetClaim', $claim);

        $validated = $request->validate([
            'nhis_tariff_id' => ['required_without:gdrg_tariff_id', 'nullable', 'integer', 'exists:nhis_tariffs,id'],
            'gdrg_tariff_id' => ['required_without:nhis_tariff_id', 'nullable', 'integer', 'exists:gdrg_tariffs,id'],
            'quantity' => ['required', 'integer', 'min:1'],
        ]);

        DB::beginTransaction();

        try {
            // Determine if using NHIS or GDRG tariff
            $isGdrg = ! empty($validated['gdrg_tariff_id']);

            if ($isGdrg) {
                $gdrgTariff = \App\Models\GdrgTariff::findOrFail($validated['gdrg_tariff_id']);

                // Map GDRG category to claim item type
                $itemType = match (strtoupper($gdrgTariff->mdc_category)) {
                    'INVESTIGATION' => 'lab',
                    'ADULT SURGERY', 'PAEDIATRIC SURGERY', 'SURGERY' => 'procedure',
                    default => 'procedure',
                };

                $code = $gdrgTariff->code;
                $name = $gdrgTariff->name;
                $price = $gdrgTariff->tariff_price;
                $nhisTariffId = null;
            } else {
                $nhisTariff = NhisTariff::findOrFail($validated['nhis_tariff_id']);

                // Map NHIS category to claim item type
                $itemType = match ($nhisTariff->category) {
                    'drug', 'drugs', 'medicine' => 'drug',
                    'lab', 'lab_service', 'investigation' => 'lab',
                    'procedure', 'procedures' => 'procedure',
                    'consumable', 'consumables' => 'consumable',
                    default => 'drug',
                };

                $code = $nhisTariff->nhis_code;
                $name = $nhisTariff->name;
                $price = $nhisTariff->price;
                $nhisTariffId = $nhisTariff->id;
            }

            // Create claim item without charge_id (manual item)
            $item = InsuranceClaimItem::create([
                'insurance_claim_id' => $claim->id,
                'charge_id' => null, // No charge - this is a claim-only item
                'item_date' => now(),
                'item_type' => $itemType,
                'code' => $code,
                'description' => $name,
                'quantity' => $validated['quantity'],
                'unit_tariff' => $price,
                'subtotal' => $price * $validated['quantity'],
                'is_covered' => true,
                'coverage_percentage' => 100,
                'insurance_pays' => $price * $validated['quantity'],
                'patient_pays' => 0,
                'is_approved' => null,
                'nhis_tariff_id' => $nhisTariffId,
                'nhis_code' => $code,
                'nhis_price' => $price,
            ]);

            // Recalculate claim totals
            $this->recalculateClaimTotals($claim);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Item added to claim successfully.',
                'item' => [
                    'id' => $item->id,
                    'name' => $item->description,
                    'code' => $item->code,
                    'nhis_code' => $item->nhis_code,
                    'quantity' => $item->quantity,
                    'unit_price' => (float) $item->unit_tariff,
                    'nhis_price' => (float) $item->nhis_price,
                    'subtotal' => (float) $item->subtotal,
                    'is_covered' => true,
                ],
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to add item: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update a claim item's quantity and/or frequency.
     */
    public function updateItem(Request $request, InsuranceClaim $claim, InsuranceClaimItem $item): JsonResponse
    {
        $this->authorize('vetClaim', $claim);

        if ($item->insurance_claim_id !== $claim->id) {
            return response()->json([
                'success' => false,
                'message' => 'Item does not belong to this claim.',
            ], 403);
        }

        $validated = $request->validate([
            'quantity' => ['sometimes', 'required', 'integer', 'min:1'],
            'frequency' => ['sometimes', 'nullable', 'string', 'max:100'],
            'dose' => ['sometimes', 'nullable', 'string', 'max:100'],
            'duration' => ['sometimes', 'nullable', 'string', 'max:100'],
            'item_date' => ['sometimes', 'required', 'date'],
        ]);

        DB::beginTransaction();

        try {
            if (isset($validated['quantity'])) {
                $item->quantity = $validated['quantity'];
                $unitPrice = $item->nhis_price ?? $item->unit_tariff;
                $item->subtotal = $unitPrice * $item->quantity;
                $item->insurance_pays = $item->is_covered ? $item->subtotal : 0;
                $item->patient_pays = $item->is_covered ? 0 : $item->subtotal;
            }

            if (array_key_exists('frequency', $validated)) {
                $item->frequency = $validated['frequency'];
            }

            if (array_key_exists('dose', $validated)) {
                $item->dose = $validated['dose'];
            }

            if (array_key_exists('duration', $validated)) {
                $item->duration = $validated['duration'];
            }

            if (isset($validated['item_date'])) {
                $item->item_date = $validated['item_date'];
            }

            $item->save();

            $this->recalculateClaimTotals($claim);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Item updated successfully.',
                'item' => [
                    'id' => $item->id,
                    'quantity' => $item->quantity,
                    'frequency' => $item->frequency,
                    'item_date' => $item->item_date?->format('Y-m-d'),
                    'subtotal' => (float) $item->subtotal,
                    'insurance_pays' => (float) $item->insurance_pays,
                ],
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to update item: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove an item from a claim.
     * This only removes from the claim, doesn't affect the original consultation.
     */
    public function removeItem(InsuranceClaim $claim, InsuranceClaimItem $item): JsonResponse
    {
        $this->authorize('vetClaim', $claim);

        // Verify item belongs to this claim
        if ($item->insurance_claim_id !== $claim->id) {
            return response()->json([
                'success' => false,
                'message' => 'Item does not belong to this claim.',
            ], 403);
        }

        DB::beginTransaction();

        try {
            $item->delete();

            // Recalculate claim totals
            $this->recalculateClaimTotals($claim);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Item removed from claim successfully.',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to remove item: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Recalculate claim totals after adding/removing items.
     */
    protected function recalculateClaimTotals(InsuranceClaim $claim): void
    {
        $claim->load('items', 'gdrgTariff');

        $isNhis = $claim->isNhisClaim();
        $gdrgAmount = (float) ($claim->gdrgTariff?->tariff_price ?? $claim->gdrg_amount ?? 0);

        if ($isNhis) {
            // For NHIS, sum only items with NHIS prices
            $itemsTotal = $claim->items
                ->whereNotNull('nhis_price')
                ->sum(fn ($item) => (float) $item->nhis_price * (int) $item->quantity);
        } else {
            $itemsTotal = $claim->items->sum('subtotal');
        }

        $claim->total_claim_amount = $gdrgAmount + $itemsTotal;
        $claim->insurance_covered_amount = $gdrgAmount + $itemsTotal;
        $claim->save();

        // Auto-refresh batch items in draft batches
        $this->refreshClaimBatchItems($claim);
    }

    /**
     * Refresh batch item amounts for a claim in any draft batches.
     */
    protected function refreshClaimBatchItems(InsuranceClaim $claim): void
    {
        // Update batch items in draft batches only
        $claim->batchItems()
            ->whereHas('batch', fn ($q) => $q->where('status', 'draft'))
            ->each(function ($batchItem) use ($claim) {
                $batchItem->claim_amount = $claim->total_claim_amount ?? 0;
                $batchItem->save();

                // Update the batch totals
                $batch = $batchItem->batch;
                $batch->total_amount = $batch->batchItems()->sum('claim_amount');
                $batch->save();
            });
    }

    public function submit(SubmitInsuranceClaimRequest $request): RedirectResponse
    {
        $claimIds = $request->getClaimIds();
        $isBatch = $request->isBatchSubmission();

        $submittedCount = 0;
        $errors = [];

        DB::beginTransaction();

        try {
            $submissionDate = $request->input('submission_date', now()->toDateString());
            $batchReference = $isBatch ? ($request->input('batch_reference') ?: 'BATCH-'.now()->format('YmdHis')) : null;

            foreach ($claimIds as $claimId) {
                $claim = InsuranceClaim::findOrFail($claimId);

                // Check authorization
                if (! auth()->user()->can('submitClaim', $claim)) {
                    $errors[] = "Not authorized to submit claim {$claim->claim_check_code}";

                    continue;
                }

                // Update claim status and tracking
                $claim->status = 'submitted';
                $claim->submitted_by = auth()->id();
                $claim->submitted_at = now();
                $claim->submission_date = $submissionDate;

                if ($isBatch) {
                    $claim->batch_reference = $batchReference;
                    $claim->batch_submitted_at = now();
                }

                if ($request->filled('notes')) {
                    $claim->notes = $request->input('notes');
                }

                $claim->save();
                $submittedCount++;
            }

            DB::commit();

            if ($submittedCount === 0) {
                return back()->with('error', 'No claims were submitted. '.implode(', ', $errors));
            }

            $message = $isBatch
                ? "{$submittedCount} claim(s) submitted successfully in batch {$batchReference}."
                : 'Claim submitted successfully.';

            if (! empty($errors)) {
                $message .= ' Some claims failed: '.implode(', ', $errors);
            }

            return back()->with('success', $message);
        } catch (\Exception $e) {
            DB::rollBack();

            return back()->with('error', 'Failed to submit claims: '.$e->getMessage());
        }
    }

    public function markAsPaid(RecordInsurancePaymentRequest $request, InsuranceClaim $claim): RedirectResponse
    {
        $this->authorize('recordPayment', $claim);

        DB::beginTransaction();

        try {
            $claim->status = 'paid';
            $claim->payment_date = $request->input('payment_date');
            $claim->payment_amount = $request->input('payment_amount');
            $claim->payment_reference = $request->input('payment_reference');
            $claim->payment_recorded_by = auth()->id();

            if ($request->filled('approval_date')) {
                $claim->approval_date = $request->input('approval_date');
                $claim->approved_by = auth()->id();
            }

            if ($request->filled('notes')) {
                $claim->notes = $request->input('notes');
            }

            $claim->save();

            DB::commit();

            return redirect()->route('admin.insurance.claims.index')
                ->with('success', 'Payment recorded successfully.');
        } catch (\Exception $e) {
            DB::rollBack();

            return back()->with('error', 'Failed to record payment: '.$e->getMessage());
        }
    }

    public function markAsRejected(RejectInsuranceClaimRequest $request, InsuranceClaim $claim): RedirectResponse
    {
        $this->authorize('rejectClaim', $claim);

        DB::beginTransaction();

        try {
            $claim->status = 'rejected';
            $claim->rejection_reason = $request->input('rejection_reason');
            $claim->rejected_by = auth()->id();
            $claim->rejected_at = $request->input('rejection_date', now());

            $claim->save();

            DB::commit();

            return redirect()->route('admin.insurance.claims.index')
                ->with('success', 'Claim marked as rejected.');
        } catch (\Exception $e) {
            DB::rollBack();

            return back()->with('error', 'Failed to reject claim: '.$e->getMessage());
        }
    }

    public function resubmit(SubmitInsuranceClaimRequest $request, InsuranceClaim $claim): RedirectResponse
    {
        $this->authorize('resubmitClaim', $claim);

        DB::beginTransaction();

        try {
            $claim->status = 'submitted';
            $claim->submitted_by = auth()->id();
            $claim->submitted_at = now();
            $claim->submission_date = $request->input('submission_date', now()->toDateString());
            $claim->resubmission_count = ($claim->resubmission_count ?? 0) + 1;
            $claim->last_resubmitted_at = now();

            // Clear rejection data
            $claim->rejection_reason = null;
            $claim->rejected_by = null;
            $claim->rejected_at = null;

            if ($request->filled('notes')) {
                $claim->notes = $request->input('notes');
            }

            $claim->save();

            DB::commit();

            return redirect()->route('admin.insurance.claims.index')
                ->with('success', 'Claim resubmitted successfully.');
        } catch (\Exception $e) {
            DB::rollBack();

            return back()->with('error', 'Failed to resubmit claim: '.$e->getMessage());
        }
    }

    /**
     * Update a rejected claim to allow correction before resubmission.
     * Only rejected claims can be edited.
     *
     * _Requirements: 17.5_
     */
    public function update(Request $request, InsuranceClaim $claim): RedirectResponse
    {
        $this->authorize('update', $claim);

        $validated = $request->validate([
            'gdrg_tariff_id' => ['nullable', 'integer', 'exists:gdrg_tariffs,id'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        DB::beginTransaction();

        try {
            // Update G-DRG if provided
            if (isset($validated['gdrg_tariff_id'])) {
                $gdrgTariff = \App\Models\GdrgTariff::find($validated['gdrg_tariff_id']);
                $claim->gdrg_tariff_id = $gdrgTariff?->id;
                $claim->gdrg_amount = $gdrgTariff?->tariff_price;

                // Recalculate total claim amount
                $itemsTotal = $claim->items()->sum('insurance_pays');
                $claim->total_claim_amount = ($gdrgTariff?->tariff_price ?? 0) + $itemsTotal;
            }

            if (isset($validated['notes'])) {
                $claim->notes = $validated['notes'];
            }

            $claim->save();

            DB::commit();

            return redirect()->route('admin.insurance.claims.index')
                ->with('success', 'Claim updated successfully.');
        } catch (\Exception $e) {
            DB::rollBack();

            return back()->with('error', 'Failed to update claim: '.$e->getMessage());
        }
    }

    /**
     * Prepare a rejected claim for resubmission by resetting its status to vetted.
     * This allows the claim to be added to a new batch.
     *
     * _Requirements: 17.5_
     */
    public function prepareForResubmission(InsuranceClaim $claim): RedirectResponse
    {
        $this->authorize('resubmitClaim', $claim);

        if ($claim->status !== 'rejected') {
            return back()->with('error', 'Only rejected claims can be prepared for resubmission.');
        }

        DB::beginTransaction();

        try {
            // Reset claim status to vetted so it can be added to a new batch
            $claim->status = 'vetted';
            $claim->resubmission_count = ($claim->resubmission_count ?? 0) + 1;
            $claim->last_resubmitted_at = now();

            // Clear rejection data but keep the reason in notes for reference
            $previousRejectionReason = $claim->rejection_reason;
            if ($previousRejectionReason) {
                $claim->notes = ($claim->notes ? $claim->notes."\n\n" : '')
                    .'Previous rejection reason: '.$previousRejectionReason;
            }
            $claim->rejection_reason = null;
            $claim->rejected_by = null;
            $claim->rejected_at = null;

            // Clear submission data
            $claim->submitted_by = null;
            $claim->submitted_at = null;
            $claim->submission_date = null;
            $claim->batch_reference = null;
            $claim->batch_submitted_at = null;

            $claim->save();

            DB::commit();

            return redirect()->route('admin.insurance.claims.index')
                ->with('success', 'Claim has been prepared for resubmission. You can now add it to a new batch.');
        } catch (\Exception $e) {
            DB::rollBack();

            return back()->with('error', 'Failed to prepare claim for resubmission: '.$e->getMessage());
        }
    }

    public function export(Request $request)
    {
        $this->authorize('exportClaims', InsuranceClaim::class);

        $format = $request->input('format', 'excel'); // excel or pdf
        $status = $request->input('status');
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');

        $query = InsuranceClaim::query()
            ->with(['patientInsurance.plan.provider', 'items']);

        if ($status) {
            $query->where('status', $status);
        }

        if ($dateFrom) {
            $query->where('date_of_attendance', '>=', $dateFrom);
        }

        if ($dateTo) {
            $query->where('date_of_attendance', '<=', $dateTo);
        }

        $claims = $query->get();

        if ($format === 'pdf') {
            return $this->exportToPdf($claims);
        }

        return $this->exportToExcel($claims);
    }

    private function exportToExcel($claims)
    {
        $filename = 'insurance-claims-'.now()->format('Y-m-d-His').'.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = function () use ($claims) {
            $file = fopen('php://output', 'w');

            // Add CSV headers
            fputcsv($file, [
                'Claim Code',
                'Patient Name',
                'Membership ID',
                'Provider',
                'Plan',
                'Date of Attendance',
                'Type of Service',
                'Status',
                'Total Claim Amount',
                'Approved Amount',
                'Patient Copay',
                'Insurance Covered',
                'Submission Date',
                'Payment Date',
                'Payment Reference',
            ]);

            // Add data rows
            foreach ($claims as $claim) {
                fputcsv($file, [
                    $claim->claim_check_code,
                    $claim->patient_surname.' '.$claim->patient_other_names,
                    $claim->membership_id,
                    $claim->patientInsurance->plan->provider->name ?? '',
                    $claim->patientInsurance->plan->name ?? '',
                    $claim->date_of_attendance,
                    ucfirst($claim->type_of_service),
                    ucfirst($claim->status),
                    number_format($claim->total_claim_amount, 2),
                    number_format($claim->approved_amount, 2),
                    number_format($claim->patient_copay_amount, 2),
                    number_format($claim->insurance_covered_amount, 2),
                    $claim->submission_date ?? '',
                    $claim->payment_date ?? '',
                    $claim->payment_reference ?? '',
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    private function exportToPdf($claims)
    {
        // For now, return a simple HTML view that can be printed as PDF
        return response()->view('exports.insurance-claims', [
            'claims' => $claims,
            'exportDate' => now(),
        ])->header('Content-Type', 'text/html');
    }

    /**
     * Soft delete a claim.
     * Only draft or pending_vetting claims can be deleted.
     */
    public function destroy(InsuranceClaim $claim): RedirectResponse
    {
        $this->authorize('delete', $claim);

        $claim->delete();

        return redirect()->back()
            ->with('success', 'Claim deleted successfully.');
    }

    private function getMinorProcedures(Patient $patient): \Illuminate\Support\Collection
    {
        return \App\Models\MinorProcedure::with([
            'patientCheckin:id,department_id,checked_in_at',
            'patientCheckin.department:id,name',
            'patientCheckin.vitalSigns' => fn ($q) => $q->with('recordedBy:id,name')->orderByDesc('recorded_at'),
            'nurse:id,name',
            'procedureType:id,name,code',
            'diagnoses:id,code,diagnosis',
            'supplies.drug:id,name,generic_name,form,strength',
        ])
            ->whereHas('patientCheckin', fn ($q) => $q->where('patient_id', $patient->id))
            ->where('status', 'completed')
            ->orderByDesc('performed_at')
            ->limit(50)
            ->get()
            ->map(function ($mp) {
                $vitals = $mp->patientCheckin?->vitalSigns?->first();

                return [
                    'id' => $mp->id,
                    'date' => $mp->patientCheckin?->checked_in_at?->format('Y-m-d H:i'),
                    'nurse' => $mp->nurse?->name,
                    'department' => $mp->patientCheckin?->department?->name,
                    'procedure_name' => $mp->procedureType?->name,
                    'procedure_code' => $mp->procedureType?->code,
                    'procedure_notes' => $mp->procedure_notes,
                    'vitals' => $vitals ? [
                        'blood_pressure' => $vitals->blood_pressure,
                        'temperature' => $vitals->temperature,
                        'pulse_rate' => $vitals->pulse_rate,
                        'respiratory_rate' => $vitals->respiratory_rate,
                        'oxygen_saturation' => $vitals->oxygen_saturation,
                        'weight' => $vitals->weight,
                        'height' => $vitals->height,
                        'bmi' => $vitals->bmi,
                    ] : null,
                    'diagnoses' => $mp->diagnoses->map(fn ($d) => [
                        'code' => $d->code,
                        'description' => $d->diagnosis,
                    ]),
                    'supplies' => $mp->supplies->map(fn ($s) => [
                        'drug_name' => $s->drug?->name,
                        'quantity' => $s->quantity_dispensed ?? $s->quantity_requested,
                        'status' => $s->status,
                    ]),
                ];
            });
    }
}
