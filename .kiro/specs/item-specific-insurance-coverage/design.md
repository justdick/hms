# Design Document

## Overview

This design implements a two-tier insurance coverage system that supports both general default rules and item-specific override rules. The system maintains backward compatibility with existing general rules while adding the ability to define precise coverage for individual drugs, lab tests, and services.

## Architecture

### High-Level Design

```
┌─────────────────────────────────────────────────────────────┐
│                    Insurance Plan                           │
│  ┌───────────────────────────────────────────────────────┐  │
│  │         Coverage Rules (insurance_coverage_rules)     │  │
│  │                                                       │  │
│  │  General Rules (item_code = NULL)                    │  │
│  │  ├─ Drugs: 80% coverage, 20% copay                   │  │
│  │  ├─ Labs: 90% coverage, 10% copay                    │  │
│  │  └─ Consultations: 70% coverage, 30% copay           │  │
│  │                                                       │  │
│  │  Item-Specific Overrides (item_code != NULL)         │  │
│  │  ├─ Paracetamol (DRUG001): 100% coverage, 0% copay   │  │
│  │  ├─ Insulin (DRUG045): 100% coverage, 0% copay       │  │
│  │  ├─ CBC Test (LAB012): 100% coverage, 0% copay       │  │
│  │  └─ Cosmetic Drug (DRUG999): 0% coverage, 100% copay │  │
│  └───────────────────────────────────────────────────────┘  │
└─────────────────────────────────────────────────────────────┘

Coverage Lookup Flow:
1. Service requested → Get item code
2. Search for item-specific rule (WHERE item_code = 'DRUG001')
3. If found → Use specific rule
4. If not found → Search for general rule (WHERE item_code IS NULL)
5. If found → Use general rule
6. If not found → No coverage (0%)
```

### Key Design Principles

1. **Backward Compatibility**: Existing general rules (with `item_code = NULL`) continue to work unchanged
2. **Specificity Wins**: Item-specific rules always take precedence over general rules
3. **Efficient Configuration**: Administrators set general rules once, then add overrides only as needed
4. **Centralized Management**: All coverage configuration happens in Insurance Plan section
5. **Separation of Concerns**: Drug/service inventory management is separate from insurance configuration

## Components and Interfaces

### 1. Database Schema (No Changes Required)

The existing `insurance_coverage_rules` table already supports this design:

```sql
CREATE TABLE insurance_coverage_rules (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    insurance_plan_id BIGINT UNSIGNED NOT NULL,
    coverage_category ENUM('consultation', 'drug', 'lab', 'procedure', 'ward', 'nursing'),
    item_code VARCHAR(191) NULL,  -- NULL = general rule, NOT NULL = specific rule
    item_description VARCHAR(191),
    is_covered BOOLEAN DEFAULT TRUE,
    coverage_type ENUM('percentage', 'fixed', 'full', 'excluded'),
    coverage_value DECIMAL(10,2),
    patient_copay_percentage DECIMAL(5,2),
    -- ... other fields
);
```

**Key Points:**
- `item_code = NULL` → General/default rule for the category
- `item_code = 'DRUG001'` → Specific rule for that drug
- Both can coexist for the same plan and category

### 2. Coverage Rule Service

**File**: `app/Services/InsuranceCoverageService.php`

```php
class InsuranceCoverageService
{
    /**
     * Get the applicable coverage rule for a specific item
     * Implements the hierarchy: specific rule > general rule > no coverage
     */
    public function getCoverageRule(
        int $insurancePlanId,
        string $category,
        ?string $itemCode = null
    ): ?InsuranceCoverageRule {
        // First, try to find item-specific rule
        if ($itemCode) {
            $specificRule = InsuranceCoverageRule::where('insurance_plan_id', $insurancePlanId)
                ->where('coverage_category', $category)
                ->where('item_code', $itemCode)
                ->where('is_active', true)
                ->whereDate('effective_from', '<=', now())
                ->where(function ($query) {
                    $query->whereNull('effective_to')
                          ->orWhereDate('effective_to', '>=', now());
                })
                ->first();
            
            if ($specificRule) {
                return $specificRule;
            }
        }
        
        // Fall back to general rule for category
        $generalRule = InsuranceCoverageRule::where('insurance_plan_id', $insurancePlanId)
            ->where('coverage_category', $category)
            ->whereNull('item_code')  // General rule
            ->where('is_active', true)
            ->whereDate('effective_from', '<=', now())
            ->where(function ($query) {
                $query->whereNull('effective_to')
                      ->orWhereDate('effective_to', '>=', now());
            })
            ->first();
        
        return $generalRule;
    }
    
    /**
     * Calculate coverage amounts for an item
     */
    public function calculateCoverage(
        int $insurancePlanId,
        string $category,
        string $itemCode,
        float $amount
    ): array {
        $rule = $this->getCoverageRule($insurancePlanId, $category, $itemCode);
        
        if (!$rule || !$rule->is_covered) {
            return [
                'is_covered' => false,
                'insurance_pays' => 0.00,
                'patient_pays' => $amount,
                'coverage_percentage' => 0,
                'rule_type' => 'none'
            ];
        }
        
        // Get insurance tariff if exists, otherwise use standard amount
        $tariff = $this->getInsuranceTariff($insurancePlanId, $category, $itemCode) ?? $amount;
        
        // Calculate based on coverage type
        $insurancePays = 0;
        $patientPays = 0;
        
        switch ($rule->coverage_type) {
            case 'full':
                $insurancePays = $tariff;
                $patientPays = 0;
                break;
            case 'percentage':
                $insurancePays = $tariff * ($rule->coverage_value / 100);
                $patientPays = $tariff - $insurancePays;
                break;
            case 'fixed':
                $insurancePays = min($rule->coverage_value, $tariff);
                $patientPays = $tariff - $insurancePays;
                break;
            case 'excluded':
                $insurancePays = 0;
                $patientPays = $tariff;
                break;
        }
        
        return [
            'is_covered' => true,
            'insurance_pays' => round($insurancePays, 2),
            'patient_pays' => round($patientPays, 2),
            'coverage_percentage' => $rule->coverage_value,
            'rule_type' => $rule->item_code ? 'specific' : 'general',
            'rule_id' => $rule->id
        ];
    }
}
```

### 3. Admin Interface Components

#### A. Coverage Rules Management Page

**Route**: `/admin/insurance/plans/{plan}/coverage-rules`

**Page Structure**:
```
┌─────────────────────────────────────────────────────────────┐
│  VET Insurance - Gold Plan - Coverage Rules                │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  [+ Add General Rule]  [+ Add Item Override]  [Import CSV] │
│                                                             │
│  ┌─ Drugs ──────────────────────────────────────────────┐  │
│  │                                                       │  │
│  │  General Default Rule:                               │  │
│  │  ✓ 80% Insurance Coverage, 20% Patient Copay        │  │
│  │  [Edit] [Delete]                                     │  │
│  │                                                       │  │
│  │  Item-Specific Overrides (3):                        │  │
│  │  ┌──────────────────────────────────────────────┐   │  │
│  │  │ Paracetamol 500mg (DRUG001)                  │   │  │
│  │  │ 100% Insurance | 0% Copay                    │   │  │
│  │  │ Override: General is 80%, this is 100%       │   │  │
│  │  │ [Edit] [Delete]                              │   │  │
│  │  └──────────────────────────────────────────────┘   │  │
│  │  ┌──────────────────────────────────────────────┐   │  │
│  │  │ Insulin (DRUG045)                            │   │  │
│  │  │ 100% Insurance | 0% Copay                    │   │  │
│  │  │ Override: General is 80%, this is 100%       │   │  │
│  │  │ [Edit] [Delete]                              │   │  │
│  │  └──────────────────────────────────────────────┘   │  │
│  │  [+ Add More Overrides]                              │  │
│  └───────────────────────────────────────────────────────┘  │
│                                                             │
│  ┌─ Lab Tests ───────────────────────────────────────────┐  │
│  │  General Default Rule:                               │  │
│  │  ✓ 90% Insurance Coverage, 10% Patient Copay        │  │
│  │  [Edit] [Delete]                                     │  │
│  │                                                       │  │
│  │  Item-Specific Overrides (1):                        │  │
│  │  ┌──────────────────────────────────────────────┐   │  │
│  │  │ Complete Blood Count (LAB012)                │   │  │
│  │  │ 100% Insurance | 0% Copay                    │   │  │
│  │  │ Override: General is 90%, this is 100%       │   │  │
│  │  │ [Edit] [Delete]                              │   │  │
│  │  └──────────────────────────────────────────────┘   │  │
│  └───────────────────────────────────────────────────────┘  │
└─────────────────────────────────────────────────────────────┘
```

#### B. Add/Edit Coverage Rule Modal

**Component**: `CoverageRuleForm.tsx`

```typescript
interface CoverageRuleFormProps {
    insurancePlanId: number;
    category: string;
    rule?: InsuranceCoverageRule;
    onSave: () => void;
}

// Form fields:
// 1. Rule Type: [General Default] or [Item-Specific Override]
// 2. If Item-Specific:
//    - Item Search: Searchable dropdown of drugs/labs/services
//    - Shows: Code, Name, Current Price
// 3. Coverage Type: [Percentage] [Fixed Amount] [Full Coverage] [Excluded]
// 4. Coverage Value: Input field (percentage or amount)
// 5. Patient Copay %: Auto-calculated or manual
// 6. Effective Dates: From/To
// 7. Notes: Text area
```

#### C. Item Search Component

**Component**: `ItemSearchSelect.tsx`

```typescript
// Searchable dropdown that queries:
// - Drugs table (for category='drug')
// - Lab tests table (for category='lab')
// - Services table (for category='consultation', 'procedure', etc.)

// Shows:
// - Item Code
// - Item Name/Description
// - Current Price
// - Whether it already has a specific rule
```

### 4. Bulk Import Feature

#### Import CSV Format

```csv
item_code,item_description,coverage_type,coverage_value,copay_percentage,notes
DRUG001,Paracetamol 500mg,percentage,100,0,Fully covered essential drug
DRUG045,Insulin,full,100,0,Diabetic medication
DRUG999,Cosmetic Cream,excluded,0,100,Not covered
LAB012,Complete Blood Count,percentage,100,0,Essential diagnostic test
```

#### Import Controller

**File**: `app/Http/Controllers/Admin/InsuranceCoverageImportController.php`

```php
class InsuranceCoverageImportController extends Controller
{
    public function import(Request $request, InsurancePlan $plan)
    {
        $request->validate([
            'file' => 'required|file|mimes:csv,xlsx',
            'category' => 'required|in:drug,lab,consultation,procedure,ward,nursing'
        ]);
        
        $results = [
            'created' => 0,
            'updated' => 0,
            'skipped' => 0,
            'errors' => []
        ];
        
        // Process CSV/Excel file
        $rows = $this->parseFile($request->file('file'));
        
        foreach ($rows as $index => $row) {
            try {
                // Validate item exists in system
                $itemExists = $this->validateItemExists(
                    $request->category,
                    $row['item_code']
                );
                
                if (!$itemExists) {
                    $results['skipped']++;
                    $results['errors'][] = [
                        'row' => $index + 2,
                        'error' => "Item code {$row['item_code']} not found in system"
                    ];
                    continue;
                }
                
                // Create or update rule
                $rule = InsuranceCoverageRule::updateOrCreate(
                    [
                        'insurance_plan_id' => $plan->id,
                        'coverage_category' => $request->category,
                        'item_code' => $row['item_code']
                    ],
                    [
                        'item_description' => $row['item_description'],
                        'coverage_type' => $row['coverage_type'],
                        'coverage_value' => $row['coverage_value'],
                        'patient_copay_percentage' => $row['copay_percentage'],
                        'is_covered' => $row['coverage_type'] !== 'excluded',
                        'is_active' => true,
                        'notes' => $row['notes'] ?? null
                    ]
                );
                
                $rule->wasRecentlyCreated ? $results['created']++ : $results['updated']++;
                
            } catch (\Exception $e) {
                $results['skipped']++;
                $results['errors'][] = [
                    'row' => $index + 2,
                    'error' => $e->getMessage()
                ];
            }
        }
        
        return response()->json($results);
    }
}
```

### 5. Service Point Integration

#### Updated Charge Creation Logic

**File**: `app/Services/ChargeService.php`

```php
class ChargeService
{
    public function __construct(
        private InsuranceCoverageService $coverageService
    ) {}
    
    public function createCharge(array $data): Charge
    {
        $checkin = PatientCheckin::find($data['patient_checkin_id']);
        
        // Check if this is an insured visit
        if ($checkin->claim_check_code) {
            $claim = InsuranceClaim::where('claim_check_code', $checkin->claim_check_code)->first();
            
            if ($claim) {
                return $this->createInsuredCharge($data, $claim);
            }
        }
        
        // Regular cash charge
        return $this->createCashCharge($data);
    }
    
    private function createInsuredCharge(array $data, InsuranceClaim $claim): Charge
    {
        $insurancePlan = $claim->patientInsurance->insurancePlan;
        
        // Calculate coverage using the new service (handles specific vs general rules)
        $coverage = $this->coverageService->calculateCoverage(
            insurancePlanId: $insurancePlan->id,
            category: $data['category'],
            itemCode: $data['item_code'],
            amount: $data['amount']
        );
        
        // Create charge with coverage split
        $charge = Charge::create([
            'patient_checkin_id' => $data['patient_checkin_id'],
            'service_type' => $data['service_type'],
            'service_code' => $data['item_code'],
            'description' => $data['description'],
            'quantity' => $data['quantity'],
            'amount' => $data['amount'],
            'insurance_claim_id' => $claim->id,
            'is_insurance_claim' => $coverage['is_covered'],
            'insurance_covered_amount' => $coverage['insurance_pays'] * $data['quantity'],
            'patient_copay_amount' => $coverage['patient_pays'] * $data['quantity'],
            'status' => 'pending'
        ]);
        
        // Create claim item
        if ($coverage['is_covered']) {
            InsuranceClaimItem::create([
                'insurance_claim_id' => $claim->id,
                'charge_id' => $charge->id,
                'item_date' => now()->toDateString(),
                'item_type' => $data['category'],
                'code' => $data['item_code'],
                'description' => $data['description'],
                'quantity' => $data['quantity'],
                'unit_tariff' => $data['amount'],
                'subtotal' => $data['amount'] * $data['quantity'],
                'coverage_percentage' => $coverage['coverage_percentage'],
                'insurance_pays' => $coverage['insurance_pays'] * $data['quantity'],
                'patient_pays' => $coverage['patient_pays'] * $data['quantity'],
                'is_approved' => false
            ]);
            
            // Update claim totals
            $claim->increment('total_claim_amount', $data['amount'] * $data['quantity']);
            $claim->increment('insurance_covered_amount', $coverage['insurance_pays'] * $data['quantity']);
            $claim->increment('patient_copay_amount', $coverage['patient_pays'] * $data['quantity']);
        }
        
        return $charge;
    }
}
```

#### Service Point Display Component

**Component**: `CoverageDisplay.tsx`

```typescript
interface CoverageDisplayProps {
    itemCode: string;
    itemName: string;
    category: string;
    amount: number;
    quantity: number;
    insurancePlanId: number;
}

// Displays:
// - Item name and code
// - Standard price
// - Coverage percentage (from specific or general rule)
// - Insurance pays amount
// - Patient copay amount
// - Badge indicating if using specific or general rule
```

## Data Models

### InsuranceCoverageRule Model

**File**: `app/Models/InsuranceCoverageRule.php`

```php
class InsuranceCoverageRule extends Model
{
    protected $fillable = [
        'insurance_plan_id',
        'coverage_category',
        'item_code',
        'item_description',
        'is_covered',
        'coverage_type',
        'coverage_value',
        'patient_copay_percentage',
        'max_quantity_per_visit',
        'max_amount_per_visit',
        'requires_preauthorization',
        'is_active',
        'effective_from',
        'effective_to',
        'notes'
    ];
    
    protected $casts = [
        'is_covered' => 'boolean',
        'is_active' => 'boolean',
        'requires_preauthorization' => 'boolean',
        'coverage_value' => 'decimal:2',
        'patient_copay_percentage' => 'decimal:2',
        'effective_from' => 'date',
        'effective_to' => 'date'
    ];
    
    // Relationships
    public function insurancePlan()
    {
        return $this->belongsTo(InsurancePlan::class);
    }
    
    // Scopes
    public function scopeGeneral($query)
    {
        return $query->whereNull('item_code');
    }
    
    public function scopeSpecific($query)
    {
        return $query->whereNotNull('item_code');
    }
    
    public function scopeActive($query)
    {
        return $query->where('is_active', true)
            ->whereDate('effective_from', '<=', now())
            ->where(function ($q) {
                $q->whereNull('effective_to')
                  ->orWhereDate('effective_to', '>=', now());
            });
    }
    
    public function scopeForCategory($query, string $category)
    {
        return $query->where('coverage_category', $category);
    }
    
    // Accessors
    public function getIsGeneralAttribute(): bool
    {
        return is_null($this->item_code);
    }
    
    public function getIsSpecificAttribute(): bool
    {
        return !is_null($this->item_code);
    }
    
    public function getRuleTypeAttribute(): string
    {
        return $this->is_general ? 'general' : 'specific';
    }
}
```

## Error Handling

### Coverage Rule Conflicts

**Scenario**: Multiple active rules for same item
**Solution**: Use most recent `effective_from` date

```php
public function getCoverageRule(...)
{
    // Add ordering by effective_from DESC
    ->orderBy('effective_from', 'desc')
    ->first();
}
```

### Missing General Rule

**Scenario**: Item-specific rule exists but no general rule for category
**Solution**: Item-specific rule still applies; general rule is optional

### Invalid Item Code

**Scenario**: Admin tries to create rule for non-existent item
**Solution**: Validate item exists before saving

```php
protected function validateItemExists(string $category, string $itemCode): bool
{
    return match($category) {
        'drug' => Drug::where('code', $itemCode)->exists(),
        'lab' => LabTest::where('code', $itemCode)->exists(),
        'consultation' => Service::where('code', $itemCode)
            ->where('type', 'consultation')->exists(),
        // ... other categories
    };
}
```

## Testing Strategy

### Unit Tests

1. **Coverage Rule Lookup**
   - Test specific rule takes precedence over general
   - Test general rule fallback when no specific rule
   - Test no coverage when no rules exist
   - Test effective date filtering

2. **Coverage Calculation**
   - Test percentage coverage calculation
   - Test fixed amount coverage
   - Test full coverage
   - Test excluded items

3. **Model Scopes**
   - Test general() scope
   - Test specific() scope
   - Test active() scope
   - Test forCategory() scope

### Feature Tests

1. **Admin Interface**
   - Test creating general rule
   - Test creating item-specific rule
   - Test editing rules
   - Test deleting rules
   - Test viewing rules by category

2. **Bulk Import**
   - Test successful import
   - Test validation errors
   - Test duplicate handling
   - Test invalid item codes

3. **Service Integration**
   - Test charge creation with specific rule
   - Test charge creation with general rule
   - Test charge creation with no coverage
   - Test coverage display at service points

### Integration Tests

1. **Complete Patient Journey**
   - Patient with insurance checks in
   - Services use specific rules where applicable
   - Services use general rules as fallback
   - Billing shows correct copay amounts
   - Claims include correct coverage information

## Migration Strategy

### Phase 1: No Database Changes Required

The existing schema already supports this feature. No migrations needed.

### Phase 2: Data Validation

Run validation script to ensure data integrity:

```php
// Ensure no duplicate rules (same plan, category, item_code)
// Ensure effective dates are logical
// Ensure coverage values are within valid ranges
```

### Phase 3: Backward Compatibility

All existing general rules (with `item_code = NULL`) continue to work without modification. The new lookup logic checks for specific rules first, then falls back to general rules, maintaining existing behavior.

## Performance Considerations

### Database Indexing

Existing index is sufficient:
```sql
INDEX idx_plan_category (insurance_plan_id, coverage_category)
```

Consider adding composite index for faster specific rule lookup:
```sql
INDEX idx_plan_category_item (insurance_plan_id, coverage_category, item_code)
```

### Caching Strategy

Cache coverage rules per insurance plan:

```php
Cache::remember(
    "coverage_rules_{$planId}_{$category}",
    3600, // 1 hour
    fn() => InsuranceCoverageRule::where('insurance_plan_id', $planId)
        ->where('coverage_category', $category)
        ->active()
        ->get()
);
```

Clear cache when rules are updated:

```php
// In InsuranceCoverageRule model
protected static function booted()
{
    static::saved(function ($rule) {
        Cache::forget("coverage_rules_{$rule->insurance_plan_id}_{$rule->coverage_category}");
    });
}
```

## Security Considerations

1. **Authorization**: Only insurance administrators can manage coverage rules
2. **Validation**: Validate all input data, especially during bulk import
3. **Audit Trail**: Log all changes to coverage rules
4. **Data Integrity**: Prevent deletion of rules that are referenced in active claims

## Deployment Plan

1. Deploy code changes (no database migrations needed)
2. Run data validation script
3. Train administrators on new interface
4. Monitor coverage calculations for accuracy
5. Provide import templates and documentation
