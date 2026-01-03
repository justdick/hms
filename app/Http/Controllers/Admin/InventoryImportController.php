<?php

namespace App\Http\Controllers\Admin;

use App\Exports\InventoryExport;
use App\Http\Controllers\Controller;
use App\Imports\InventoryImport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class InventoryImportController extends Controller
{
    /**
     * Download inventory export (all batches with stock).
     */
    public function export(): BinaryFileResponse
    {
        $this->authorize('viewAny', \App\Models\Drug::class);

        $filename = 'inventory_export_'.now()->format('Y-m-d_His').'.xlsx';

        return Excel::download(new InventoryExport, $filename);
    }

    /**
     * Import inventory from exported file.
     */
    public function import(Request $request): RedirectResponse
    {
        $this->authorize('manageBatches', \App\Models\Drug::class);

        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv|max:10240',
        ]);

        try {
            $file = $request->file('file');
            $extension = $file->getClientOriginalExtension();

            // Read the file
            if ($extension === 'csv') {
                $data = Excel::toArray(null, $file)[0];
            } else {
                // For xlsx, get the Data sheet (second sheet, index 1)
                $sheets = Excel::toArray(null, $file);
                $data = $sheets[1] ?? $sheets[0]; // Data sheet or first sheet
            }

            if (empty($data)) {
                return back()->with('error', 'The file appears to be empty.');
            }

            // Get headers from first row
            $headers = array_map('strtolower', array_map('trim', $data[0]));

            // Map data rows to associative arrays
            $dataRows = [];
            for ($i = 1; $i < count($data); $i++) {
                $row = $data[$i];

                // Skip empty rows
                if (empty(array_filter($row))) {
                    continue;
                }

                $mappedRow = [];
                foreach ($headers as $index => $header) {
                    $mappedRow[$header] = $row[$index] ?? null;
                }
                $dataRows[] = $mappedRow;
            }

            if (empty($dataRows)) {
                return back()->with('error', 'No data rows found in the file.');
            }

            // Process the import
            $importer = new InventoryImport;
            $results = $importer->processRows($dataRows);

            $message = "Import complete: {$results['created']} created, {$results['updated']} updated, {$results['skipped']} skipped.";

            if (! empty($results['errors'])) {
                $errorCount = count($results['errors']);
                $message .= " {$errorCount} errors occurred.";

                // Store errors in session for display
                session()->flash('import_errors', array_slice($results['errors'], 0, 20));
            }

            return back()->with('success', $message);

        } catch (\Exception $e) {
            return back()->with('error', 'Import failed: '.$e->getMessage());
        }
    }
}
