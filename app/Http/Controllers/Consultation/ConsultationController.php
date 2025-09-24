<?php

namespace App\Http\Controllers\Consultation;

use App\Http\Controllers\Controller;
use App\Models\BillingService;
use App\Models\Consultation;
use App\Models\LabService;
use App\Models\PatientCheckin;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ConsultationController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        // Check if user can view consultations
        $this->authorize('viewAny', PatientCheckin::class);

        // Get patient check-ins awaiting consultation (accessible to user)
        $awaitingConsultation = PatientCheckin::with([
            'patient:id,first_name,last_name,date_of_birth,phone_number',
            'department:id,name',
            'vitalSigns' => function ($query) {
                $query->latest()->limit(1);
            },
        ])
            ->accessibleTo($user)
            ->whereIn('status', ['checked_in', 'vitals_taken', 'awaiting_consultation'])
            ->today()
            ->orderBy('checked_in_at')
            ->get();

        // Get active consultations (accessible to user)
        $activeConsultations = Consultation::with([
            'patientCheckin.patient:id,first_name,last_name',
            'patientCheckin.department:id,name',
        ])
            ->accessibleTo($user)
            ->inProgress()
            ->today()
            ->orderBy('started_at')
            ->get();

        return Inertia::render('Consultation/Index', [
            'awaitingConsultation' => $awaitingConsultation,
            'activeConsultations' => $activeConsultations,
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
            'diagnoses',
            'prescriptions',
            'labOrders.labService',
        ]);

        return Inertia::render('Consultation/Show', [
            'consultation' => $consultation,
            'labServices' => LabService::active()->get(['id', 'name', 'code', 'category', 'price', 'sample_type']),
        ]);
    }

    public function store(Request $request)
    {
        $this->authorize('create', Consultation::class);

        $request->validate([
            'patient_checkin_id' => 'required|exists:patient_checkins,id',
            'chief_complaint' => 'nullable|string',
        ]);

        $patientCheckin = PatientCheckin::findOrFail($request->patient_checkin_id);

        // Ensure user can access this patient check-in
        $this->authorize('view', $patientCheckin);

        $consultation = Consultation::create([
            'patient_checkin_id' => $patientCheckin->id,
            'doctor_id' => $request->user()->id,
            'started_at' => now(),
            'status' => 'in_progress',
            'chief_complaint' => $request->chief_complaint,
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
            'chief_complaint' => 'nullable|string',
            'subjective_notes' => 'nullable|string',
            'objective_notes' => 'nullable|string',
            'assessment_notes' => 'nullable|string',
            'plan_notes' => 'nullable|string',
            'follow_up_date' => 'nullable|date|after:today',
        ]);

        $consultation->update($request->only([
            'chief_complaint',
            'subjective_notes',
            'objective_notes',
            'assessment_notes',
            'plan_notes',
            'follow_up_date',
        ]));

        return redirect()->back()->with('success', 'Consultation updated successfully.');
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
        // Get consultation billing service
        $consultationService = BillingService::where('service_type', 'consultation')
            ->where('service_code', 'CONSULT_GENERAL')
            ->first();

        if (! $consultationService) {
            return; // Skip billing if no service configured
        }

        $billNumber = 'BILL-'.now()->format('Ymd').'-'.str_pad($consultation->id, 4, '0', STR_PAD_LEFT);

        $bill = $consultation->patientCheckin->patient->bills()->create([
            'consultation_id' => $consultation->id,
            'bill_number' => $billNumber,
            'total_amount' => 0,
            'paid_amount' => 0,
            'status' => 'pending',
            'issued_at' => now(),
            'due_date' => now()->addDays(30),
        ]);

        // Add consultation charge
        $bill->billItems()->create([
            'billing_service_id' => $consultationService->id,
            'description' => 'General Consultation',
            'quantity' => 1,
            'unit_price' => $consultationService->base_price,
            'total_price' => $consultationService->base_price,
        ]);

        // Add lab order charges
        foreach ($consultation->labOrders as $labOrder) {
            $labBillingService = BillingService::where('service_type', 'lab_test')
                ->where('service_name', $labOrder->labService->name)
                ->first();

            if ($labBillingService) {
                $bill->billItems()->create([
                    'billing_service_id' => $labBillingService->id,
                    'description' => $labOrder->labService->name,
                    'quantity' => 1,
                    'unit_price' => $labOrder->labService->price,
                    'total_price' => $labOrder->labService->price,
                ]);
            }
        }

        // Calculate total
        $bill->calculateTotal();
    }
}
