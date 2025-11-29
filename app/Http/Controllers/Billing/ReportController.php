<?php

namespace App\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
use App\Models\Charge;
use App\Services\PdfService;
use App\Services\ReportService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Maatwebsite\Excel\Facades\Excel;

class ReportController extends Controller
{
    public function __construct(
        private ReportService $reportService,
        private PdfService $pdfService
    ) {}

    /**
     * Display the outstanding balances report.
     */
    public function outstanding(Request $request): InertiaResponse
    {
        $this->authorize('viewAll', Charge::class);

        $filters = [
            'department_id' => $request->query('department_id'),
            'has_insurance' => $request->query('has_insurance') !== null
                ? filter_var($request->query('has_insurance'), FILTER_VALIDATE_BOOLEAN)
                : null,
            'min_amount' => $request->query('min_amount'),
            'max_amount' => $request->query('max_amount'),
        ];

        // Remove null filters
        $filters = array_filter($filters, fn ($value) => $value !== null && $value !== '');

        $balances = $this->reportService->getOutstandingBalances($filters);
        $summary = $this->reportService->getOutstandingSummary($filters);
        $departments = $this->reportService->getDepartments();

        return Inertia::render('Billing/Reports/Outstanding', [
            'balances' => $balances,
            'summary' => $summary,
            'departments' => $departments,
            'filters' => [
                'department_id' => $request->query('department_id', ''),
                'has_insurance' => $request->query('has_insurance', ''),
                'min_amount' => $request->query('min_amount', ''),
                'max_amount' => $request->query('max_amount', ''),
            ],
        ]);
    }

    /**
     * Export outstanding balances to Excel.
     */
    public function exportOutstandingExcel(Request $request): Response
    {
        $this->authorize('viewAll', Charge::class);

        $filters = [
            'department_id' => $request->query('department_id'),
            'has_insurance' => $request->query('has_insurance') !== null
                ? filter_var($request->query('has_insurance'), FILTER_VALIDATE_BOOLEAN)
                : null,
            'min_amount' => $request->query('min_amount'),
            'max_amount' => $request->query('max_amount'),
        ];

        $filters = array_filter($filters, fn ($value) => $value !== null && $value !== '');

        $data = $this->reportService->exportOutstandingBalances($filters);

        // Create CSV content
        $csv = '';
        if (count($data) > 0) {
            // Headers
            $csv .= implode(',', array_keys($data[0]))."\n";
            // Data rows
            foreach ($data as $row) {
                $csv .= implode(',', array_map(fn ($v) => '"'.str_replace('"', '""', $v).'"', $row))."\n";
            }
        }

        $filename = 'outstanding_balances_'.date('Y-m-d').'.csv';

        return response($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    /**
     * Export outstanding balances to PDF.
     */
    public function exportOutstandingPdf(Request $request): Response
    {
        $this->authorize('viewAll', Charge::class);

        $filters = [
            'department_id' => $request->query('department_id'),
            'has_insurance' => $request->query('has_insurance') !== null
                ? filter_var($request->query('has_insurance'), FILTER_VALIDATE_BOOLEAN)
                : null,
            'min_amount' => $request->query('min_amount'),
            'max_amount' => $request->query('max_amount'),
        ];

        $filters = array_filter($filters, fn ($value) => $value !== null && $value !== '');

        $balances = $this->reportService->getOutstandingBalances($filters);
        $summary = $this->reportService->getOutstandingSummary($filters);

        $pdf = $this->pdfService->generateOutstandingReport($balances, $summary);

        $filename = 'outstanding_balances_'.date('Y-m-d').'.pdf';

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    /**
     * Display the revenue report.
     */
    public function revenue(Request $request): InertiaResponse
    {
        $this->authorize('viewAll', Charge::class);

        // Default to last 30 days if no date range specified
        $startDate = $request->query('start_date')
            ? Carbon::parse($request->query('start_date'))
            : Carbon::now()->subDays(29);
        $endDate = $request->query('end_date')
            ? Carbon::parse($request->query('end_date'))
            : Carbon::now();

        $groupBy = $request->query('group_by', 'date');

        $report = $this->reportService->getRevenueReport($startDate, $endDate, $groupBy);
        $departments = $this->reportService->getDepartments();

        return Inertia::render('Billing/Reports/Revenue', [
            'report' => $report,
            'departments' => $departments,
            'filters' => [
                'start_date' => $startDate->toDateString(),
                'end_date' => $endDate->toDateString(),
                'group_by' => $groupBy,
            ],
        ]);
    }

    /**
     * Export revenue report to Excel.
     */
    public function exportRevenueExcel(Request $request): Response
    {
        $this->authorize('viewAll', Charge::class);

        $startDate = $request->query('start_date')
            ? Carbon::parse($request->query('start_date'))
            : Carbon::now()->subDays(29);
        $endDate = $request->query('end_date')
            ? Carbon::parse($request->query('end_date'))
            : Carbon::now();

        $groupBy = $request->query('group_by', 'date');

        $data = $this->reportService->exportRevenueReport($startDate, $endDate, $groupBy);

        // Create CSV content
        $csv = '';
        if (count($data) > 0) {
            // Headers
            $csv .= implode(',', array_keys($data[0]))."\n";
            // Data rows
            foreach ($data as $row) {
                $csv .= implode(',', array_map(fn ($v) => '"'.str_replace('"', '""', $v).'"', $row))."\n";
            }
        }

        $filename = 'revenue_report_'.$startDate->format('Y-m-d').'_to_'.$endDate->format('Y-m-d').'.csv';

        return response($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    /**
     * Export revenue report to PDF.
     */
    public function exportRevenuePdf(Request $request): Response
    {
        $this->authorize('viewAll', Charge::class);

        $startDate = $request->query('start_date')
            ? Carbon::parse($request->query('start_date'))
            : Carbon::now()->subDays(29);
        $endDate = $request->query('end_date')
            ? Carbon::parse($request->query('end_date'))
            : Carbon::now();

        $groupBy = $request->query('group_by', 'date');

        $report = $this->reportService->getRevenueReport($startDate, $endDate, $groupBy);

        $pdf = $this->pdfService->generateRevenueReport($report, $groupBy);

        $filename = 'revenue_report_'.$startDate->format('Y-m-d').'_to_'.$endDate->format('Y-m-d').'.pdf';

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }
}
