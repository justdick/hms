<?php

namespace App\Http\Controllers\Ward;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreVitalsScheduleRequest;
use App\Http\Requests\UpdateVitalsScheduleRequest;
use App\Models\PatientAdmission;
use App\Models\VitalsSchedule;
use App\Models\Ward as WardModel;
use App\Services\VitalsAlertService;
use App\Services\VitalsScheduleService;
use Illuminate\Http\RedirectResponse;

class VitalsScheduleController extends Controller
{
    public function __construct(
        public VitalsScheduleService $scheduleService,
        public VitalsAlertService $alertService
    ) {}

    public function store(
        StoreVitalsScheduleRequest $request,
        WardModel $ward,
        PatientAdmission $admission
    ): RedirectResponse {
        // Ensure the admission belongs to this ward
        if ($admission->ward_id !== $ward->id && $admission->bed?->ward_id !== $ward->id) {
            abort(404);
        }

        // Ensure admission is active
        if ($admission->status !== 'admitted') {
            return redirect()->back()
                ->withErrors(['admission' => 'Cannot create vitals schedule for discharged patient.']);
        }

        // Check if schedule already exists
        $existingSchedule = VitalsSchedule::where('patient_admission_id', $admission->id)
            ->where('is_active', true)
            ->first();

        if ($existingSchedule) {
            return redirect()->back()
                ->withErrors(['schedule' => 'An active vitals schedule already exists for this patient.']);
        }

        $schedule = $this->scheduleService->createSchedule(
            $admission,
            $request->validated('interval_minutes'),
            $request->user()
        );

        return redirect()->back()
            ->with('success', 'Vitals schedule created successfully.');
    }

    public function update(
        UpdateVitalsScheduleRequest $request,
        WardModel $ward,
        PatientAdmission $admission,
        VitalsSchedule $schedule
    ): RedirectResponse {
        // Ensure the admission belongs to this ward
        if ($admission->ward_id !== $ward->id && $admission->bed?->ward_id !== $ward->id) {
            abort(404);
        }

        // Ensure schedule belongs to this admission
        if ($schedule->patient_admission_id !== $admission->id) {
            abort(404);
        }

        $this->scheduleService->updateSchedule(
            $schedule,
            $request->validated('interval_minutes')
        );

        return redirect()->back()
            ->with('success', 'Vitals schedule updated successfully.');
    }

    public function destroy(
        WardModel $ward,
        PatientAdmission $admission,
        VitalsSchedule $schedule
    ): RedirectResponse {
        // Ensure the admission belongs to this ward
        if ($admission->ward_id !== $ward->id && $admission->bed?->ward_id !== $ward->id) {
            abort(404);
        }

        // Ensure schedule belongs to this admission
        if ($schedule->patient_admission_id !== $admission->id) {
            abort(404);
        }

        $this->scheduleService->disableSchedule($schedule);

        return redirect()->back()
            ->with('success', 'Vitals schedule disabled successfully.');
    }
}
