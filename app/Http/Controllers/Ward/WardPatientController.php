<?php

namespace App\Http\Controllers\Ward;

use App\Http\Controllers\Controller;
use App\Models\Bed;
use App\Models\DeliveryRecord;
use App\Models\PatientAdmission;
use App\Models\VitalsSchedule;
use App\Models\Ward as WardModel;
use App\Services\VitalsScheduleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class WardPatientController extends Controller
{
    public function show(Request $request, WardModel $ward, PatientAdmission $admission, VitalsScheduleService $scheduleService)
    {
        // Ensure the admission belongs to this ward
        if ($admission->ward_id !== $ward->id && $admission->bed?->ward_id !== $ward->id) {
            abort(404);
        }

        // Load admission with all required relationships for PatientShow
        $admission->load([
            'patient.activeInsurance.plan.provider',
            'bed',
            'ward',
            'consultation' => function ($query) {
                $query->with([
                    'doctor:id,name',
                    'patientCheckin.vitalSigns' => function ($q) {
                        $q->latest()->with('recordedBy:id,name')->limit(1);
                    },
                    'prescriptions.drug',
                    'labOrders.labService:id,name,code,price,test_parameters',
                    'diagnoses.diagnosis', // Include consultation diagnoses
                ]);
            },
            'vitalSigns' => function ($query) {
                $query->latest()->with('recordedBy:id,name');
            },
            'medicationAdministrations' => function ($query) {
                $query->with(['prescription.drug', 'administeredBy:id,name'])
                    ->orderBy('administered_at', 'desc');
            },
            'nursingNotes' => function ($query) {
                $query->with('nurse:id,name')->latest();
            },
            'wardRounds' => function ($query) {
                $query->with([
                    'doctor:id,name',
                    'diagnoses',
                    'prescriptions.drug',
                    'labOrders.labService:id,name,code,price,test_parameters',
                    'procedures.procedureType',
                    'procedures.doctor:id,name',
                ])
                    ->orderBy('round_datetime', 'desc')
                    ->orderBy('id', 'desc');
            },
            'activeVitalsSchedule.createdBy:id,name',
        ]);

        // Get available beds for bed assignment modal
        $availableBeds = Bed::query()
            ->where('ward_id', $ward->id)
            ->available()
            ->get();

        $allBeds = Bed::query()
            ->where('ward_id', $ward->id)
            ->where('is_active', true)
            ->with('currentAdmission.patient:id,first_name,last_name')
            ->orderBy('bed_number')
            ->get();

        // Get vitals schedule status if active schedule exists
        $vitalsScheduleStatus = null;
        if ($admission->activeVitalsSchedule) {
            $vitalsScheduleStatus = $scheduleService->getScheduleStatus($admission->activeVitalsSchedule);
        }

        // Get schedule history (all schedules for this admission)
        $allSchedules = VitalsSchedule::where('patient_admission_id', $admission->id)
            ->with('createdBy:id,name')
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        // Format schedule history
        $scheduleHistory = $allSchedules->map(function ($schedule) use ($scheduleService) {
            return array_merge(
                $schedule->only(['id', 'interval_minutes', 'is_active', 'created_at']),
                [
                    'created_by' => $schedule->createdBy?->only(['id', 'name']),
                    'status' => $scheduleService->getScheduleStatus($schedule),
                ]
            );
        });

        // Check if this is a maternity ward admission
        $isMaternityWard = $ward->code === 'MATERNITY-WARD';

        // Load delivery records if maternity ward
        $deliveryRecords = [];
        if ($isMaternityWard) {
            $deliveryRecords = $admission->deliveryRecords()
                ->with(['recordedBy:id,name', 'lastEditedBy:id,name'])
                ->orderBy('delivery_date', 'desc')
                ->get()
                ->map(function ($record) {
                    return array_merge($record->toArray(), [
                        'delivery_mode_label' => $record->delivery_mode_label,
                    ]);
                });
        }

        return Inertia::render('Ward/PatientShow', [
            'admission' => $admission,
            'availableBeds' => $availableBeds,
            'allBeds' => $allBeds,
            'hasAvailableBeds' => $availableBeds->isNotEmpty(),
            'vitalsScheduleStatus' => $vitalsScheduleStatus,
            'scheduleHistory' => $scheduleHistory,
            'can_edit_vitals_timestamp' => $request->user()->can('vitals.edit-timestamp'),
            'can_edit_medication_timestamp' => $request->user()->can('medications.edit-timestamp'),
            'can_delete_medication_administration' => $request->user()->can('medications.delete'),
            'can_delete_old_medication_administration' => $request->user()->can('medications.delete-old'),
            'can_transfer' => $request->user()->can('admissions.transfer'),
            'can_create_ward_round' => $request->user()->can('ward_rounds.create'),
            'can_update_ward_round' => $request->user()->can('ward_rounds.update'),
            'can_view_ward_rounds' => $request->user()->can('ward_rounds.view'),
            'availableWards' => WardModel::active()
                ->where('id', '!=', $ward->id)
                ->get(['id', 'name', 'code', 'available_beds']),
            'is_maternity_ward' => $isMaternityWard,
            'delivery_records' => $deliveryRecords,
            'delivery_modes' => DeliveryRecord::DELIVERY_MODES,
        ]);
    }

    /**
     * Discharge a patient from the ward.
     */
    public function discharge(Request $request, WardModel $ward, PatientAdmission $admission): RedirectResponse
    {
        // Check permission
        if (! $request->user()->can('admissions.discharge')) {
            abort(403, 'You do not have permission to discharge patients.');
        }

        // Ensure the admission belongs to this ward
        if ($admission->ward_id !== $ward->id && $admission->bed?->ward_id !== $ward->id) {
            abort(404);
        }

        // Ensure patient is still admitted
        if ($admission->status !== 'admitted') {
            return redirect()->back()->withErrors(['admission' => 'Patient is not currently admitted.']);
        }

        $request->validate([
            'discharge_notes' => 'nullable|string|max:2000',
        ]);

        try {
            $admission->markAsDischarged($request->user(), $request->discharge_notes);

            return redirect()->route('wards.show', $ward)
                ->with('success', "Patient {$admission->patient->first_name} {$admission->patient->last_name} has been discharged successfully.");
        } catch (\RuntimeException $e) {
            // This catches the unpaid copays exception
            return redirect()->back()->withErrors(['discharge' => $e->getMessage()]);
        }
    }
}
