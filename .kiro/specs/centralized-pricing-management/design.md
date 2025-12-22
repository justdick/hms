# Design Document: Centralized Pricing Management

## Overview

This feature extends the Unified Pricing Dashboard to become the single source of truth for all pricing in the HMS. It removes price fields from individual configuration forms (Drug, Lab Service, Department Billing), adds support for NHIS copay on unmapped items (flexible copay), and updates billing logic to correctly handle unmapped NHIS items.

The key changes are:
1. **Form modifications** - Remove price fields from Drug, Lab Service, and Department Billing forms
2. **Pricing status filters** - Add "Unpriced Items" and "Priced Items" filters to the dashboard
3. **Flexible copay** - Allow NHIS copay configuration for unmapped items
4. **Billing logic updates** - Handle unmapped NHIS items with copay in charge creation and claims
5. **Unpriced item handling** - Auto-external for unpriced drugs, external referral for unpriced labs

### UI Simplification

With the Pricing Dashboard as the single source of truth, the following UIs become redundant and can be removed or simplified:

**Remove from Insurance Plan Show page:**
- Coverage Rules table and "Add Rule" button → Replace with link to Pricing Dashboard
- Tariffs table and "Add Tariff" button → Replace with link to Pricing Dashboard
- "Manage Coverage" button → Redirect to Pricing Dashboard with plan pre-selected

**Keep on Insurance Plan page:**
- Plan details (limits, copay percentage, referral requirements)
- Category defaults as simple fields (e.g., "Drugs: 80%", "Labs: 70%") - these are fallback percentages when no item-specific rule exists

**Remove entirely:**
- `/admin/insurance/plans/{plan}/coverage` page - replaced by Pricing Dashboard
- `/admin/insurance/plans/{plan}/coverage-rules` page - replaced by Pricing Dashboard
- `/admin/insurance/coverage-rules/create` page - replaced by Pricing Dashboard inline editing
- `/admin/insurance/coverage-rules/{id}/edit` page - replaced by Pricing Dashboard inline editing
- `/admin/insurance/tariffs/create` page - replaced by Pricing Dashboard (for private insurance tariffs)
- `/admin/insurance/tariffs/{id}/edit` page - replaced by Pricing Dashboard (for private insurance tariffs)

**NOT affected (keep as-is):**
- `/admin/nhis-tariffs` - NHIS Master Tariff list (imported from NHIS, read-only prices)
- `/admin/nhis-mappings` - NHIS Item Mappings (linking drugs/labs to NHIS codes)
- `/admin/gdrg-tariffs` - G-DRG Tariffs (imported from NHIS)

The NHIS master tariff is government-set pricing and remains read-only. The Pricing Dashboard displays NHIS tariff prices but doesn't allow editing them. Only the facility's copay amount can be edited.

**Insurance Plan Edit page changes:**
- Add simple category default fields (percentage per category: consultation, drugs, labs, procedures)
- These are stored on the InsurancePlan model or as "general" coverage rules (item_code = null)

**Rationale:**
- All item-specific pricing (cash price, tariff, copay) is in the Pricing Dashboard
- Category defaults are simple percentage fields on the plan (fallback when no item rule exists)
- This eliminates the confusing "category default vs item exception" paradigm
- Single place to manage all pricing = less confusion, fewer errors

## Architecture

### High-Level Architecture

```
┌─────────────────────────────────────────────────────────────────────────┐
│                    Configuration Forms (Modified)                        │
│                                                                          │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐                   │
│  │ Drug Form    │  │ Lab Service  │  │ Dept Billing │                   │
│  │ (no price)   │  │ (no price)   │  │ (no price)   │                   │
│  └──────────────┘  └──────────────┘  └──────────────┘                   │
│         │                 │                 │                            │
│         └─────────────────┼─────────────────┘                            │
│                           │ "Set Price" link                             │
│                           ▼                                              │
│  ┌────────────────────────────────────────────────────────────────────┐ │
│  │              Unified Pricing Dashboard (Enhanced)                   │ │
│  │  - Pricing status filter (Unpriced/Priced)                         │ │
│  │  - Flexible copay for unmapped NHIS items                          │ │
│  │  - Status indicators (Priced, Unpriced, Mapped, Flexible Copay)    │ │
│  └────────────────────────────────────────────────────────────────────┘ │
└─────────────────────────────────────────────────────────────────────────┘
                                    │
                                    ▼
┌─────────────────────────────────────────────────────────────────────────┐
│                    Billing & Ordering Logic (Updated)                    │
│                                                                          │
│  ┌──────────────────────┐  ┌──────────────────────┐                     │
│  │ InsuranceCoverage-   │  │ Prescription/Lab     │                     │
│  │ Service (updated)    │  │ Order Creation       │                     │
│  │ - Flexible copay     │  │ - Auto-external      │                     │
│  │ - Unmapped handling  │  │ - External referral  │                     │
│  └──────────────────────┘  └──────────────────────┘                     │
└─────────────────────────────────────────────────────────────────────────┘
```

### Request Flow for Flexible Copay

```
┌────────┐     ┌────────────────┐     ┌─────────────────────┐     ┌──────────┐
│ Browser│────▶│ PricingDash-   │────▶│ PricingDashboard-   │────▶│ Database │
│        │     │ boardController│     │ Service             │     │          │
└────────┘     └────────────────┘     └─────────────────────┘     └──────────┘
     │                │                        │                        │
     │  PUT /pricing  │                        │                        │
     │  /flexible-    │                        │                        │
     │  copay         │                        │                        │
     │───────────────▶│  updateFlexibleCopay() │                        │
     │                │───────────────────────▶│  Check if unmapped     │
     │                │                        │───────────────────────▶│
     │                │                        │  Create/Update         │
     │                │                        │  InsuranceCoverageRule │
     │                │                        │  with is_unmapped=true │
     │                │                        │───────────────────────▶│
     │                │◀───────────────────────│                        │
     │◀───────────────│  redirect()->back()    │                        │
```

### Billing Flow for Unmapped NHIS Items

```
┌─────────────┐     ┌─────────────────────┐     ┌──────────────────┐
│ Charge      │────▶│ InsuranceCoverage-  │────▶│ Charge Created   │
│ Creation    │     │ Service             │     │                  │
│ Event       │     │                     │     │                  │
└─────────────┘     └─────────────────────┘     └──────────────────┘
      │                      │                          │
      │  Is NHIS patient?    │                          │
      │─────────────────────▶│                          │
      │                      │  Is item mapped?         │
      │                      │─────────────────────────▶│
      │                      │                          │
      │                      │  NO - Check flexible     │
      │                      │  copay rule              │
      │                      │─────────────────────────▶│
      │                      │                          │
      │                      │  Has copay? → patient    │
      │                      │  pays copay, ins = 0     │
      │                      │                          │
      │                      │  No copay? → patient     │
      │                      │  pays cash price, ins = 0│
      │                      │◀─────────────────────────│
```

## Components and Interfaces

### Backend Components

#### Updated PricingDashboardService

```php
namespace App\Services;

class PricingDashboardService
{
    // Existing methods...

    /**
     * Get pricing data with pricing status filter.
     * 
     * @param string|null $pricingStatus 'unpriced', 'priced', or null for all
     */
    public function getPricingData(
        ?int $insurancePlanId,
        ?string $category = null,
        ?string $search = null,
        ?string $pricingStatus = null,  // NEW
        int $perPage = 50
    ): array;

    /**
     * Update flexible copay for an unmapped NHIS item.
     * Creates InsuranceCoverageRule with is_unmapped = true.
     */
    public function updateFlexibleCopay(
        int $nhisplanId,
        string $itemType,
        int $itemId,
        string $itemCode,
        ?float $copayAmount
    ): ?InsuranceCoverageRule;

    /**
     * Get pricing status summary counts.
     * 
     * @return array{
     *     unpriced: int,
     *     priced: int,
     *     nhis_mapped: int,
     *     nhis_unmapped: int,
     *     flexible_copay: int
     * }
     */
    public function getPricingStatusSummary(?int $insurancePlanId = null): array;

    /**
     * Determine pricing status for an item.
     * 
     * @return string 'priced'|'unpriced'|'nhis_mapped'|'flexible_copay'|'not_mapped'
     */
    public function getPricingStatus(
        string $itemType,
        int $itemId,
        ?int $insurancePlanId = null
    ): string;
}
```

#### Updated InsuranceCoverageService

```php
namespace App\Services;

class InsuranceCoverageService
{
    /**
     * Calculate coverage for an item, handling unmapped items with flexible copay.
     * 
     * @return array{
     *     insurance_amount: float,
     *     patient_amount: float,
     *     is_unmapped: bool,
     *     has_flexible_copay: bool
     * }
     */
    public function calculateCoverage(
        int $insurancePlanId,
        string $itemType,
        string $itemCode,
        float $cashPrice
    ): array;

    /**
     * Check if item has flexible copay configured.
     */
    public function hasFlexibleCopay(
        int $insurancePlanId,
        string $itemType,
        string $itemCode
    ): bool;

    /**
     * Get flexible copay amount for unmapped item.
     */
    public function getFlexibleCopay(
        int $insurancePlanId,
        string $itemType,
        string $itemCode
    ): ?float;
}
```

#### New Form Request: UpdateFlexibleCopayRequest

```php
namespace App\Http\Requests\Admin;

class UpdateFlexibleCopayRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'item_type' => ['required', 'in:drug,lab,procedure'],
            'item_id' => ['required', 'integer'],
            'item_code' => ['required', 'string'],
            'copay_amount' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
```

### Database Changes

#### Migration: Add is_unmapped to InsuranceCoverageRule

```php
Schema::table('insurance_coverage_rules', function (Blueprint $table) {
    $table->boolean('is_unmapped')->default(false)->after('patient_copay_amount');
});
```

This flag indicates the coverage rule is for an unmapped item (flexible copay).

### Frontend Components

#### Updated PricingDashboard/Index.tsx

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
        pricingStatus: 'all' | 'unpriced' | 'priced';  // NEW
    };
    summary: {  // NEW
        unpriced: number;
        priced: number;
        nhis_mapped: number;
        nhis_unmapped: number;
        flexible_copay: number;
    };
}
```

#### Updated PricingItem Type

```tsx
interface PricingItem {
    id: number;
    type: 'drug' | 'lab' | 'consultation' | 'procedure';
    code: string;
    name: string;
    category: string;
    cashPrice: number | null;  // null = unpriced
    insuranceTariff: number | null;
    copayAmount: number | null;
    coverageValue: number | null;
    coverageType: string | null;
    isMapped: boolean;
    isUnmapped: boolean;  // NEW - has flexible copay rule
    nhisCode: string | null;
    coverageRuleId: number | null;
    pricingStatus: 'priced' | 'unpriced' | 'nhis_mapped' | 'flexible_copay' | 'not_mapped';  // NEW
}
```

#### New Component: PricingStatusFilter.tsx

Filter dropdown for pricing status (All, Unpriced, Priced).

#### New Component: PricingStatusBadge.tsx

Badge component showing pricing status with appropriate colors:
- Priced: Green
- Unpriced: Red/Warning
- NHIS Mapped: Blue
- Flexible Copay: Purple
- Not Mapped: Gray

#### New Component: PricingSummaryCards.tsx

Summary cards showing counts of items in each pricing status.

### Form Modifications

#### Drug Form Changes

Remove `unit_price` field from:
- `resources/js/pages/Pharmacy/Drugs/Create.tsx`
- `resources/js/pages/Pharmacy/Drugs/Edit.tsx`

Add "Set Price" link in drug list that navigates to `/admin/pricing-dashboard?search={drug_code}`.

#### Lab Service Form Changes

Remove `price` field from:
- `resources/js/pages/Lab/Services/Create.tsx`
- `resources/js/pages/Lab/Services/Edit.tsx`

Add "Set Price" link in lab service list.

#### Department Billing Form Changes

Remove `consultation_fee` field from:
- `resources/js/pages/Admin/DepartmentBilling/Edit.tsx`

Add "Set Price" link in department billing list.

### Prescription/Lab Order Logic Updates

#### PrescriptionObserver Updates

```php
class PrescriptionObserver
{
    public function creating(Prescription $prescription): void
    {
        // Check if drug is unpriced
        if ($prescription->drug && $prescription->drug->unit_price === null) {
            $prescription->dispensing_source = 'external';
            $prescription->is_unpriced = true;
        }
    }
}
```

#### LabOrderObserver Updates

```php
class LabOrderObserver
{
    public function creating(LabOrder $labOrder): void
    {
        // Check if lab service is unpriced
        if ($labOrder->labService && $labOrder->labService->price === null) {
            $labOrder->status = 'external_referral';
            $labOrder->is_unpriced = true;
        }
    }
}
```

## Data Models

### Modified Models

| Model | Changes |
|-------|---------|
| InsuranceCoverageRule | Add `is_unmapped` boolean field |
| Prescription | Add `is_unpriced` boolean field, update observer |
| LabOrder | Add `is_unpriced` boolean field, add `external_referral` status, update observer |

### New Migrations

1. `add_is_unmapped_to_insurance_coverage_rules_table`
2. `add_is_unpriced_to_prescriptions_table`
3. `add_is_unpriced_and_external_referral_to_lab_orders_table`

## Correctness Properties

*A property is a characteristic or behavior that should hold true across all valid executions of a system-essentially, a formal statement about what the system should do. Properties serve as the bridge between human-readable specifications and machine-verifiable correctness guarantees.*

### Property Reflection

After analyzing the acceptance criteria, I identified the following redundancies:
- 1.4 and 1.5 (new items default to null price) can be combined into one property
- 2.1 and 2.2 (pricing status filters) are inverses but test different logic, keep separate
- 4.1/4.3 and 4.2/4.4 test the same billing logic from different angles, combine into comprehensive properties
- 3.3 and 3.4 (status display) can be combined into one status determination property

### Property 1: New items default to unpriced

*For any* newly created Drug or LabService, the price field (unit_price or price) should be null.

**Validates: Requirements 1.4, 1.5**

### Property 2: Unpriced filter returns only unpriced items

*For any* set of items with various prices, the "Unpriced Items" filter should return exactly those items where cash price is null or zero.

**Validates: Requirements 2.1**

### Property 3: Priced filter returns only priced items

*For any* set of items with various prices, the "Priced Items" filter should return exactly those items where cash price is greater than zero.

**Validates: Requirements 2.2**

### Property 4: Flexible copay creates coverage rule with unmapped flag

*For any* unmapped item and valid copay amount, setting flexible copay should create an InsuranceCoverageRule with `is_unmapped = true` and the correct `patient_copay_amount`.

**Validates: Requirements 3.2**

### Property 5: Pricing status correctly determined

*For any* item, the pricing status should be:
- "unpriced" if cash price is null/zero
- "nhis_mapped" if has NHIS mapping and no flexible copay
- "flexible_copay" if unmapped but has copay rule with is_unmapped=true
- "not_mapped" if unmapped and no flexible copay
- "priced" otherwise

**Validates: Requirements 3.3, 3.4, 5.1**

### Property 6: Clearing flexible copay removes or nullifies rule

*For any* unmapped item with existing flexible copay, clearing the copay should either delete the InsuranceCoverageRule or set patient_copay_amount to null.

**Validates: Requirements 3.5**

### Property 7: Unmapped NHIS billing with flexible copay

*For any* NHIS patient charge for an unmapped item with flexible copay configured, the charge should have insurance_amount = 0 and patient_amount = copay_amount.

**Validates: Requirements 4.1, 4.3**

### Property 8: Unmapped NHIS billing without copay

*For any* NHIS patient charge for an unmapped item without flexible copay, the charge should have insurance_amount = 0 and patient_amount = cash_price.

**Validates: Requirements 4.2, 4.4**

### Property 9: Insurance claims include unmapped items

*For any* insurance claim for an NHIS patient with unmapped items, all unmapped items should be included in the claim with insurance_amount = 0.

**Validates: Requirements 4.5**

### Property 10: Pricing summary counts are accurate

*For any* set of items, the pricing summary counts should exactly match the number of items in each status category.

**Validates: Requirements 5.2**

### Property 11: Unpriced drugs auto-set to external

*For any* prescription for an unpriced drug, the dispensing_source should be automatically set to "external".

**Validates: Requirements 6.1**

### Property 12: External prescriptions excluded from dispensing queue

*For any* set of prescriptions, the pharmacy dispensing queue should exclude all prescriptions with dispensing_source = "external".

**Validates: Requirements 6.4**

### Property 13: Unpriced labs auto-set to external referral

*For any* lab order for an unpriced lab service, the status should be automatically set to "external_referral".

**Validates: Requirements 7.2**

### Property 14: External referral orders excluded from lab queue

*For any* set of lab orders, the lab work queue should exclude all orders with status = "external_referral".

**Validates: Requirements 7.4**

### Property 15: Category defaults used when no item rule exists

*For any* insurance plan with category defaults and an item without a specific coverage rule, the coverage calculation should use the category default percentage from the plan.

**Validates: Requirements 8.5**

## Error Handling

| Error Scenario | Handling |
|----------------|----------|
| Setting copay for mapped item | Return error "Item is already mapped to NHIS tariff" |
| Negative copay amount | Return validation error |
| Item not found | Return 404 error |
| Database error on save | Return 500 error, log exception |
| Attempting to dispense unpriced drug | Return error "Drug is unpriced, marked for external dispensing" |

## Testing Strategy

### Dual Testing Approach

This feature requires both unit tests and property-based tests:

- **Unit tests** verify specific examples, UI behavior, and edge cases
- **Property-based tests** verify universal properties across random inputs

### Property-Based Testing

Use **Pest** with property-based testing approach. Each correctness property will be implemented as a property-based test.

```php
// Example structure
it('creates coverage rule with unmapped flag for flexible copay', function () {
    // Generate random unmapped item and copay amount
    // Call updateFlexibleCopay
    // Assert InsuranceCoverageRule created with is_unmapped = true
})->repeat(100);
```

### Unit Testing

- Test form field removal (UI tests)
- Test "Set Price" link navigation
- Test pricing status badge rendering
- Test edge cases (zero price vs null price)
- Test observer behavior for unpriced items

### Test Data Requirements

- Factory for Drug with nullable unit_price
- Factory for LabService with nullable price
- Factory for InsuranceCoverageRule with is_unmapped flag
- Factory for Prescription with dispensing_source
- Factory for LabOrder with external_referral status

