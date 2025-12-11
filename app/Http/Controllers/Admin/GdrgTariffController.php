<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\ImportGdrgTariffRequest;
use App\Http\Requests\StoreGdrgTariffRequest;
use App\Http\Requests\UpdateGdrgTariffRequest;
use App\Http\Resources\GdrgTariffResource;
use App\Models\GdrgTariff;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class GdrgTariffController extends Controller
{
    /**
     * Display a listing of G-DRG tariffs.
     */
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', GdrgTariff::class);

        $perPage = min($request->input('per_page', 20), 100);

        $tariffs = GdrgTariff::query()
            ->search($request->input('search'))
            ->byMdcCategory($request->input('mdc_category'))
            ->byAgeCategory($request->input('age_category'))
            ->when($request->boolean('active_only'), fn ($q) => $q->active())
            ->orderBy('name')
            ->paginate($perPage)
            ->withQueryString();

        // Get distinct MDC categories for filter dropdown
        $mdcCategories = GdrgTariff::query()
            ->distinct()
            ->pluck('mdc_category')
            ->filter()
            ->sort()
            ->values();

        return Inertia::render('Admin/GdrgTariffs/Index', [
            'tariffs' => GdrgTariffResource::collection($tariffs),
            'filters' => [
                'search' => $request->input('search'),
                'mdc_category' => $request->input('mdc_category'),
                'age_category' => $request->input('age_category'),
                'active_only' => $request->boolean('active_only'),
            ],
            'mdcCategories' => $mdcCategories,
            'ageCategories' => ['adult', 'child', 'all'],
        ]);
    }

    /**
     * Store a newly created G-DRG tariff.
     */
    public function store(StoreGdrgTariffRequest $request): RedirectResponse
    {
        $this->authorize('create', GdrgTariff::class);

        GdrgTariff::create($request->validated());

        return redirect()
            ->route('admin.gdrg-tariffs.index')
            ->with('success', 'G-DRG tariff created successfully.');
    }

    /**
     * Update the specified G-DRG tariff.
     */
    public function update(UpdateGdrgTariffRequest $request, GdrgTariff $gdrgTariff): RedirectResponse
    {
        $this->authorize('update', $gdrgTariff);

        $gdrgTariff->update($request->validated());

        return redirect()
            ->route('admin.gdrg-tariffs.index')
            ->with('success', 'G-DRG tariff updated successfully.');
    }

    /**
     * Remove the specified G-DRG tariff.
     */
    public function destroy(GdrgTariff $gdrgTariff): RedirectResponse
    {
        $this->authorize('delete', $gdrgTariff);

        // Check if tariff is used in any claims
        if ($gdrgTariff->insuranceClaims()->exists()) {
            return back()->with('error', 'Cannot delete tariff that is used in existing claims.');
        }

        $gdrgTariff->delete();

        return redirect()
            ->route('admin.gdrg-tariffs.index')
            ->with('success', 'G-DRG tariff deleted successfully.');
    }

    /**
     * Import G-DRG tariffs from a file.
     */
    public function import(ImportGdrgTariffRequest $request): RedirectResponse
    {
        $this->authorize('create', GdrgTariff::class);

        $result = $this->importTariffs($request->file('file'));

        if (! $result['success']) {
            return back()->with('error', 'Import failed: '.implode(', ', $result['errors']));
        }

        $message = "Import completed: {$result['imported']} created, {$result['updated']} updated.";

        if (! empty($result['errors'])) {
            $message .= ' Some rows had errors: '.implode('; ', array_slice($result['errors'], 0, 3));
        }

        return redirect()
            ->route('admin.gdrg-tariffs.index')
            ->with('success', $message);
    }

    /**
     * Search G-DRG tariffs for dropdown (JSON response).
     */
    public function search(Request $request): JsonResponse
    {
        $this->authorize('viewAny', GdrgTariff::class);

        $tariffs = GdrgTariff::query()
            ->active()
            ->search($request->input('search'))
            ->byMdcCategory($request->input('mdc_category'))
            ->byAgeCategory($request->input('age_category'))
            ->orderBy('name')
            ->limit($request->integer('limit', 50))
            ->get();

        return response()->json([
            'tariffs' => GdrgTariffResource::collection($tariffs),
        ]);
    }

    /**
     * Import tariffs from uploaded file.
     */
    protected function importTariffs($file): array
    {
        $result = [
            'success' => true,
            'imported' => 0,
            'updated' => 0,
            'errors' => [],
        ];

        try {
            $extension = $file->getClientOriginalExtension();

            if (in_array($extension, ['csv', 'txt'])) {
                $rows = $this->parseCsvFile($file);
            } else {
                // For Excel files, use a simple approach
                $rows = $this->parseExcelFile($file);
            }

            foreach ($rows as $index => $row) {
                $rowNumber = $index + 2; // Account for header row

                try {
                    $code = trim($row['code'] ?? $row['Code'] ?? '');
                    $name = trim($row['name'] ?? $row['Name'] ?? '');
                    $mdcCategory = trim($row['mdc_category'] ?? $row['MDC Category'] ?? $row['mdc'] ?? '');
                    $tariffPrice = (float) ($row['tariff_price'] ?? $row['Tariff Price'] ?? $row['price'] ?? 0);
                    $ageCategory = trim($row['age_category'] ?? $row['Age Category'] ?? 'all');

                    if (empty($code) || empty($name)) {
                        $result['errors'][] = "Row {$rowNumber}: Code and name are required.";

                        continue;
                    }

                    if ($tariffPrice < 0) {
                        $result['errors'][] = "Row {$rowNumber}: Price cannot be negative.";

                        continue;
                    }

                    // Normalize age category
                    $ageCategory = strtolower($ageCategory);
                    if (! in_array($ageCategory, ['adult', 'child', 'all'])) {
                        $ageCategory = 'all';
                    }

                    $existing = GdrgTariff::where('code', $code)->first();

                    if ($existing) {
                        $existing->update([
                            'name' => $name,
                            'mdc_category' => $mdcCategory,
                            'tariff_price' => $tariffPrice,
                            'age_category' => $ageCategory,
                        ]);
                        $result['updated']++;
                    } else {
                        GdrgTariff::create([
                            'code' => $code,
                            'name' => $name,
                            'mdc_category' => $mdcCategory,
                            'tariff_price' => $tariffPrice,
                            'age_category' => $ageCategory,
                            'is_active' => true,
                        ]);
                        $result['imported']++;
                    }
                } catch (\Exception $e) {
                    $result['errors'][] = "Row {$rowNumber}: ".$e->getMessage();
                }
            }
        } catch (\Exception $e) {
            $result['success'] = false;
            $result['errors'][] = $e->getMessage();
        }

        return $result;
    }

    /**
     * Parse CSV file into array of rows.
     */
    protected function parseCsvFile($file): array
    {
        $rows = [];
        $handle = fopen($file->getPathname(), 'r');

        if ($handle === false) {
            throw new \Exception('Unable to open file.');
        }

        $headers = fgetcsv($handle);
        if ($headers === false) {
            fclose($handle);
            throw new \Exception('Unable to read file headers.');
        }

        // Normalize headers
        $headers = array_map('trim', $headers);

        while (($data = fgetcsv($handle)) !== false) {
            if (count($data) === count($headers)) {
                $rows[] = array_combine($headers, $data);
            }
        }

        fclose($handle);

        return $rows;
    }

    /**
     * Parse Excel file into array of rows.
     */
    protected function parseExcelFile($file): array
    {
        // For simplicity, we'll use PhpSpreadsheet if available
        // Otherwise fall back to CSV parsing
        if (! class_exists(\PhpOffice\PhpSpreadsheet\IOFactory::class)) {
            throw new \Exception('Excel file support requires PhpSpreadsheet. Please use CSV format.');
        }

        $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($file->getPathname());
        $worksheet = $spreadsheet->getActiveSheet();
        $rows = [];
        $headers = [];

        foreach ($worksheet->getRowIterator() as $rowIndex => $row) {
            $cellIterator = $row->getCellIterator();
            $cellIterator->setIterateOnlyExistingCells(false);

            $rowData = [];
            foreach ($cellIterator as $cell) {
                $rowData[] = $cell->getValue();
            }

            if ($rowIndex === 1) {
                $headers = array_map('trim', $rowData);
            } else {
                if (count($rowData) === count($headers)) {
                    $rows[] = array_combine($headers, $rowData);
                }
            }
        }

        return $rows;
    }
}
