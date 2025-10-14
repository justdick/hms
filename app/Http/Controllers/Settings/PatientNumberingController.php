<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\Patient;
use App\Models\SystemConfiguration;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PatientNumberingController extends Controller
{
    public function index(): Response
    {
        $this->authorize('viewAny', Patient::class);

        $config = SystemConfiguration::getGroup('patient_numbering');

        // Get the last patient number for preview
        $lastPatient = Patient::latest('id')->first();
        $nextNumber = $lastPatient ? $lastPatient->id + 1 : 1;

        return Inertia::render('settings/patient-numbering', [
            'config' => $config,
            'nextNumber' => $nextNumber,
            'currentExample' => $lastPatient?->patient_number ?? 'No patients yet',
        ]);
    }

    public function update(Request $request)
    {
        $this->authorize('viewAny', Patient::class);

        $validated = $request->validate([
            'patient_number_prefix' => 'required|string|max:10|alpha',
            'patient_number_year_format' => 'required|in:YYYY,YY',
            'patient_number_separator' => 'nullable|string|max:1',
            'patient_number_padding' => 'required|integer|min:3|max:8',
            'patient_number_reset' => 'required|in:never,yearly,monthly',
        ]);

        foreach ($validated as $key => $value) {
            $type = $key === 'patient_number_padding' ? 'integer' : 'string';
            $config = SystemConfiguration::where('key', $key)->first();

            SystemConfiguration::set(
                $key,
                $value ?? '',
                $type,
                $config?->description,
                'patient_numbering'
            );
        }

        SystemConfiguration::clearCache();

        return redirect()->back()->with('success', 'Patient numbering configuration updated successfully.');
    }

    public function preview(Request $request)
    {
        $validated = $request->validate([
            'prefix' => 'required|string|max:10',
            'yearFormat' => 'required|in:YYYY,YY',
            'separator' => 'nullable|string|max:1',
            'padding' => 'required|integer|min:3|max:8',
            'number' => 'required|integer|min:1',
        ]);

        $year = $validated['yearFormat'] === 'YYYY' ? date('Y') : date('y');
        $separator = $validated['separator'] ?? '';
        $paddedNumber = str_pad($validated['number'], $validated['padding'], '0', STR_PAD_LEFT);

        $preview = $validated['prefix'].$separator.$year.$separator.$paddedNumber;

        return response()->json(['preview' => $preview]);
    }
}
