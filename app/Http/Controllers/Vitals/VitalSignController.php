<?php

namespace App\Http\Controllers\Vitals;

use App\Http\Controllers\Controller;
use App\Models\PatientAdmission;
use App\Models\PatientCheckin;
use App\Models\VitalSign;
use Illuminate\Http\Request;

class VitalSignController extends Controller
{
    public function store(Request $request)
    {
        $this->authorize('create', VitalSign::class);

        $validated = $request->validate([
            'patient_checkin_id' => 'required|exists:patient_checkins,id',
            'blood_pressure_systolic' => 'nullable|integer',
            'blood_pressure_diastolic' => 'nullable|integer',
            'temperature' => 'nullable|numeric',
            'pulse_rate' => 'nullable|integer',
            'respiratory_rate' => 'nullable|integer',
            'weight' => 'nullable|integer',
            'height' => 'nullable|numeric',
            'oxygen_saturation' => 'nullable|integer',
            'blood_sugar' => 'nullable|numeric',
            'notes' => 'nullable|string',
        ]);

        $checkin = PatientCheckin::findOrFail($validated['patient_checkin_id']);

        $vitalSign = VitalSign::create([
            'patient_id' => $checkin->patient_id,
            'patient_checkin_id' => $validated['patient_checkin_id'],
            'recorded_by' => auth()->id(),
            'blood_pressure_systolic' => $validated['blood_pressure_systolic'] ?? null,
            'blood_pressure_diastolic' => $validated['blood_pressure_diastolic'] ?? null,
            'temperature' => $validated['temperature'] ?? null,
            'pulse_rate' => $validated['pulse_rate'] ?? null,
            'respiratory_rate' => $validated['respiratory_rate'] ?? null,
            'weight' => $validated['weight'] ?? null,
            'height' => $validated['height'] ?? null,
            'oxygen_saturation' => $validated['oxygen_saturation'] ?? null,
            'blood_sugar' => $validated['blood_sugar'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'recorded_at' => now(),
        ]);

        // Update checkin status
        $checkin->markVitalsTaken();

        $vitalSign->load(['recordedBy']);

        return redirect()->back()->with('success', 'Vital signs recorded successfully');
    }

    public function show(VitalSign $vitalSign)
    {
        $this->authorize('view', $vitalSign);

        $vitalSign->load(['patient', 'patientCheckin', 'recordedBy']);

        return response()->json(['vital_sign' => $vitalSign]);
    }

    public function update(VitalSign $vitalSign, Request $request)
    {
        $this->authorize('update', $vitalSign);

        $validated = $request->validate([
            'blood_pressure_systolic' => 'nullable|integer',
            'blood_pressure_diastolic' => 'nullable|integer',
            'temperature' => 'nullable|numeric',
            'pulse_rate' => 'nullable|integer',
            'respiratory_rate' => 'nullable|integer',
            'weight' => 'nullable|integer',
            'height' => 'nullable|numeric',
            'oxygen_saturation' => 'nullable|integer',
            'blood_sugar' => 'nullable|numeric',
            'notes' => 'nullable|string',
        ]);

        $vitalSign->update($validated);

        return redirect()->back()->with('success', 'Vital signs updated successfully');
    }

    public function patientHistory(int $patientId)
    {
        $this->authorize('viewAny', VitalSign::class);

        $vitalSigns = VitalSign::where('patient_id', $patientId)
            ->with(['recordedBy', 'patientCheckin.department'])
            ->orderBy('recorded_at', 'desc')
            ->limit(10)
            ->get();

        return response()->json(['vital_signs' => $vitalSigns]);
    }

    public function storeForAdmission(Request $request, PatientAdmission $admission)
    {
        $this->authorize('create', VitalSign::class);

        $rules = [
            'temperature' => 'nullable|numeric',
            'blood_pressure_systolic' => 'nullable|integer',
            'blood_pressure_diastolic' => 'nullable|integer',
            'pulse_rate' => 'nullable|integer',
            'respiratory_rate' => 'nullable|integer',
            'oxygen_saturation' => 'nullable|integer',
            'blood_sugar' => 'nullable|numeric',
            'weight' => 'nullable|integer',
            'height' => 'nullable|numeric',
            'notes' => 'nullable|string|max:500',
        ];

        // Only allow recorded_at if user has permission
        if (auth()->user()->can('vitals.edit-timestamp')) {
            $rules['recorded_at'] = 'nullable|date';
        }

        $validated = $request->validate($rules);

        // Determine recorded_at: use provided value if permitted, otherwise now()
        $recordedAt = now();
        if (auth()->user()->can('vitals.edit-timestamp') && ! empty($validated['recorded_at'])) {
            $recordedAt = $validated['recorded_at'];
        }

        $vitalSign = VitalSign::create([
            'patient_id' => $admission->patient_id,
            'patient_admission_id' => $admission->id,
            'patient_checkin_id' => $admission->consultation->patient_checkin_id ?? null,
            'recorded_by' => auth()->id(),
            'blood_pressure_systolic' => $validated['blood_pressure_systolic'],
            'blood_pressure_diastolic' => $validated['blood_pressure_diastolic'],
            'temperature' => $validated['temperature'],
            'pulse_rate' => $validated['pulse_rate'],
            'respiratory_rate' => $validated['respiratory_rate'],
            'weight' => $validated['weight'] ?? null,
            'height' => $validated['height'] ?? null,
            'oxygen_saturation' => $validated['oxygen_saturation'] ?? null,
            'blood_sugar' => $validated['blood_sugar'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'recorded_at' => $recordedAt,
        ]);

        return redirect()->back()->with('success', 'Vital signs recorded successfully.');
    }

    public function updateForAdmission(Request $request, PatientAdmission $admission, VitalSign $vitalSign)
    {
        $this->authorize('update', $vitalSign);

        // Ensure the vital sign belongs to this admission
        if ($vitalSign->patient_admission_id !== $admission->id) {
            abort(404);
        }

        $rules = [
            'temperature' => 'nullable|numeric',
            'blood_pressure_systolic' => 'nullable|integer',
            'blood_pressure_diastolic' => 'nullable|integer',
            'pulse_rate' => 'nullable|integer',
            'respiratory_rate' => 'nullable|integer',
            'oxygen_saturation' => 'nullable|integer',
            'blood_sugar' => 'nullable|numeric',
            'weight' => 'nullable|integer',
            'height' => 'nullable|numeric',
            'notes' => 'nullable|string|max:500',
        ];

        // Only allow recorded_at if user has permission
        if (auth()->user()->can('editTimestamp', $vitalSign)) {
            $rules['recorded_at'] = 'nullable|date';
        }

        $validated = $request->validate($rules);

        // Remove recorded_at from update if user doesn't have permission
        if (! auth()->user()->can('editTimestamp', $vitalSign)) {
            unset($validated['recorded_at']);
        }

        $vitalSign->update($validated);

        return redirect()->back()->with('success', 'Vital signs updated successfully.');
    }
}
