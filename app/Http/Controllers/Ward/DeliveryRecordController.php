<?php

namespace App\Http\Controllers\Ward;

use App\Http\Controllers\Controller;
use App\Models\DeliveryRecord;
use App\Models\Patient;
use App\Models\PatientAdmission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class DeliveryRecordController extends Controller
{
    /**
     * Get delivery records for a patient
     */
    public function index(Patient $patient)
    {
        $this->authorize('view', $patient);

        $deliveryRecords = DeliveryRecord::where('patient_id', $patient->id)
            ->with(['recordedBy:id,name', 'lastEditedBy:id,name', 'patientAdmission:id,admission_number,ward_id'])
            ->orderByDesc('delivery_date')
            ->get()
            ->map(fn ($record) => $this->formatDeliveryRecord($record));

        return response()->json(['delivery_records' => $deliveryRecords]);
    }

    /**
     * Store a new delivery record
     */
    public function store(Request $request, PatientAdmission $admission)
    {
        $this->authorize('update', $admission);

        // Verify this is a maternity ward admission
        if (! $this->isMaternityWard($admission)) {
            return back()->withErrors(['error' => 'Delivery records can only be added for maternity ward admissions.']);
        }

        $validated = $request->validate([
            'delivery_date' => ['required', 'date', 'before_or_equal:today'],
            'gestational_age' => ['nullable', 'string', 'max:50'],
            'parity' => ['nullable', 'string', 'max:50'],
            'delivery_mode' => ['required', Rule::in(array_keys(DeliveryRecord::DELIVERY_MODES))],
            'outcomes' => ['nullable', 'array'],
            'outcomes.*.time_of_delivery' => ['nullable', 'string', 'max:20'],
            'outcomes.*.sex' => ['nullable', Rule::in(['male', 'female', 'unknown'])],
            'outcomes.*.apgar_1min' => ['nullable', 'integer', 'min:0', 'max:10'],
            'outcomes.*.apgar_5min' => ['nullable', 'integer', 'min:0', 'max:10'],
            'outcomes.*.apgar_10min' => ['nullable', 'integer', 'min:0', 'max:10'],
            'outcomes.*.birth_weight' => ['nullable', 'numeric', 'min:0', 'max:10000'],
            'outcomes.*.head_circumference' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'outcomes.*.full_length' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'outcomes.*.notes' => ['nullable', 'string', 'max:500'],
            'surgical_notes' => ['nullable', 'string', 'max:5000'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $deliveryRecord = DB::transaction(function () use ($validated, $admission) {
            return DeliveryRecord::create([
                'patient_admission_id' => $admission->id,
                'patient_id' => $admission->patient_id,
                'delivery_date' => $validated['delivery_date'],
                'gestational_age' => $validated['gestational_age'] ?? null,
                'parity' => $validated['parity'] ?? null,
                'delivery_mode' => $validated['delivery_mode'],
                'outcomes' => $validated['outcomes'] ?? null,
                'surgical_notes' => $validated['surgical_notes'] ?? null,
                'notes' => $validated['notes'] ?? null,
                'recorded_by_id' => auth()->id(),
            ]);
        });

        return back()->with('success', 'Delivery record added successfully.');
    }

    /**
     * Update a delivery record
     */
    public function update(Request $request, DeliveryRecord $deliveryRecord)
    {
        $this->authorize('update', $deliveryRecord->patientAdmission);

        $validated = $request->validate([
            'delivery_date' => ['required', 'date', 'before_or_equal:today'],
            'gestational_age' => ['nullable', 'string', 'max:50'],
            'parity' => ['nullable', 'string', 'max:50'],
            'delivery_mode' => ['required', Rule::in(array_keys(DeliveryRecord::DELIVERY_MODES))],
            'outcomes' => ['nullable', 'array'],
            'outcomes.*.time_of_delivery' => ['nullable', 'string', 'max:20'],
            'outcomes.*.sex' => ['nullable', Rule::in(['male', 'female', 'unknown'])],
            'outcomes.*.apgar_1min' => ['nullable', 'integer', 'min:0', 'max:10'],
            'outcomes.*.apgar_5min' => ['nullable', 'integer', 'min:0', 'max:10'],
            'outcomes.*.apgar_10min' => ['nullable', 'integer', 'min:0', 'max:10'],
            'outcomes.*.birth_weight' => ['nullable', 'numeric', 'min:0', 'max:10000'],
            'outcomes.*.head_circumference' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'outcomes.*.full_length' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'outcomes.*.notes' => ['nullable', 'string', 'max:500'],
            'surgical_notes' => ['nullable', 'string', 'max:5000'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $deliveryRecord->update([
            'delivery_date' => $validated['delivery_date'],
            'gestational_age' => $validated['gestational_age'] ?? null,
            'parity' => $validated['parity'] ?? null,
            'delivery_mode' => $validated['delivery_mode'],
            'outcomes' => $validated['outcomes'] ?? null,
            'surgical_notes' => $validated['surgical_notes'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'last_edited_by_id' => auth()->id(),
        ]);

        return back()->with('success', 'Delivery record updated successfully.');
    }

    /**
     * Delete a delivery record
     */
    public function destroy(DeliveryRecord $deliveryRecord)
    {
        $this->authorize('update', $deliveryRecord->patientAdmission);

        $deliveryRecord->delete();

        return back()->with('success', 'Delivery record deleted successfully.');
    }

    /**
     * Check if admission is in maternity ward
     */
    private function isMaternityWard(PatientAdmission $admission): bool
    {
        return $admission->ward && $admission->ward->code === 'MATERNITY-WARD';
    }

    /**
     * Format delivery record for API response
     */
    private function formatDeliveryRecord(DeliveryRecord $record): array
    {
        return [
            'id' => $record->id,
            'patient_admission_id' => $record->patient_admission_id,
            'admission_number' => $record->patientAdmission?->admission_number,
            'delivery_date' => $record->delivery_date->format('Y-m-d'),
            'gestational_age' => $record->gestational_age,
            'parity' => $record->parity,
            'delivery_mode' => $record->delivery_mode,
            'delivery_mode_label' => $record->delivery_mode_label,
            'outcomes' => $record->outcomes,
            'surgical_notes' => $record->surgical_notes,
            'notes' => $record->notes,
            'is_c_section' => $record->isCSection(),
            'baby_count' => $record->baby_count,
            'recorded_by' => $record->recordedBy?->name,
            'last_edited_by' => $record->lastEditedBy?->name,
            'created_at' => $record->created_at->format('Y-m-d H:i'),
            'updated_at' => $record->updated_at->format('Y-m-d H:i'),
        ];
    }
}
