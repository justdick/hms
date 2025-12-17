# Design Document: Unified Pricing Dashboard

## Overview

The Unified Pricing Dashboard provides a centralized interface for viewing and managing all service pricing across the HMS. It aggregates pricing data from multiple sources (Drug, LabService, DepartmentBilling, InsuranceCoverageRule, NhisTariff) into a single view, with the ability to switch between insurance plans to see plan-specific pricing.

The dashboard acts as a **view layer** over existing data - edits made here update the underlying tables, ensuring consistency with existing configuration pages.

## Architecture

### High-Level Architecture

```
┌─────────────────────────────────────────────────────────────────────────┐
│                        Unified Pricing Dashboard                         │
│                                                                          │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐                   │
│  │ Plan Selector│  │ Search/Filter│  │ Bulk Actions │                   │
│  └──────────────┘  └──────────────┘  └──────────────┘                   │
│                                                                          │
│  ┌────────────────────────────────────────────────────────────────────┐ │
│  │                      Pricing Table                                  │ │
│  │  Item | Category | Cash Price | [Insurance-specific columns]       │ │
│  └────────────────────────────────────────────────────────────────────┘ │
└─────────────────────────────────────────────────────────────────────────┘
                                    │
                                    ▼
┌─────────────────────────────────────────────────────────────────────────┐
│                    PricingDashboardService                               │
│                                                                          │
│  - getPricingData(planId, filters)                                      │
│  - updateCashPrice(itemType, itemId, price)                             │
│  - updateInsuranceCopay(planId, itemType, itemId, copay)                │
│  - updateInsuranceCoverage(planId, itemType, itemId, coverage)          │
│  - bulkUpdateCopay(planId, items[], copay)                              │
│  - exportPricing(planId, filters)                                       │
└─────────────────────────────────────────────────────────────────────────┘
                                    │
                    ┌───────────────┼───────────────┐
                    ▼               ▼               ▼
            ┌─────────────┐ ┌─────────────┐ ┌─────────────────────┐
            │    Drug     │ │ LabService  │ │ InsuranceCoverageRule│
            │ unit_price  │ │   price     │ │ patient_copay_amount │
            └─────────────┘ └─────────────┘ └─────────────────────┘
                    │               │               │
                    ▼               ▼               ▼
            ┌─────────────┐ ┌─────────────┐ ┌─────────────────────┐
            │ Department  │ │ NhisTariff  │ │  InsuranceTariff    │
            │  Billing    │ │  (read)     │ │   tariff_amount     │
            └─────────────┘ └─────────────┘ └─────────────────────┘
```

### Request Flow

```
┌────────┐     ┌────────────────┐     ┌─────────────────────┐     ┌──────────┐
│ Browser│────▶│ PricingDash-   │────▶│ PricingDashboard-   │────▶│ Database │
│        │     │ boardController│     │ Service             │     │          │
└────────┘     └────────────────┘     └─────────────────────┘     └──────────┘
     │                │                        │                        │
     │  GET /pricing  │                        │                        │
     │  ?plan_id=1    │                        │                        │
     │───────────────▶│  getPricingData(1)     │                        │
     │                │───────────────────────▶│  Query Drug, Lab,      │
     │                │                        │  DeptBilling, Coverage │
     │                │                        │───────────────────────▶│
     │                │                        │◀───────────────────────│
     │                │◀───────────────────────│                        │
     │◀───────────────│  Inertia::render()     │                        │
     │                │                        │                        │
     │  PUT /pricing  │                        │                        │
     │  {item, price} │                        │                        │
     │───────────────▶│  updateCashPrice()     │                        │
     │                │───────────────────────▶│  Drug::update()        │
     │                │                        │───────────────────────▶│
     │                │                        │◀───────────────────────│
     │◀───────────────│  redirect()->back()    │                        │
```

## Components and Interfaces

### Backend Components

#### PricingDashboardController

```php
namespace App\Http\Controllers\Admin;

class PricingDashboardController extends Controller
{
    public function __construct(
        protected PricingDashboardService $pricingService
    ) {}

    // GET /admin/pricing-dashboard
    public function index(Request $request): Response;

    // PUT /admin/pricing-dashboard/cash-price
    public function updateCashPrice(UpdateCashPriceRequest $request): RedirectResponse;

    // PUT /admin/pricing-dashboard/insurance-copay
    public function updateInsuranceCopay(UpdateInsuranceCopayRequest $request): RedirectResponse;

    // PUT /admin/pricing-dashboard/insurance-coverage
    public function updateInsuranceCoverage(UpdateInsuranceCoverageRequest $request): RedirectResponse;

    // POST /admin/pricing-dashboard/bulk-update
    public function bulkUpdate(BulkUpdatePricingRequest $request): RedirectResponse;

    // GET /admin/pricing-dashboard/export
    public function export(Request $request): StreamedResponse;

    // POST /admin/pricing-dashboard/import
    public function import(ImportPricingRequest $request): RedirectResponse;

    // GET /admin/pricing-dashboard/import-template
    public function downloadImportTemplate(Request $request): StreamedResponse;
}
```

#### PricingDashboardService

```php
namespace App\Services;

class PricingDashboardService
{
    /**
     * Get all pricing data for a specific insurance plan.
     * 
     * @return array{
     *     items: Collection,
     *     categories: array,
     *     plan: ?InsurancePlan,
     *     is_nhis: bool
     * }
     */
    public function getPricingData(
        ?int $insurancePlanId,
        ?string $category = null,
        ?string $search = null,
        int $perPage = 50
    ): array;

    /**
     * Update cash price for an item.
     */
    public function updateCashPrice(
        string $itemType,
        int $itemId,
        float $price
    ): bool;

    /**
     * Update insurance copay for an item.
     * Creates InsuranceCoverageRule if not exists.
     */
    public function updateInsuranceCopay(
        int $insurancePlanId,
        string $itemType,
        int $itemId,
        string $itemCode,
        float $copayAmount
    ): InsuranceCoverageRule;

    /**
     * Update insurance coverage settings for an item.
     */
    public function updateInsuranceCoverage(
        int $insurancePlanId,
        string $itemType,
        int $itemId,
        string $itemCode,
        array $coverageData
    ): InsuranceCoverageRule;

    /**
     * Bulk update copay for multiple items.
     * 
     * @return array{updated: int, errors: array}
     */
    public function bulkUpdateCopay(
        int $insurancePlanId,
        array $items,
        float $copayAmount
    ): array;

    /**
     * Export pricing data to CSV.
     */
    public function exportToCsv(
        ?int $insurancePlanId,
        ?string $category = null,
        ?string $search = null
    ): string;

    /**
     * Import pricing data from CSV/Excel.
     * 
     * @return array{imported: int, updated: int, skipped: int, errors: array}
     */
    public function importFromFile(
        UploadedFile $file,
        ?int $insurancePlanId = null
    ): array;

    /**
     * Generate import template CSV.
     */
    public function generateImportTemplate(
        ?int $insurancePlanId = null,
        ?string $category = null
    ): string;
}
```

#### PricingItem DTO

```php
namespace App\DTOs;

class PricingItem
{
    public function __construct(
        public int $id,
        public string $type,           // 'drug', 'lab', 'consultation', 'procedure'
        public string $code,
        public string $name,
        public string $category,
        public float $cashPrice,
        public ?float $insuranceTariff,
        public ?float $copayAmount,
        public ?float $coverageValue,
        public ?string $coverageType,
        public bool $isMapped,         // For NHIS: has NhisItemMapping
        public ?string $nhisCode,
        public ?int $coverageRuleId,
    ) {}
}
```

### Frontend Components

#### Page: PricingDashboard/Index.tsx

```tsx
interface Props {
    items: PaginatedData<PricingItem>;
    insurancePlans: InsurancePlan[];
    selectedPlan: InsurancePlan | null;
    isNhis: boolean;
    categories: string[];
    filters: {
        category: string | null;
        search: string | null;
        unmappedOnly: boolean;
    };
}
```

#### Component: PricingTable.tsx

Displays the pricing data with inline editing capabilities.

#### Component: PlanSelector.tsx

Dropdown to select insurance plan, with NHIS highlighted.

#### Component: BulkEditModal.tsx

Modal for bulk updating copay amounts.

#### Component: PricingExportButton.tsx

Export current view to CSV.

#### Component: PricingImportModal.tsx

Modal for importing pricing data from CSV/Excel file. Shows preview of changes before applying.

## Data Models

### Existing Models Used

| Model | Fields Used | Operation |
|-------|-------------|-----------|
| Drug | id, drug_code, name, unit_price, category | Read/Write unit_price |
| LabService | id, code, name, price, category | Read/Write price |
| DepartmentBilling | id, department_id, department_name, consultation_fee | Read/Write consultation_fee |
| InsurancePlan | id, name, provider_id | Read |
| InsuranceProvider | id, name, is_nhis | Read |
| InsuranceCoverageRule | id, insurance_plan_id, coverage_category, item_code, patient_copay_amount, coverage_value, coverage_type, tariff_amount | Read/Write |
| NhisTariff | id, nhis_code, name, price, category | Read only |
| NhisItemMapping | item_type, item_id, nhis_tariff_id | Read |

### New Model: PricingChangeLog

```php
// Migration
Schema::create('pricing_change_logs', function (Blueprint $table) {
    $table->id();
    $table->string('item_type');           // 'drug', 'lab', 'consultation'
    $table->unsignedBigInteger('item_id');
    $table->string('item_code')->nullable();
    $table->string('field_changed');        // 'cash_price', 'copay', 'coverage'
    $table->unsignedBigInteger('insurance_plan_id')->nullable();
    $table->decimal('old_value', 10, 2)->nullable();
    $table->decimal('new_value', 10, 2);
    $table->foreignId('changed_by')->constrained('users');
    $table->timestamps();
    
    $table->index(['item_type', 'item_id']);
    $table->index('insurance_plan_id');
});
```

## Correctness Properties

*A property is a characteristic or behavior that should hold true across all valid executions of a system-essentially, a formal statement about what the system should do. Properties serve as the bridge between human-readable specifications and machine-verifiable correctness guarantees.*

### Property 1: Cash price updates persist to correct model

*For any* item type and valid price, updating the cash price via the dashboard should update the corresponding model's price field (Drug.unit_price, LabService.price, or DepartmentBilling.consultation_fee).

**Validates: Requirements 2.1, 2.2, 2.3**

### Property 2: Price validation rejects invalid values

*For any* price value that is zero, negative, or non-numeric, the system should reject the update and return a validation error.

**Validates: Requirements 2.5**

### Property 3: NHIS copay updates create or update coverage rules

*For any* NHIS-mapped item and valid copay amount, updating the copay should create a new InsuranceCoverageRule (if none exists) or update the existing rule's patient_copay_amount field with the item-specific item_code.

**Validates: Requirements 3.3**

### Property 4: Unmapped items are correctly identified

*For any* item without an NhisItemMapping record linking to an active NhisTariff, the dashboard should indicate the item is unmapped and disable copay editing for NHIS.

**Validates: Requirements 3.4, 6.1, 6.2**

### Property 5: NHIS tariff display matches master data

*For any* NHIS-mapped item, the displayed NHIS tariff should equal the NhisTariff.price value from the linked tariff record.

**Validates: Requirements 3.2**

### Property 6: Private insurance coverage updates persist correctly

*For any* private insurance plan, item, and valid coverage settings (tariff, coverage_value, copay), updating via the dashboard should create or update the InsuranceCoverageRule with the correct values.

**Validates: Requirements 4.2, 4.3, 4.4**

### Property 7: Patient pays calculation is correct

*For any* coverage settings (coverage_type, coverage_value, tariff, copay), the calculated "Patient Pays" amount should equal: (tariff × (100 - coverage_value)%) + fixed_copay for percentage coverage, or tariff - coverage_value + fixed_copay for fixed coverage.

**Validates: Requirements 4.5**

### Property 8: Bulk update applies to all selected items

*For any* set of selected items and copay value, bulk update should create or update InsuranceCoverageRule records for every item in the set with the specified copay amount.

**Validates: Requirements 5.2, 5.3**

### Property 9: Search returns matching items only

*For any* search term, all returned items should have the search term in their name, code, or category (case-insensitive partial match).

**Validates: Requirements 1.4**

### Property 10: Export contains all filtered data

*For any* filter criteria, the exported CSV should contain exactly the items that match the current filters, with all required columns present.

**Validates: Requirements 7.1, 7.2, 7.3**

### Property 11: Import matches items by code

*For any* valid CSV row with an item code, the import should find and update the correct item by matching drug_code, lab service code, or department code.

**Validates: Requirements 8.2**

### Property 12: Import handles invalid rows gracefully

*For any* CSV file containing both valid and invalid rows, the import should process all valid rows and skip invalid ones, returning accurate counts of imported, skipped, and error rows.

**Validates: Requirements 8.5, 8.6**

### Property 13: Audit log captures all price changes

*For any* price or copay change made via the dashboard (including imports), a PricingChangeLog record should be created with the correct old value, new value, user, and timestamp.

**Validates: Requirements 9.1, 9.2**

## Error Handling

| Error Scenario | Handling |
|----------------|----------|
| Invalid price (negative/zero) | Return validation error, do not save |
| Item not found | Return 404 error |
| Insurance plan not found | Return 404 error |
| Unmapped item copay edit (NHIS) | Return error "Item must be mapped to NHIS tariff first" |
| Database error on save | Return 500 error, log exception |
| Bulk update partial failure | Continue with remaining items, return summary of successes and failures |

## Testing Strategy

### Dual Testing Approach

This feature requires both unit tests and property-based tests:

- **Unit tests** verify specific examples and edge cases
- **Property-based tests** verify universal properties across random inputs

### Property-Based Testing

Use **Pest** with a property-based testing approach. Each correctness property will be implemented as a property-based test.

```php
// Example structure
it('persists cash price updates to correct model', function () {
    // Generate random item type and valid price
    // Update via service
    // Assert database reflects change
})->repeat(100);
```

### Unit Testing

- Test controller authorization
- Test validation rules
- Test edge cases (empty results, max pagination)
- Test NHIS vs private insurance view differences

### Test Data Requirements

- Factory for Drug, LabService, DepartmentBilling
- Factory for InsurancePlan (with NHIS and non-NHIS variants)
- Factory for InsuranceCoverageRule
- Factory for NhisTariff and NhisItemMapping
