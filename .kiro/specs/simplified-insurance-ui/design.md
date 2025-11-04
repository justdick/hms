# Design Document

## Overview

This design reimagines the insurance coverage configuration interface to be intuitive, fast, and task-focused. The redesign maintains the existing two-tier system (general defaults + item-specific exceptions) but presents it in a way that matches how administrators think about insurance coverage.

**Core Design Philosophy:**
- **Progressive Disclosure**: Show simple options first, advanced features when needed
- **Visual First**: Use cards, colors, and icons instead of tables and forms
- **Inline Actions**: Edit directly where you see the data
- **Smart Defaults**: Provide presets and templates for common scenarios
- **Contextual Help**: Guide users without overwhelming them

## Architecture

### High-Level Component Structure

```
Insurance Plan Management
├── Plan Setup Wizard (New Plans)
│   ├── Step 1: Plan Details
│   ├── Step 2: Coverage Presets
│   └── Step 3: Review & Create
│
├── Coverage Dashboard (Main View)
│   ├── Category Cards (6 cards)
│   │   ├── Visual Coverage Indicator
│   │   ├── Inline Edit
│   │   └── Exception Count Badge
│   ├── Recent Items Panel
│   └── Quick Actions Menu
│
├── Category Detail View (Expanded)
│   ├── Default Rule Section
│   ├── Exceptions List
│   └── Add Exception Button
│
└── Exception Management
    ├── Simplified Add Modal
    ├── Bulk Import Interface
    └── Exception History
```

### Data Flow

```
User Action → UI Component → Controller → Service Layer → Database
                ↓
         Real-time Validation
                ↓
         Optimistic UI Update
                ↓
         Success/Error Feedback
```



## Components and Interfaces

### 1. Plan Setup Wizard

**Route**: `/admin/insurance/plans/create`

**Component**: `PlanSetupWizard.tsx`

```typescript
interface WizardStep {
    step: 1 | 2 | 3;
    title: string;
    isComplete: boolean;
}

interface CoveragePreset {
    id: string;
    name: string;
    description: string;
    coverages: {
        consultation: number;
        drug: number;
        lab: number;
        procedure: number;
        ward: number;
        nursing: number;
    };
}

// Step 1: Plan Details
// - Plan Name, Plan Code, Provider Selection
// - Simple form, no complexity

// Step 2: Coverage Presets
// - Show preset cards: NHIS Standard, Corporate Premium, Basic, Custom
// - Each card shows preview of coverage percentages
// - Selected preset pre-fills the coverage inputs
// - User can modify any percentage

// Step 3: Review & Create
// - Summary of plan details and coverage
// - "Create Plan" button
// - Creates plan + all 6 default coverage rules in one transaction
```

**Backend**: `InsurancePlanController@store`
- Accepts plan details + coverage array
- Creates plan and all default rules in a database transaction
- Returns to coverage dashboard



### 2. Coverage Dashboard

**Route**: `/admin/insurance/plans/{plan}/coverage`

**Component**: `CoverageDashboard.tsx`

```typescript
interface CategoryCard {
    category: string;
    label: string;
    icon: React.ComponentType;
    defaultCoverage: number | null;
    exceptionCount: number;
    color: 'green' | 'yellow' | 'red' | 'gray';
}

// Visual Layout:
// ┌─────────────────────────────────────────────────────┐
// │  Gold Plan - VET Insurance                          │
// │  [Quick Actions ▼]  [Import Exceptions]            │
// ├─────────────────────────────────────────────────────┤
// │  ┌──────────┐  ┌──────────┐  ┌──────────┐         │
// │  │ 💊 Drugs │  │ 🔬 Labs  │  │ 👨‍⚕️ Consult│         │
// │  │   80%    │  │   90%    │  │   70%    │         │
// │  │ 3 except │  │ 1 except │  │ No except│         │
// │  └──────────┘  └──────────┘  └──────────┘         │
// │  ┌──────────┐  ┌──────────┐  ┌──────────┐         │
// │  │ 🏥 Proced│  │ 🛏️ Ward   │  │ 👩‍⚕️ Nursing│         │
// │  │   85%    │  │   100%   │  │   75%    │         │
// │  │ No except│  │ No except│  │ 2 except │         │
// │  └──────────┘  └──────────┘  └──────────┘         │
// └─────────────────────────────────────────────────────┘

// Color Coding:
// - Green: 80-100% coverage
// - Yellow: 50-79% coverage
// - Red: 1-49% coverage
// - Gray: Not configured

// Interactions:
// - Click card → Expand to show details
// - Click percentage → Inline edit
// - Hover → Show tooltip with summary
```

**Backend**: `InsurancePlanController@showCoverage`
- Returns plan with coverage rules grouped by category
- Includes exception counts per category
- Includes recent items (last 30 days)



### 3. Inline Edit Component

**Component**: `InlinePercentageEdit.tsx`

```typescript
interface InlineEditProps {
    value: number;
    onSave: (newValue: number) => Promise<void>;
    min?: number;
    max?: number;
}

// Behavior:
// 1. Display: Shows "80%" as clickable text
// 2. Click: Transforms to input field with current value selected
// 3. Edit: User types new value
// 4. Save: Press Enter or click away
// 5. Validation: Check 0-100 range
// 6. Feedback: Green checkmark animation on success
// 7. Error: Red shake animation + revert on failure

// Implementation:
// - Uses Inertia router.patch for updates
// - Optimistic UI update
// - Rollback on error
```

**Backend**: `InsuranceCoverageRuleController@quickUpdate`
- New endpoint for inline updates
- Validates percentage range
- Updates single field
- Returns updated rule



### 4. Simplified Exception Modal

**Component**: `AddExceptionModal.tsx`

```typescript
interface AddExceptionModalProps {
    planId: number;
    category: string;
    onSuccess: () => void;
}

// Simplified Layout:
// ┌─────────────────────────────────────────────┐
// │  Add Coverage Exception                     │
// ├─────────────────────────────────────────────┤
// │  Search for item:                           │
// │  [🔍 Search by name or code...        ]     │
// │                                             │
// │  ┌─────────────────────────────────────┐   │
// │  │ CBC001 - Complete Blood Count       │   │
// │  │ Current price: $45.00                │   │
// │  └─────────────────────────────────────┘   │
// │                                             │
// │  Coverage for this item:                    │
// │  ○ Percentage  ● Fixed  ○ Full  ○ None     │
// │                                             │
// │  [100] %                                    │
// │                                             │
// │  Preview:                                   │
// │  Insurance pays: $45.00 (100%)              │
// │  Patient pays: $0.00 (0%)                   │
// │                                             │
// │  Notes (optional):                          │
// │  [Essential diagnostic test...        ]     │
// │                                             │
// │  [Cancel]  [Add Exception]                  │
// └─────────────────────────────────────────────┘

// Only 3 required fields:
// 1. Item (search and select)
// 2. Coverage type + value
// 3. Notes (optional)

// Auto-calculations:
// - Copay percentage calculated automatically
// - Preview shows exact amounts
```

**Backend**: Uses existing `InsuranceCoverageRuleController@store`
- No changes needed to backend
- Frontend just sends simpler data



### 5. Coverage Presets System

**File**: `app/Services/CoveragePresetService.php`

```php
class CoveragePresetService
{
    public function getPresets(): array
    {
        return [
            [
                'id' => 'nhis_standard',
                'name' => 'NHIS Standard',
                'description' => 'Standard National Health Insurance coverage',
                'coverages' => [
                    'consultation' => 70,
                    'drug' => 80,
                    'lab' => 90,
                    'procedure' => 75,
                    'ward' => 100,
                    'nursing' => 80,
                ],
            ],
            [
                'id' => 'corporate_premium',
                'name' => 'Corporate Premium',
                'description' => 'High coverage for corporate clients',
                'coverages' => [
                    'consultation' => 90,
                    'drug' => 90,
                    'lab' => 100,
                    'procedure' => 90,
                    'ward' => 100,
                    'nursing' => 90,
                ],
            ],
            [
                'id' => 'basic',
                'name' => 'Basic Coverage',
                'description' => 'Minimal coverage plan',
                'coverages' => [
                    'consultation' => 50,
                    'drug' => 60,
                    'lab' => 70,
                    'procedure' => 50,
                    'ward' => 80,
                    'nursing' => 60,
                ],
            ],
            [
                'id' => 'custom',
                'name' => 'Custom',
                'description' => 'Configure your own coverage percentages',
                'coverages' => null, // User fills in
            ],
        ];
    }
}
```

**Frontend**: `CoveragePresetSelector.tsx`
- Displays preset cards with preview
- User selects one
- Fills in coverage inputs
- User can still modify before saving



### 6. Recent Items Monitoring

**Component**: `RecentItemsPanel.tsx`

```typescript
interface RecentItem {
    id: number;
    code: string;
    name: string;
    category: string;
    price: number;
    addedDate: string;
    coverageStatus: 'default' | 'exception' | 'not_covered';
    isExpensive: boolean; // price > threshold
}

// Layout:
// ┌─────────────────────────────────────────────┐
// │  Recently Added Items (Last 30 Days)        │
// ├─────────────────────────────────────────────┤
// │  ⚠️  Advanced MRI Scan                      │
// │      $850.00 • Uses default 90% coverage    │
// │      [Add Exception]                        │
// ├─────────────────────────────────────────────┤
// │  ✓  Basic X-Ray                             │
// │      $45.00 • Uses default 90% coverage     │
// │      [Add Exception]                        │
// └─────────────────────────────────────────────┘

// Features:
// - Shows items added in last 30 days
// - Highlights expensive items (⚠️ icon)
// - Shows current coverage status
// - One-click "Add Exception" button
// - Dismissible after review
```

**Backend**: `InsurancePlanController@getRecentItems`

```php
public function getRecentItems(InsurancePlan $plan): array
{
    $threshold = 500; // Expensive item threshold
    $recentItems = [];
    
    // Get recent drugs
    $recentDrugs = Drug::where('created_at', '>=', now()->subDays(30))
        ->get()
        ->map(fn($drug) => [
            'id' => $drug->id,
            'code' => $drug->drug_code,
            'name' => $drug->name,
            'category' => 'drug',
            'price' => $drug->unit_price,
            'addedDate' => $drug->created_at,
            'isExpensive' => $drug->unit_price > $threshold,
            'coverageStatus' => $this->getCoverageStatus($plan, 'drug', $drug->drug_code),
        ]);
    
    // Similar for labs, procedures, etc.
    
    return $recentItems;
}

private function getCoverageStatus($plan, $category, $itemCode): string
{
    $hasException = InsuranceCoverageRule::where('insurance_plan_id', $plan->id)
        ->where('coverage_category', $category)
        ->where('item_code', $itemCode)
        ->exists();
    
    if ($hasException) {
        return 'exception';
    }
    
    $hasDefault = InsuranceCoverageRule::where('insurance_plan_id', $plan->id)
        ->where('coverage_category', $category)
        ->whereNull('item_code')
        ->exists();
    
    return $hasDefault ? 'default' : 'not_covered';
}
```



### 7. Bulk Import with Template

**Component**: `BulkImportModal.tsx`

```typescript
// Step 1: Download Template
// - Provides Excel template with:
//   - Column headers: item_code, item_name, coverage_percentage, notes
//   - Example rows
//   - Instructions sheet

// Step 2: Upload Filled Template
// - Drag & drop or file picker
// - Validates file format

// Step 3: Preview & Validate
// - Shows all rows in a table
// - Highlights errors in red
// - Shows what will be created/updated

// Step 4: Confirm Import
// - Creates all valid exceptions
// - Shows summary: "45 added, 2 skipped"
```

**Backend**: `InsuranceCoverageImportController.php`

```php
class InsuranceCoverageImportController extends Controller
{
    public function downloadTemplate(InsurancePlan $plan, string $category)
    {
        // Generate Excel template with:
        // - Instructions sheet
        // - Data sheet with headers and examples
        // - Validation rules
        
        return Excel::download(
            new CoverageExceptionTemplate($category),
            "coverage_exceptions_{$category}_template.xlsx"
        );
    }
    
    public function preview(Request $request, InsurancePlan $plan)
    {
        $file = $request->file('file');
        $category = $request->input('category');
        
        // Parse file
        $rows = Excel::toArray(new CoverageExceptionImport, $file)[0];
        
        // Validate each row
        $validated = [];
        $errors = [];
        
        foreach ($rows as $index => $row) {
            $validation = $this->validateRow($row, $category, $plan);
            
            if ($validation['valid']) {
                $validated[] = $validation['data'];
            } else {
                $errors[] = [
                    'row' => $index + 2,
                    'errors' => $validation['errors'],
                ];
            }
        }
        
        return response()->json([
            'valid_rows' => $validated,
            'errors' => $errors,
            'summary' => [
                'total' => count($rows),
                'valid' => count($validated),
                'invalid' => count($errors),
            ],
        ]);
    }
    
    public function import(Request $request, InsurancePlan $plan)
    {
        $validatedRows = $request->input('validated_rows');
        $category = $request->input('category');
        
        $created = 0;
        $updated = 0;
        
        foreach ($validatedRows as $row) {
            $rule = InsuranceCoverageRule::updateOrCreate(
                [
                    'insurance_plan_id' => $plan->id,
                    'coverage_category' => $category,
                    'item_code' => $row['item_code'],
                ],
                [
                    'item_description' => $row['item_name'],
                    'coverage_type' => 'percentage',
                    'coverage_value' => $row['coverage_percentage'],
                    'patient_copay_percentage' => 100 - $row['coverage_percentage'],
                    'is_covered' => $row['coverage_percentage'] > 0,
                    'is_active' => true,
                    'notes' => $row['notes'] ?? null,
                ]
            );
            
            $rule->wasRecentlyCreated ? $created++ : $updated++;
        }
        
        return response()->json([
            'success' => true,
            'created' => $created,
            'updated' => $updated,
        ]);
    }
}
```



### 8. Notification System for New Items

**File**: `app/Observers/ItemObserver.php`

```php
// Register observers for Drug, LabService, etc.

class DrugObserver
{
    public function created(Drug $drug): void
    {
        // Find all insurance plans with default coverage for drugs
        $plansWithDrugCoverage = InsuranceCoverageRule::whereNull('item_code')
            ->where('coverage_category', 'drug')
            ->where('is_active', true)
            ->with('insurancePlan')
            ->get();
        
        foreach ($plansWithDrugCoverage as $rule) {
            // Check if plan requires explicit approval
            if ($rule->insurancePlan->require_explicit_approval_for_new_items) {
                // Don't notify, item won't be covered until reviewed
                continue;
            }
            
            // Create notification for insurance admins
            $admins = User::role('insurance_admin')->get();
            
            foreach ($admins as $admin) {
                $admin->notify(new NewItemAddedNotification(
                    item: $drug,
                    category: 'drug',
                    plan: $rule->insurancePlan,
                    defaultCoverage: $rule->coverage_value
                ));
            }
        }
    }
}
```

**Notification**: `NewItemAddedNotification.php`

```php
class NewItemAddedNotification extends Notification
{
    public function toArray($notifiable): array
    {
        return [
            'type' => 'new_item_coverage',
            'message' => "New {$this->category} '{$this->item->name}' will be covered at {$this->defaultCoverage}% by default",
            'item_id' => $this->item->id,
            'item_code' => $this->item->code,
            'item_name' => $this->item->name,
            'category' => $this->category,
            'plan_id' => $this->plan->id,
            'plan_name' => $this->plan->plan_name,
            'default_coverage' => $this->defaultCoverage,
            'actions' => [
                'add_exception' => route('admin.insurance.coverage-rules.create', [
                    'plan_id' => $this->plan->id,
                    'category' => $this->category,
                    'item_code' => $this->item->code,
                ]),
                'keep_default' => route('admin.notifications.dismiss', $this->id),
            ],
        ];
    }
}
```



## Database Schema Changes

### New Column for Insurance Plans

```php
// Migration: add_require_explicit_approval_to_insurance_plans_table.php

Schema::table('insurance_plans', function (Blueprint $table) {
    $table->boolean('require_explicit_approval_for_new_items')
        ->default(false)
        ->after('requires_referral');
});
```

**Purpose**: Allows plans to opt-in to requiring explicit approval for new items instead of using default coverage.

### No Other Schema Changes Required

The existing `insurance_coverage_rules` table already supports everything we need:
- `item_code` NULL = general rule
- `item_code` NOT NULL = specific exception
- All coverage types already supported

## UI/UX Design Patterns

### Color Coding System

```typescript
const getCoverageColor = (percentage: number | null): string => {
    if (percentage === null) return 'gray'; // Not configured
    if (percentage >= 80) return 'green';   // High coverage
    if (percentage >= 50) return 'yellow';  // Medium coverage
    return 'red';                           // Low coverage
};
```

### Icon System

```typescript
const categoryIcons = {
    consultation: '👨‍⚕️',
    drug: '💊',
    lab: '🔬',
    procedure: '🏥',
    ward: '🛏️',
    nursing: '👩‍⚕️',
};
```

### Animation Feedback

```typescript
// Success animation
const successAnimation = {
    initial: { scale: 1 },
    animate: { scale: [1, 1.2, 1] },
    transition: { duration: 0.3 },
};

// Error shake animation
const errorAnimation = {
    initial: { x: 0 },
    animate: { x: [-10, 10, -10, 10, 0] },
    transition: { duration: 0.4 },
};
```



## Routes

```php
// routes/web.php

// Plan Setup Wizard
Route::get('/admin/insurance/plans/create', [InsurancePlanController::class, 'create'])
    ->name('admin.insurance.plans.create');
Route::post('/admin/insurance/plans', [InsurancePlanController::class, 'store'])
    ->name('admin.insurance.plans.store');

// Coverage Dashboard
Route::get('/admin/insurance/plans/{plan}/coverage', [InsurancePlanController::class, 'showCoverage'])
    ->name('admin.insurance.plans.coverage');

// Quick Update (Inline Edit)
Route::patch('/admin/insurance/coverage-rules/{rule}/quick-update', [InsuranceCoverageRuleController::class, 'quickUpdate'])
    ->name('admin.insurance.coverage-rules.quick-update');

// Recent Items
Route::get('/admin/insurance/plans/{plan}/recent-items', [InsurancePlanController::class, 'getRecentItems'])
    ->name('admin.insurance.plans.recent-items');

// Bulk Import
Route::get('/admin/insurance/plans/{plan}/coverage/import-template/{category}', [InsuranceCoverageImportController::class, 'downloadTemplate'])
    ->name('admin.insurance.coverage.import-template');
Route::post('/admin/insurance/plans/{plan}/coverage/import-preview', [InsuranceCoverageImportController::class, 'preview'])
    ->name('admin.insurance.coverage.import-preview');
Route::post('/admin/insurance/plans/{plan}/coverage/import', [InsuranceCoverageImportController::class, 'import'])
    ->name('admin.insurance.coverage.import');

// Coverage Presets
Route::get('/admin/insurance/coverage-presets', [CoveragePresetController::class, 'index'])
    ->name('admin.insurance.coverage-presets');
```

## Error Handling

### Validation Rules

```php
// Quick Update Validation
$request->validate([
    'coverage_value' => 'required|numeric|min:0|max:100',
]);

// Exception Creation Validation
$request->validate([
    'item_code' => 'required|exists:drugs,drug_code', // or labs, etc.
    'coverage_type' => 'required|in:percentage,fixed,full,excluded',
    'coverage_value' => 'required_unless:coverage_type,full,excluded|numeric|min:0',
]);

// Bulk Import Validation
foreach ($rows as $row) {
    if (!$this->itemExists($row['item_code'], $category)) {
        $errors[] = "Item code {$row['item_code']} not found";
    }
    if ($row['coverage_percentage'] < 0 || $row['coverage_percentage'] > 100) {
        $errors[] = "Coverage percentage must be between 0 and 100";
    }
}
```

### User-Friendly Error Messages

```typescript
const errorMessages = {
    'coverage_value.required': 'Please enter a coverage percentage',
    'coverage_value.min': 'Coverage cannot be negative',
    'coverage_value.max': 'Coverage cannot exceed 100%',
    'item_code.exists': 'This item does not exist in the system',
    'duplicate_exception': 'This item already has a coverage exception',
};
```



## Testing Strategy

### Unit Tests

1. **Coverage Preset Service**
   - Test preset data structure
   - Test preset retrieval
   - Test custom preset handling

2. **Coverage Color Calculation**
   - Test color for null (gray)
   - Test color for high coverage (green)
   - Test color for medium coverage (yellow)
   - Test color for low coverage (red)

3. **Validation Logic**
   - Test percentage range validation
   - Test item existence validation
   - Test duplicate exception detection

### Feature Tests

1. **Plan Setup Wizard**
   - Test creating plan with preset
   - Test creating plan with custom coverage
   - Test all default rules are created
   - Test validation errors

2. **Coverage Dashboard**
   - Test dashboard displays all categories
   - Test exception counts are correct
   - Test color coding is applied
   - Test recent items are shown

3. **Inline Edit**
   - Test successful update
   - Test validation error handling
   - Test optimistic UI update
   - Test rollback on error

4. **Exception Management**
   - Test adding exception
   - Test duplicate prevention
   - Test item search
   - Test coverage calculation preview

5. **Bulk Import**
   - Test template download
   - Test successful import
   - Test validation errors
   - Test partial import (some valid, some invalid)

### Browser Tests (Pest v4)

```php
it('allows quick setup of insurance plan', function () {
    $this->actingAs(User::factory()->insuranceAdmin()->create());
    
    $page = visit('/admin/insurance/plans/create');
    
    $page->assertSee('Plan Setup Wizard')
        ->fill('plan_name', 'Test Plan')
        ->fill('plan_code', 'TEST001')
        ->click('Next')
        ->click('NHIS Standard') // Select preset
        ->click('Next')
        ->assertSee('Review & Create')
        ->assertSee('80%') // Drug coverage from preset
        ->click('Create Plan')
        ->assertSee('Plan created successfully');
    
    // Verify default rules were created
    expect(InsuranceCoverageRule::where('insurance_plan_id', $plan->id)->count())
        ->toBe(6);
});

it('allows inline editing of coverage percentage', function () {
    $plan = InsurancePlan::factory()->create();
    $rule = InsuranceCoverageRule::factory()->create([
        'insurance_plan_id' => $plan->id,
        'coverage_category' => 'drug',
        'item_code' => null,
        'coverage_value' => 80,
    ]);
    
    $this->actingAs(User::factory()->insuranceAdmin()->create());
    
    $page = visit("/admin/insurance/plans/{$plan->id}/coverage");
    
    $page->click('80%') // Click to edit
        ->fill('coverage_value', '90')
        ->press('Enter')
        ->assertSee('90%')
        ->assertNoJavascriptErrors();
    
    // Verify database was updated
    expect($rule->fresh()->coverage_value)->toBe(90.0);
});
```



## Performance Considerations

### Caching Strategy

```php
// Cache coverage rules per plan
Cache::remember("plan_{$planId}_coverage_dashboard", 3600, function () use ($planId) {
    return [
        'categories' => $this->getCategorySummaries($planId),
        'recent_items' => $this->getRecentItems($planId),
    ];
});

// Clear cache on updates
class InsuranceCoverageRule extends Model
{
    protected static function booted()
    {
        static::saved(function ($rule) {
            Cache::forget("plan_{$rule->insurance_plan_id}_coverage_dashboard");
        });
        
        static::deleted(function ($rule) {
            Cache::forget("plan_{$rule->insurance_plan_id}_coverage_dashboard");
        });
    }
}
```

### Database Optimization

```sql
-- Existing indexes are sufficient
-- But consider adding for recent items query:
CREATE INDEX idx_drugs_created_at ON drugs(created_at);
CREATE INDEX idx_lab_services_created_at ON lab_services(created_at);
```

### Frontend Optimization

```typescript
// Lazy load exception lists
const ExceptionList = lazy(() => import('./ExceptionList'));

// Debounce search input
const debouncedSearch = useMemo(
    () => debounce((query) => searchItems(query), 300),
    []
);

// Virtualize long exception lists
import { FixedSizeList } from 'react-window';
```

## Security Considerations

1. **Authorization**: Only insurance admins can manage coverage rules
2. **Validation**: All inputs validated on both client and server
3. **Audit Trail**: All changes logged with user and timestamp
4. **Rate Limiting**: Bulk import limited to prevent abuse
5. **File Upload**: Validate file type and size for imports

## Migration Strategy

### Phase 1: Deploy New UI (Parallel)
- Deploy new routes and components
- Keep old UI accessible
- Add feature flag to switch between old/new UI
- Train administrators on new UI

### Phase 2: Gradual Rollout
- Enable new UI for pilot users
- Gather feedback
- Fix issues
- Enable for all users

### Phase 3: Deprecate Old UI
- Remove old routes and components
- Clean up unused code
- Update documentation

### Rollback Plan
- Feature flag allows instant rollback to old UI
- No database changes means no data migration needed
- Old UI remains functional throughout

## Accessibility

- All interactive elements keyboard accessible
- ARIA labels for screen readers
- Color coding supplemented with icons/text
- Focus indicators visible
- Error messages announced to screen readers

## Documentation

### User Guide Topics
1. Setting up a new insurance plan
2. Using coverage presets
3. Adding coverage exceptions
4. Bulk importing exceptions
5. Monitoring recent items
6. Understanding coverage calculations

### Admin Guide Topics
1. Configuring coverage presets
2. Setting up notifications
3. Managing user permissions
4. Troubleshooting common issues
