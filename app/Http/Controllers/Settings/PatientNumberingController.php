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

        // Calculate the actual next number based on current format
        $nextNumber = $this->calculateNextNumber($config);

        return Inertia::render('settings/patient-numbering', [
            'config' => $config,
            'nextNumber' => $nextNumber,
            'currentExample' => $lastPatient?->patient_number ?? 'No patients yet',
        ]);
    }

    /**
     * Calculate the next patient number based on existing records and current format.
     * This properly handles imported data where the highest number may not be the last by ID.
     */
    private function calculateNextNumber(array $config): int
    {
        $format = $config['patient_number_format'] ?? 'prefix_year_number';
        $prefix = $config['patient_number_prefix'] ?? 'PAT';
        $yearFormat = $config['patient_number_year_format'] ?? 'YYYY';
        $separator = $config['patient_number_separator'] ?? '';
        $padding = (int) ($config['patient_number_padding'] ?? 6);
        $resetPolicy = $config['patient_number_reset'] ?? 'never';

        // Generate year based on format
        $year = $yearFormat === 'YYYY' ? date('Y') : date('y');

        if ($format === 'number_year') {
            // Format: 1495/2022 - number comes first
            $suffixPattern = $separator.$year;

            if ($resetPolicy === 'monthly') {
                $suffixPattern = $separator.date('m').$separator.$year;
            }

            // Find ALL patient numbers with this year suffix and get the highest number
            $patientsWithPattern = Patient::where('patient_number', 'like', "%{$suffixPattern}")
                ->pluck('patient_number');

            $highestNumber = 0;
            foreach ($patientsWithPattern as $patientNumber) {
                // Extract the numeric part from the beginning (before the separator)
                $numericPart = preg_replace('/[^0-9].*/', '', $patientNumber);
                $number = (int) $numericPart;
                if ($number > $highestNumber) {
                    $highestNumber = $number;
                }
            }

            return $highestNumber + 1;
        }

        // Default format: PAT2025000001 - prefix + year + number
        $basePattern = $prefix.$separator.$year.$separator;

        if ($resetPolicy === 'monthly') {
            $basePattern .= date('m').$separator;
        }

        // Find ALL patient numbers with this pattern and get the highest number
        $patientsWithPattern = Patient::where('patient_number', 'like', "{$basePattern}%")
            ->pluck('patient_number');

        $highestNumber = 0;
        foreach ($patientsWithPattern as $patientNumber) {
            // Extract the numeric part from the end
            $numericPart = substr($patientNumber, -$padding);
            $number = (int) $numericPart;
            if ($number > $highestNumber) {
                $highestNumber = $number;
            }
        }

        return $highestNumber + 1;
    }

    public function update(Request $request)
    {
        $this->authorize('viewAny', Patient::class);

        $validated = $request->validate([
            'patient_number_prefix' => 'nullable|string|max:10|alpha',
            'patient_number_year_format' => 'required|in:YYYY,YY',
            'patient_number_separator' => 'nullable|string|max:1',
            'patient_number_padding' => 'required|integer|min:3|max:8',
            'patient_number_reset' => 'required|in:never,yearly,monthly',
            'patient_number_format' => 'required|in:prefix_year_number,number_year',
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
            'prefix' => 'nullable|string|max:10',
            'yearFormat' => 'required|in:YYYY,YY',
            'separator' => 'nullable|string|max:1',
            'padding' => 'required|integer|min:3|max:8',
            'number' => 'required|integer|min:1',
            'format' => 'required|in:prefix_year_number,number_year',
        ]);

        $year = $validated['yearFormat'] === 'YYYY' ? date('Y') : date('y');
        $separator = $validated['separator'] ?? '';
        $paddedNumber = str_pad($validated['number'], $validated['padding'], '0', STR_PAD_LEFT);

        if ($validated['format'] === 'number_year') {
            // Format: 1495/2022
            $preview = $paddedNumber.$separator.$year;
        } else {
            // Format: PAT2025000001
            $prefix = $validated['prefix'] ?? '';
            $preview = $prefix.$separator.$year.$separator.$paddedNumber;
        }

        return response()->json(['preview' => $preview]);
    }
}
