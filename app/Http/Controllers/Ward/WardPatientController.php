<?php

namespace App\Http\Controllers\Ward;

use App\Http\Controllers\Controller;
use App\Models\Bed;
use App\Models\PatientAdmission;
use App\Models\VitalsSchedule;
use App\Models\Ward as WardModel;
use App\Services\VitalsScheduleService;
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
                ]);
            },
            'vitalSigns' => function ($query) {
                $query->latest()->with('recordedBy:id,name');
            },
            'medicationAdministrations' => function ($query) {
                $query->with(['prescription.drug', 'administeredBy:id,name'])
                    ->orderBy('scheduled_time', 'asc');
            },
            'nursingNotes' => function ($query) {
                $query->with('nurse:id,name')->latest();
            },
            'wardRounds' => function ($query) {
                $query->with([
                    'doctor:id,name',
                    'diagnoses',
                    'prescriptions.drug',
                    'labOrders.labService',
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

        return Inertia::render('Ward/PatientShow', [
            'admission' => $admission,
            'availableBeds' => $availableBeds,
            'allBeds' => $allBeds,
            'hasAvailableBeds' => $availableBeds->isNotEmpty(),
            'vitalsScheduleStatus' => $vitalsScheduleStatus,
            'scheduleHistory' => $scheduleHistory,
        ]);
    }
}
