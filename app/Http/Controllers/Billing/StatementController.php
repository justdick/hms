<?php

namespace App\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
use App\Models\Patient;
use App\Services\PdfService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class StatementController extends Controller
{
    public function __construct(
        protected PdfService $pdfService
    ) {}

    /**
     * Generate a patient statement PDF.
     */
    public function generate(Request $request, Patient $patient): Response
    {
        $this->authorize('generateStatement', Patient::class);

        $validated = $request->validate([
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
        ]);

        $startDate = Carbon::parse($validated['start_date']);
        $endDate = Carbon::parse($validated['end_date']);

        // Generate the PDF
        $pdf = $this->pdfService->generateStatement($patient, $startDate, $endDate);

        // Log the statement generation
        $this->pdfService->logStatementGeneration(
            $patient,
            $startDate,
            $endDate,
            auth()->id(),
            $request->ip()
        );

        // Generate filename
        $filename = sprintf(
            'statement_%s_%s_to_%s.pdf',
            $patient->patient_number,
            $startDate->format('Y-m-d'),
            $endDate->format('Y-m-d')
        );

        return $pdf->download($filename);
    }

    /**
     * Preview statement data (for modal display).
     */
    public function preview(Request $request, Patient $patient): array
    {
        $this->authorize('generateStatement', Patient::class);

        $validated = $request->validate([
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
        ]);

        $startDate = Carbon::parse($validated['start_date']);
        $endDate = Carbon::parse($validated['end_date']);

        return $this->pdfService->getStatementData($patient, $startDate, $endDate);
    }
}
