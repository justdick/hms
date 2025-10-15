<?php

namespace App\Http\Controllers\Ward;

use App\Http\Controllers\Controller;
use App\Models\PatientAdmission;
use App\Models\Ward;
use Inertia\Inertia;

class WardPatientController extends Controller
{
    public function show(Ward $ward, PatientAdmission $admission)
    {
        // Ensure the admission belongs to this ward
        if ($admission->ward_id !== $ward->id && $admission->bed?->ward_id !== $ward->id) {
            abort(404);
        }

        // Load all necessary relationships
        $admission->load([
            'patient:id,patient_number,first_name,last_name,date_of_birth,gender,phone_number',
            'bed:id,ward_id,bed_number,status,type',
            'ward:id,name,code',
            'consultation.doctor:id,name',
            'vitalSigns' => function ($query) {
                $query->latest('recorded_at')
                    ->with('recordedBy:id,name');
            },
            'medicationAdministrations' => function ($query) {
                $query->latest('scheduled_time')
                    ->with([
                        'prescription.drug:id,name,strength,form',
                        'administeredBy:id,name',
                    ]);
            },
            'nursingNotes' => function ($query) {
                $query->latest('created_at')
                    ->with('createdBy:id,name');
            },
            'wardRounds' => function ($query) {
                $query->latest('created_at')
                    ->with('doctor:id,name');
            },
        ]);

        return Inertia::render('Ward/PatientShow', [
            'admission' => $admission,
        ]);
    }
}
