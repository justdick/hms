<?php

namespace App\Http\Controllers\Admin;

use App\Exports\DrugImportTemplate;
use App\Http\Controllers\Controller;
use App\Imports\DrugImport;
use App\Models\Drug;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DrugImportController extends Controller
{
    /**
     * Download the drug import template.
     */
    public function downloadTemplate(): BinaryFileResponse
    {
        $this->authorize('create', Drug::class);

        return Excel::download(
            new DrugImportTemplate,
            'drug_import_template.xlsx'
        );
    }

    /**
     * Import drugs from uploaded file.
     */
    public function import(Request $request): RedirectResponse
    {
        $this->authorize('create', Drug::class);

        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,xlsx,xls', 'max:10240'],
        ]);

        $file = $request->file('file');

        try {
            // Read the file
            $data = Excel::toArray([], $file);

            // Get the data sheet (second sheet if Excel, first if CSV)
            $rows = [];
            if (count($data) > 1 && ! empty($data[1])) {
                // Excel with multiple sheets - use Data sheet
                $rows = $data[1];
            } else {
                // CSV or single sheet Excel
                $rows = $data[0] ?? [];
            }

            if (empty($rows)) {
                return redirect()
                    ->back()
                    ->with('error', 'No data found in the file.');
            }

            // Get headers from first row
            $headers = array_map(fn ($h) => strtolower(trim($h ?? '')), array_shift($rows));

            // Convert rows to associative arrays
            $dataRows = [];
            foreach ($rows as $row) {
                $rowData = [];
                foreach ($headers as $index => $header) {
                    if ($header && isset($row[$index])) {
                        $rowData[$header] = $row[$index];
                    }
                }
                // Skip empty rows
                if (! empty(array_filter($rowData))) {
                    $dataRows[] = $rowData;
                }
            }

            if (empty($dataRows)) {
                return redirect()
                    ->back()
                    ->with('error', 'No valid data rows found in the file.');
            }

            // Process the import
            $importer = new DrugImport;
            $results = $importer->processRows($dataRows);

            // Build result message
            $message = sprintf(
                'Import completed: %d created, %d updated, %d mapped to NHIS.',
                $results['created'],
                $results['updated'],
                $results['mapped']
            );

            if ($results['skipped'] > 0) {
                $message .= sprintf(' %d skipped.', $results['skipped']);
            }

            if (! empty($results['errors'])) {
                $errorCount = count($results['errors']);
                $message .= sprintf(' %d errors occurred.', $errorCount);

                // Show first few errors
                $errorMessages = array_slice(
                    array_map(fn ($e) => "Row {$e['row']}: {$e['error']}", $results['errors']),
                    0,
                    3
                );

                return redirect()
                    ->back()
                    ->with('warning', $message.' Errors: '.implode('; ', $errorMessages));
            }

            return redirect()
                ->back()
                ->with('success', $message);

        } catch (\Exception $e) {
            return redirect()
                ->back()
                ->with('error', 'Import failed: '.$e->getMessage());
        }
    }
}
