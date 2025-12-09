<?php

namespace App\Http\Controllers\Admin;

use App\Exports\ProcedureTypeImportTemplate;
use App\Http\Controllers\Controller;
use App\Imports\ProcedureTypeImport;
use App\Models\MinorProcedureType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ProcedureTypeImportController extends Controller
{
    /**
     * Download the procedure type import template.
     */
    public function downloadTemplate(): BinaryFileResponse
    {
        $this->authorize('create', MinorProcedureType::class);

        return Excel::download(
            new ProcedureTypeImportTemplate,
            'procedure_type_import_template.xlsx'
        );
    }

    /**
     * Import procedure types from uploaded file.
     */
    public function import(Request $request): RedirectResponse
    {
        $this->authorize('create', MinorProcedureType::class);

        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,xlsx,xls', 'max:10240'],
        ]);

        $file = $request->file('file');

        try {
            $data = Excel::toArray([], $file);

            $rows = [];
            if (count($data) > 1 && ! empty($data[1])) {
                $rows = $data[1];
            } else {
                $rows = $data[0] ?? [];
            }

            if (empty($rows)) {
                return redirect()->back()->with('error', 'No data found in the file.');
            }

            $headers = array_map(fn ($h) => strtolower(trim($h ?? '')), array_shift($rows));

            $dataRows = [];
            foreach ($rows as $row) {
                $rowData = [];
                foreach ($headers as $index => $header) {
                    if ($header && isset($row[$index])) {
                        $rowData[$header] = $row[$index];
                    }
                }
                if (! empty(array_filter($rowData))) {
                    $dataRows[] = $rowData;
                }
            }

            if (empty($dataRows)) {
                return redirect()->back()->with('error', 'No valid data rows found.');
            }

            $importer = new ProcedureTypeImport;
            $results = $importer->processRows($dataRows);

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
                $errorMessages = array_slice(
                    array_map(fn ($e) => "Row {$e['row']}: {$e['error']}", $results['errors']),
                    0,
                    3
                );

                return redirect()->back()->with('warning', $message.' Errors: '.implode('; ', $errorMessages));
            }

            return redirect()->back()->with('success', $message);

        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Import failed: '.$e->getMessage());
        }
    }
}
