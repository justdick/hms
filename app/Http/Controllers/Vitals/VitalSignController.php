<?php

namespace App\Http\Controllers\Vitals;

use App\Http\Controllers\Controller;
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
            'blood_pressure_systolic' => 'nullable|numeric|min:0|max:300',
            'blood_pressure_diastolic' => 'nullable|numeric|min:0|max:200',
            'temperature' => 'nullable|numeric|min:30|max:45',
            'pulse_rate' => 'nullable|integer|min:30|max:200',
            'respiratory_rate' => 'nullable|integer|min:5|max:60',
            'weight' => 'nullable|numeric|min:0|max:500',
            'height' => 'nullable|numeric|min:20|max:300',
            'oxygen_saturation' => 'nullable|integer|min:50|max:100',
            'notes' => 'nullable|string',
        ]);

        $checkin = PatientCheckin::findOrFail($validated['patient_checkin_id']);

        $vitalSign = VitalSign::create([
            'patient_id' => $checkin->patient_id,
            'patient_checkin_id' => $validated['patient_checkin_id'],
            'recorded_by' => auth()->id(),
            'blood_pressure_systolic' => $validated['blood_pressure_systolic'],
            'blood_pressure_diastolic' => $validated['blood_pressure_diastolic'],
            'temperature' => $validated['temperature'],
            'pulse_rate' => $validated['pulse_rate'],
            'respiratory_rate' => $validated['respiratory_rate'],
            'weight' => $validated['weight'],
            'height' => $validated['height'],
            'oxygen_saturation' => $validated['oxygen_saturation'],
            'notes' => $validated['notes'],
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
            'blood_pressure_systolic' => 'nullable|numeric|min:0|max:300',
            'blood_pressure_diastolic' => 'nullable|numeric|min:0|max:200',
            'temperature' => 'nullable|numeric|min:30|max:45',
            'pulse_rate' => 'nullable|integer|min:30|max:200',
            'respiratory_rate' => 'nullable|integer|min:5|max:60',
            'weight' => 'nullable|numeric|min:0|max:500',
            'height' => 'nullable|numeric|min:20|max:300',
            'oxygen_saturation' => 'nullable|integer|min:50|max:100',
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
}
