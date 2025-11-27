<?php

namespace App\Http\Controllers\Admin;

use App\Exports\ProcedureTypeImportTemplate;
use App\Http\Controllers\Controller;
use App\Imports\ProcedureTypeImport;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class ProcedureTypeImportController extends Controller
{
    /**
     * Download import template.
     */
    public function template()
    {
        return Excel::download(new ProcedureTypeImportTemplate, 'procedure_type_import_template.xlsx');
    }

    /**
     * Import procedure types from CSV/Excel.
     */
    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:csv,xlsx,xls|max:10240',
        ]);

        $file = $request->file('file');
        $extension = $file->getClientOriginalExtension();

        // Parse the file
        $rows = [];
        if ($extension === 'csv') {
            $rows = $this->parseCsv($file->getPathname());
        } else {
            $rows = $this->parseExcel($file->getPathname());
        }

        if (empty($rows)) {
            return redirect()->back()->with('error', 'No data found in file.');
        }

        $importer = new ProcedureTypeImport;
        $results = $importer->processRows($rows);

        $message = sprintf(
            'Import completed: %d created, %d updated, %d mapped to NHIS. %d skipped.',
            $results['created'],
            $results['updated'],
            $results['mapped'],
            $results['skipped']
        );

        if (! empty($results['errors'])) {
            $errorCount = count($results['errors']);
            $message .= " {$errorCount} errors occurred.";

            // Show first 10 errors
            $errorMessages = array_slice(
                array_map(fn ($e) => "Row {$e['row']}: {$e['error']}", $results['errors']),
                0,
                10
            );
            $message .= ' Errors: '.implode('; ', $errorMessages);

            if ($errorCount > 10) {
                $message .= '... and '.($errorCount - 10).' more.';
            }
        }

        $flashType = $results['created'] > 0 || $results['updated'] > 0 ? 'success' : 'warning';

        return redirect()->back()->with($flashType, $message);
    }

    private function parseCsv(string $path): array
    {
        $rows = [];
        $headers = [];

        if (($handle = fopen($path, 'r')) !== false) {
            $lineNumber = 0;
            while (($data = fgetcsv($handle)) !== false) {
                $lineNumber++;

                if ($lineNumber === 1) {
                    $headers = array_map('trim', $data);

                    continue;
                }

                if (count($data) === count($headers)) {
                    $rows[] = array_combine($headers, $data);
                }
            }
            fclose($handle);
        }

        return $rows;
    }

    private function parseExcel(string $path): array
    {
        $rows = [];
        $data = Excel::toArray(null, $path);

        if (! empty($data[0])) {
            $sheet = $data[0];
            $headers = array_map('trim', $sheet[0] ?? []);

            for ($i = 1; $i < count($sheet); $i++) {
                if (count($sheet[$i]) === count($headers)) {
                    $rows[] = array_combine($headers, $sheet[$i]);
                }
            }
        }

        return $rows;
    }
}
