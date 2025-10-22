<?php

namespace App\Http\Controllers\Ward;

use App\Http\Controllers\Controller;
use App\Models\Bed;
use App\Models\PatientAdmission;
use App\Models\Ward as WardModel;
use Illuminate\Http\Request;
use Inertia\Inertia;

class WardPatientController extends Controller
{
    public function show(Request $request, WardModel $ward, PatientAdmission $admission)
    {
        // Ensure the admission belongs to this ward
        if ($admission->ward_id !== $ward->id && $admission->bed?->ward_id !== $ward->id) {
            abort(404);
        }

        // Load admission with all required relationships for PatientShow
        $admission->load([
            'patient',
            'bed',
            'ward',
            'consultation.doctor',
            'vitalSigns' => function ($query) {
                $query->latest()->with('recordedBy:id,name');
            },
            'medicationAdministrations' => function ($query) {
                $query->with(['prescription.drug', 'administeredBy:id,name'])
                    ->orderBy('scheduled_time', 'desc');
            },
            'nursingNotes' => function ($query) {
                $query->with('nurse:id,name')->latest();
            },
            'wardRounds' => function ($query) {
                $query->with('doctor:id,name')
                    ->orderBy('round_datetime', 'desc')
                    ->orderBy('id', 'desc');
            },
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

        return Inertia::render('Ward/PatientShow', [
            'admission' => $admission,
            'availableBeds' => $availableBeds,
            'allBeds' => $allBeds,
            'hasAvailableBeds' => $availableBeds->isNotEmpty(),
        ]);
    }
}
