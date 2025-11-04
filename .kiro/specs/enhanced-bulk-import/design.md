# Design Document

## Overview

This design enhances the bulk import system for insurance coverage rules by pre-populating templates with system inventory data and supporting all coverage types. The solution eliminates manual data entry, reduces errors, and provides the same flexibility as the UI.

## Architecture

### High-Level Design

```
┌─────────────────────────────────────────────────────────────┐
│                    Bulk Import Flow                         │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  1. Administrator clicks "Download Template"                │
│     ↓                                                       │
│  2. System queries all drugs/services for category         │
│     ↓                                                       │
│  3. System checks existing coverage rules for plan         │
│     ↓                                                       │
│  4. Template generated with:                               │
│     - All items pre-filled (code, name, price)            │
│     - Existing coverage values or defaults                 │
│     - Instructions sheet with examples                     │
│     ↓                                                       │
│  5. Administrator edits coverage_type & coverage_value     │
│     ↓                                                       │
│  6. Administrator uploads edited template                  │
│     ↓                                                       │
│  7. System validates coverage types and item codes         │
│     ↓                                                       │
│  8. System creates/updates coverage rules                  │
│     ↓                                                       │
│  9. Summary displayed (created/updated/skipped)            │
│                                                             │
└─────────────────────────────────────────────────────────────┘
```

### Key Design Principles

1. **Pre-population First**: Templates always include all system items
2. **Edit, Don't Enter**: Administrators edit values, not enter data
3. **Type Safety**: Validate coverage types strictly
4. **Backward Compatible**: Support old format during transition
5. **Clear Feedback**: Detailed errors with row numbers

## Components and Interfaces

### 1. Enhanced Template Export

**File**: `app/Exports/CoverageExceptionTemplate.php`

The template export is completely redesigned to query system inventory and pre-populate data.

```php
<?php

namespace App\Exports;

use App\Models\Drug;
use App\Models\InsuranceCoverageRule;
use App\Models\InsurancePlan;
use App\Models\LabTest;
use App\Models\Service;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class CoverageExceptionTemplate implements WithMultipleSheets
{
    public function __construct(
        private readonly string $category,
        private readonly int $insurancePlanId
    ) {}

    public function sheets(): array
    {
        return [
            new EnhancedInstructionsSheet($this->category),
            new PrePopulatedDataSheet($this->category, $this->insurancePlanId),
        ];
    }
}

class EnhancedInstructionsSheet implements FromArray, WithStyles, WithTitle
{
    public function __construct(
        private readonly string $category
    ) {}

    public function array(): array
    {
        return [
            ['Coverage Exception Import Template - Pre-Populated'],
            [''],
            ['INSTRUCTIONS:'],
            ['1. The Data sheet is PRE-FILLED with all items from your system inventory'],
            ['2. Review and edit ONLY the coverage_type and coverage_value columns'],
            ['3. Do NOT modify item_code, item_name, or current_price columns'],
            ['4. Delete rows for items that should use the general/default rule'],
            [''],
            ['COVERAGE TYPES:'],
            [''],
            ['Type: percentage'],
            ['  Description: Insurance covers X%, patient pays (100-X)%'],
            ['  Example: coverage_value = 80 means 80% insurance, 20% patient'],
            ['  Use for: Standard percentage-based coverage'],
            [''],
            ['Type: fixed_amount'],
            ['  Description: Insurance pays fixed dollar amount, patient pays rest'],
            ['  Example: coverage_value = 30 means insurance pays $30, patient pays (price - $30)'],
            ['  Use for: Drugs with fixed copay amounts'],
            [''],
            ['Type: full'],
            ['  Description: 100% coverage, no patient copay'],
            ['  Example: coverage_value = 100 (value ignored, always 100%)'],
            ['  Use for: Essential medications, preventive care'],
            [''],
            ['Type: excluded'],
            ['  Description: 0% coverage, patient pays all'],
            ['  Example: coverage_value = 0 (value ignored, always 0%)'],
            ['  Use for: Cosmetic items, non-covered services'],
            [''],
            ['EXAMPLES:'],
            ['  Paracetamol: percentage, 100 → 100% covered'],
            ['  Insulin: fixed_amount, 30 → Insurance pays $30, patient pays rest'],
            ['  Cosmetic cream: excluded, 0 → Not covered, patient pays all'],
            ['  Preventive vaccine: full, 100 → Fully covered, no copay'],
            [''],
            ['IMPORTANT NOTES:'],
            ['- All items are already populated - just edit coverage settings'],
            ['- Items you don\'t modify will keep their current coverage'],
            ['- Delete rows for items that should use the general rule'],
            ['- Do not add new rows - only edit existing ones'],
            [''],
            ['Category: ' . ucfirst($this->category)],
            ['Generated: ' . now()->format('Y-m-d H:i:s')],
        ];
    }

    public function title(): string
    {
        return 'Instructions';
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true, 'size' => 16]],
            3 => ['font' => ['bold' => true, 'size' => 12]],
            9 => ['font' => ['bold' => true, 'size' => 12]],
            29 => ['font' => ['bold' => true, 'size' => 12]],
            36 => ['font' => ['bold' => true, 'size' => 12]],
        ];
    }
}

class PrePopulatedDataSheet implements FromCollection, WithHeadings, WithStyles, WithTitle
{
    public function __construct(
        private readonly string $category,
        private readonly int $insurancePlanId
    ) {}

    public function collection(): Collection
    {
        // Get all items for this category from system inventory
        $items = $this->getItemsForCategory($this->category);
        
        // Get existing specific coverage rules for this plan
        $existingRules = InsuranceCoverageRule::where('insurance_plan_id', $this->insurancePlanId)
            ->where('coverage_category', $this->category)
            ->whereNotNull('item_code')
            ->get()
            ->keyBy('item_code');
        
        // Get general rule for default values
        $generalRule = InsuranceCoverageRule::where('insurance_plan_id', $this->insurancePlanId)
            ->where('coverage_category', $this->category)
            ->whereNull('item_code')
            ->first();
        
        $defaultCoverageType = $generalRule?->coverage_type ?? 'percentage';
        $defaultCoverageValue = $generalRule?->coverage_value ?? 80;
        
        // Map items to template rows
        return $items->map(function ($item) use ($existingRules, $defaultCoverageType, $defaultCoverageValue) {
            $existingRule = $existingRules->get($item->code);
            
            return [
                'item_code' => $item->code,
                'item_name' => $item->name,
                'current_price' => number_format($item->price, 2, '.', ''),
                'coverage_type' => $existingRule?->coverage_type ?? $defaultCoverageType,
                'coverage_value' => $existingRule?->coverage_value ?? $defaultCoverageValue,
                'notes' => $existingRule?->notes ?? '',
            ];
        });
    }
    
    private function getItemsForCategory(string $category): Collection
    {
        return match($category) {
            'drug' => Drug::select('code', 'name', 'selling_price as price')
                ->orderBy('name')
                ->get(),
            'lab' => LabTest::select('code', 'name', 'price')
                ->orderBy('name')
                ->get(),
            'consultation' => Service::select('code', 'name', 'price')
                ->where('type', 'consultation')
                ->orderBy('name')
                ->get(),
            'procedure' => Service::select('code', 'name', 'price')
                ->where('type', 'procedure')
                ->orderBy('name')
                ->get(),
            default => collect([]),
        };
    }

    public function headings(): array
    {
        return [
            'item_code',
            'item_name',
            'current_price',
            'coverage_type',
            'coverage_value',
            'notes',
        ];
    }

    public function title(): string
    {
        return 'Data';
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => [
                'font' => ['bold' => true],
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'E2E8F0']
                ]
            ],
        ];
    }
}
```

### 2. Enhanced Import Controller

**File**: `app/Http/Controllers/Admin/InsuranceCoverageImportController.php`

Updated to handle template downloads and process all coverage types.

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Exports\CoverageExceptionTemplate;
use App\Http\Controllers\Controller;
use App\Models\InsuranceCoverageRule;
use App\Models\InsurancePlan;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class InsuranceCoverageImportController extends Controller
{
    /**
     * Download pre-populated template for bulk import
     */
    public function downloadTemplate(InsurancePlan $plan, string $category)
    {
        $this->authorize('manage', $plan);
        
        // Validate category
        $validCategories = ['drug', 'lab', 'consultation', 'procedure', 'ward', 'nursing'];
        if (!in_array($category, $validCategories)) {
            return response()->json(['error' => 'Invalid category'], 400);
        }
        
        $fileName = sprintf(
            'coverage_template_%s_%s_%s.xlsx',
            $category,
            $plan->name,
            now()->format('Y-m-d')
        );
        
        return Excel::download(
            new CoverageExceptionTemplate($category, $plan->id),
            $fileName
        );
    }
    
    /**
     * Import coverage rules from uploaded file
     */
    public function import(Request $request, InsurancePlan $plan)
    {
        $this->authorize('manage', $plan);
        
        $request->validate([
            'file' => 'required|file|mimes:csv,xlsx,xls',
            'category' => 'required|in:drug,lab,consultation,procedure,ward,nursing'
        ]);
        
        $results = [
            'created' => 0,
            'updated' => 0,
            'skipped' => 0,
            'errors' => []
        ];
        
        try {
            $rows = Excel::toArray([], $request->file('file'))[0];
            
            // Skip header row
            $header = array_shift($rows);
            
            // Detect format (old vs new)
            $isNewFormat = in_array('coverage_type', $header);
            
            foreach ($rows as $index => $row) {
                $rowNumber = $index + 2; // +2 because we skipped header and Excel is 1-indexed
                
                try {
                    $rowData = array_combine($header, $row);
                    
                    // Convert old format to new format if needed
                    if (!$isNewFormat && isset($rowData['coverage_percentage'])) {
                        $rowData['coverage_type'] = 'percentage';
                        $rowData['coverage_value'] = $rowData['coverage_percentage'];
                    }
                    
                    // Validate required fields
                    if (empty($rowData['item_code'])) {
                        $results['skipped']++;
                        $results['errors'][] = [
                            'row' => $rowNumber,
                            'error' => 'Missing item_code'
                        ];
                        continue;
                    }
                    
                    // Validate coverage type
                    $validTypes = ['percentage', 'fixed_amount', 'full', 'excluded'];
                    if (!in_array($rowData['coverage_type'], $validTypes)) {
                        $results['skipped']++;
                        $results['errors'][] = [
                            'row' => $rowNumber,
                            'error' => sprintf(
                                'Invalid coverage_type: %s. Must be: %s',
                                $rowData['coverage_type'],
                                implode(', ', $validTypes)
                            )
                        ];
                        continue;
                    }
                    
                    // Validate item exists in system
                    if (!$this->validateItemExists($request->category, $rowData['item_code'])) {
                        $results['skipped']++;
                        $results['errors'][] = [
                            'row' => $rowNumber,
                            'error' => sprintf('Item code %s not found in system', $rowData['item_code'])
                        ];
                        continue;
                    }
                    
                    // Process coverage based on type
                    $coverageData = $this->processCoverageType(
                        $rowData['coverage_type'],
                        $rowData['coverage_value']
                    );
                    
                    // Create or update rule
                    $rule = InsuranceCoverageRule::updateOrCreate(
                        [
                            'insurance_plan_id' => $plan->id,
                            'coverage_category' => $request->category,
                            'item_code' => $rowData['item_code']
                        ],
                        [
                            'item_description' => $rowData['item_name'] ?? null,
                            'coverage_type' => $rowData['coverage_type'],
                            'coverage_value' => $coverageData['coverage_value'],
                            'patient_copay_percentage' => $coverageData['copay_percentage'],
                            'is_covered' => $coverageData['is_covered'],
                            'is_active' => true,
                            'effective_from' => now(),
                            'notes' => $rowData['notes'] ?? null
                        ]
                    );
                    
                    $rule->wasRecentlyCreated ? $results['created']++ : $results['updated']++;
                    
                } catch (\Exception $e) {
                    $results['skipped']++;
                    $results['errors'][] = [
                        'row' => $rowNumber,
                        'error' => $e->getMessage()
                    ];
                }
            }
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to process file: ' . $e->getMessage()
            ], 500);
        }
        
        return response()->json([
            'success' => true,
            'results' => $results
        ]);
    }
    
    /**
     * Validate that item exists in system inventory
     */
    private function validateItemExists(string $category, string $itemCode): bool
    {
        return match($category) {
            'drug' => \App\Models\Drug::where('code', $itemCode)->exists(),
            'lab' => \App\Models\LabTest::where('code', $itemCode)->exists(),
            'consultation', 'procedure' => \App\Models\Service::where('code', $itemCode)
                ->where('type', $category)
                ->exists(),
            default => false,
        };
    }
    
    /**
     * Process coverage type and return standardized data
     */
    private function processCoverageType(string $type, float $value): array
    {
        return match($type) {
            'percentage' => [
                'coverage_value' => $value,
                'copay_percentage' => 100 - $value,
                'is_covered' => true,
            ],
            'fixed_amount' => [
                'coverage_value' => $value,
                'copay_percentage' => 0, // Calculated dynamically based on item price
                'is_covered' => true,
            ],
            'full' => [
                'coverage_value' => 100,
                'copay_percentage' => 0,
                'is_covered' => true,
            ],
            'excluded' => [
                'coverage_value' => 0,
                'copay_percentage' => 100,
                'is_covered' => false,
            ],
        };
    }
}
```

### 3. Frontend Integration

**Component**: `resources/js/components/Insurance/BulkImportModal.tsx`

Updated to support template download and display enhanced instructions.

```typescript
import { useState } from 'react';
import { router } from '@inertiajs/react';
import axios from 'axios';

interface BulkImportModalProps {
    insurancePlanId: number;
    category: string;
    onClose: () => void;
    onSuccess: () => void;
}

export default function BulkImportModal({
    insurancePlanId,
    category,
    onClose,
    onSuccess
}: BulkImportModalProps) {
    const [file, setFile] = useState<File | null>(null);
    const [uploading, setUploading] = useState(false);
    const [downloading, setDownloading] = useState(false);
    const [results, setResults] = useState<any>(null);

    const handleDownloadTemplate = async () => {
        setDownloading(true);
        try {
            const response = await axios.get(
                `/admin/insurance/plans/${insurancePlanId}/coverage-rules/template/${category}`,
                { responseType: 'blob' }
            );
            
            const url = window.URL.createObjectURL(new Blob([response.data]));
            const link = document.createElement('a');
            link.href = url;
            link.setAttribute('download', `coverage_template_${category}_${new Date().toISOString().split('T')[0]}.xlsx`);
            document.body.appendChild(link);
            link.click();
            link.remove();
        } catch (error) {
            console.error('Failed to download template:', error);
            alert('Failed to download template. Please try again.');
        } finally {
            setDownloading(false);
        }
    };

    const handleFileChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        if (e.target.files && e.target.files[0]) {
            setFile(e.target.files[0]);
            setResults(null);
        }
    };

    const handleUpload = async () => {
        if (!file) return;

        setUploading(true);
        const formData = new FormData();
        formData.append('file', file);
        formData.append('category', category);

        try {
            const response = await axios.post(
                `/admin/insurance/plans/${insurancePlanId}/coverage-rules/import`,
                formData,
                {
                    headers: { 'Content-Type': 'multipart/form-data' }
                }
            );

            setResults(response.data.results);
            
            if (response.data.results.errors.length === 0) {
                setTimeout(() => {
                    onSuccess();
                    onClose();
                }, 2000);
            }
        } catch (error) {
            console.error('Import failed:', error);
            alert('Import failed. Please check your file and try again.');
        } finally {
            setUploading(false);
        }
    };

    return (
        <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
            <div className="bg-white dark:bg-gray-800 rounded-lg p-6 max-w-2xl w-full mx-4 max-h-[90vh] overflow-y-auto">
                <h2 className="text-2xl font-bold mb-4">Bulk Import Coverage Rules</h2>
                
                <div className="space-y-4">
                    {/* Instructions */}
                    <div className="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-4">
                        <h3 className="font-semibold mb-2">New: Pre-populated Templates!</h3>
                        <ul className="text-sm space-y-1 list-disc list-inside">
                            <li>Download template with ALL items already filled in</li>
                            <li>Just edit coverage_type and coverage_value columns</li>
                            <li>Supports: percentage, fixed_amount, full, excluded</li>
                            <li>No manual data entry required!</li>
                        </ul>
                    </div>

                    {/* Download Template Button */}
                    <button
                        onClick={handleDownloadTemplate}
                        disabled={downloading}
                        className="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-3 px-4 rounded-lg disabled:opacity-50"
                    >
                        {downloading ? 'Downloading...' : '📥 Download Pre-populated Template'}
                    </button>

                    <div className="border-t pt-4">
                        <h3 className="font-semibold mb-2">Upload Edited Template</h3>
                        
                        {/* File Input */}
                        <input
                            type="file"
                            accept=".csv,.xlsx,.xls"
                            onChange={handleFileChange}
                            className="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100"
                        />

                        {file && (
                            <button
                                onClick={handleUpload}
                                disabled={uploading}
                                className="mt-4 w-full bg-green-600 hover:bg-green-700 text-white font-semibold py-2 px-4 rounded-lg disabled:opacity-50"
                            >
                                {uploading ? 'Uploading...' : 'Upload and Import'}
                            </button>
                        )}
                    </div>

                    {/* Results */}
                    {results && (
                        <div className="border-t pt-4">
                            <h3 className="font-semibold mb-2">Import Results</h3>
                            <div className="space-y-2">
                                <div className="flex justify-between">
                                    <span>Created:</span>
                                    <span className="font-semibold text-green-600">{results.created}</span>
                                </div>
                                <div className="flex justify-between">
                                    <span>Updated:</span>
                                    <span className="font-semibold text-blue-600">{results.updated}</span>
                                </div>
                                <div className="flex justify-between">
                                    <span>Skipped:</span>
                                    <span className="font-semibold text-red-600">{results.skipped}</span>
                                </div>

                                {results.errors.length > 0 && (
                                    <div className="mt-4">
                                        <h4 className="font-semibold text-red-600 mb-2">Errors:</h4>
                                        <div className="max-h-40 overflow-y-auto space-y-1">
                                            {results.errors.map((error: any, index: number) => (
                                                <div key={index} className="text-sm text-red-600">
                                                    Row {error.row}: {error.error}
                                                </div>
                                            ))}
                                        </div>
                                    </div>
                                )}
                            </div>
                        </div>
                    )}

                    {/* Close Button */}
                    <button
                        onClick={onClose}
                        className="w-full bg-gray-200 hover:bg-gray-300 dark:bg-gray-700 dark:hover:bg-gray-600 text-gray-800 dark:text-gray-200 font-semibold py-2 px-4 rounded-lg"
                    >
                        Close
                    </button>
                </div>
            </div>
        </div>
    );
}
```

### 4. Routes

**File**: `routes/insurance.php`

Add new route for template download.

```php
// Template download
Route::get(
    '/plans/{plan}/coverage-rules/template/{category}',
    [InsuranceCoverageImportController::class, 'downloadTemplate']
)->name('insurance.coverage.template');

// Import (existing route, updated controller)
Route::post(
    '/plans/{plan}/coverage-rules/import',
    [InsuranceCoverageImportController::class, 'import']
)->name('insurance.coverage.import');
```

## Data Flow

### Template Generation Flow

```
1. User clicks "Download Template" for Drugs category
   ↓
2. Frontend calls: GET /admin/insurance/plans/{id}/coverage-rules/template/drug
   ↓
3. Controller authorizes user and validates category
   ↓
4. CoverageExceptionTemplate queries:
   - All drugs from Drug model
   - Existing specific rules for this plan
   - General rule for default values
   ↓
5. PrePopulatedDataSheet maps each drug to row:
   - item_code: drug.code
   - item_name: drug.name
   - current_price: drug.price
   - coverage_type: existing_rule.type OR general_rule.type OR 'percentage'
   - coverage_value: existing_rule.value OR general_rule.value OR 80
   - notes: existing_rule.notes OR ''
   ↓
6. Excel file generated with Instructions + Data sheets
   ↓
7. File downloaded to user's computer
```

### Import Processing Flow

```
1. User uploads edited template
   ↓
2. Frontend calls: POST /admin/insurance/plans/{id}/coverage-rules/import
   ↓
3. Controller validates file format and category
   ↓
4. Parse Excel/CSV file, skip header row
   ↓
5. For each row:
   a. Validate coverage_type (percentage/fixed_amount/full/excluded)
   b. Validate item_code exists in system
   c. Process coverage type to get standardized values
   d. Create or update InsuranceCoverageRule
   e. Track created/updated/skipped counts
   f. Collect errors with row numbers
   ↓
6. Return results summary with errors
   ↓
7. Frontend displays results
```

## Error Handling

### Template Generation Errors

| Error | Handling |
|-------|----------|
| Invalid category | Return 400 error with message |
| Unauthorized access | Return 403 error |
| No items in category | Generate empty template with instructions |
| Database query fails | Log error, return 500 |

### Import Processing Errors

| Error | Handling |
|-------|----------|
| Invalid file format | Return validation error |
| Invalid coverage_type | Skip row, add to errors with row number |
| Item code not found | Skip row, add to errors with row number |
| Missing required field | Skip row, add to errors with row number |
| Database save fails | Skip row, add to errors with row number |

## Testing Strategy

### Unit Tests

1. **CoverageExceptionTemplate**
   - Test queries correct items for each category
   - Test pre-fills existing rule values
   - Test falls back to general rule values
   - Test uses defaults when no rules exist

2. **Import Controller**
   - Test processCoverageType() for each type
   - Test validateItemExists() for each category
   - Test backward compatibility with old format

### Feature Tests

1. **Template Download**
   - Test downloads template with all items
   - Test pre-fills existing coverage values
   - Test requires authorization
   - Test validates category

2. **Import Processing**
   - Test imports percentage coverage
   - Test imports fixed_amount coverage
   - Test imports full coverage
   - Test imports excluded coverage
   - Test validates coverage types
   - Test validates item codes
   - Test provides detailed error messages
   - Test backward compatibility with old format

### Integration Tests

1. **Complete Workflow**
   - Download template
   - Edit coverage values
   - Upload template
   - Verify rules created correctly
   - Verify coverage calculations use new rules

## Performance Considerations

### Template Generation
- Query optimization: Use select() to limit columns
- Pagination: Not needed, templates are one-time downloads
- Caching: Cache general rule lookup per plan

### Import Processing
- Batch processing: Process rows in chunks of 100
- Transaction: Wrap imports in database transaction
- Memory: Use generators for large files

## Security Considerations

1. **Authorization**: Verify user can manage insurance plan
2. **File Validation**: Strict MIME type checking
3. **Input Validation**: Validate all coverage types and values
4. **SQL Injection**: Use Eloquent ORM, no raw queries
5. **File Size**: Limit upload size to 5MB

## Deployment Plan

1. Deploy new CoverageExceptionTemplate class
2. Deploy updated import controller
3. Deploy frontend changes
4. Add new route
5. Test with sample data
6. Train administrators on new workflow
7. Monitor for errors in first week
