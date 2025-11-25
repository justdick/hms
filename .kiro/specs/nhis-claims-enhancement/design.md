# Design Document: NHIS Claims Enhancement

## Overview

This feature enhances the insurance claims vetting system to support NHIS (National Health Insurance Scheme) claims with G-DRG tariff selection. The implementation uses a modal-based vetting interface that opens from the claims list, allowing claims officers to efficiently review and approve claims without page navigation.

Key capabilities:
- G-DRG tariff management (separate from existing insurance tariffs)
- Modal-based claim vetting with searchable G-DRG selection
- Claim-specific diagnoses (independent of consultation records)
- Aggregated view of investigations, prescriptions, and procedures from consultations and ward rounds
- Export vetted claims to XML and Excel formats by date range
- Support for both NHIS (G-DRG) and standard insurance workflows

## Architecture

The feature follows the existing HMS architecture patterns:

```
┌─────────────────────────────────────────────────────────────────┐
│                        Frontend (React)                          │
├─────────────────────────────────────────────────────────────────┤
│  Claims Index Page                                               │
│  ├── ClaimsTable (existing)                                     │
│  ├── VettingModal (new)                                         │
│  │   ├── PatientInfoSection                                     │
│  │   ├── AttendanceDetailsSection                               │
│  │   ├── GdrgSelector (NHIS only)                               │
│  │   ├── DiagnosesManager                                       │
│  │   ├── ClaimItemsTabs                                         │
│  │   │   ├── InvestigationsTab                                  │
│  │   │   ├── PrescriptionsTab                                   │
│  │   │   └── ProceduresTab                                      │
│  │   └── ClaimTotalDisplay                                      │
│  └── ExportModal (new)                                          │
├─────────────────────────────────────────────────────────────────┤
│                     Backend (Laravel)                            │
├─────────────────────────────────────────────────────────────────┤
│  Controllers                                                     │
│  ├── GdrgTariffController (new)                                 │
│  ├── InsuranceClaimController (enhanced)                        │
│  └── ClaimExportController (new)                                │
├─────────────────────────────────────────────────────────────────┤
│  Services                                                        │
│  ├── ClaimVettingService (new)                                  │
│  └── ClaimExportService (new)                                   │
├─────────────────────────────────────────────────────────────────┤
│  Models                                                          │
│  ├── GdrgTariff (new)                                           │
│  ├── InsuranceClaimDiagnosis (new)                              │
│  ├── InsuranceProvider (enhanced - requires_gdrg flag)          │
│  └── InsuranceClaim (enhanced - gdrg_tariff_id)                 │
└─────────────────────────────────────────────────────────────────┘
```

## Components and Interfaces

### Backend Components

#### 1. GdrgTariffController
Manages G-DRG tariff CRUD operations.

```php
class GdrgTariffController extends Controller
{
    public function index(Request $request): Response;      // List with search/filter
    public function store(StoreGdrgTariffRequest $request): RedirectResponse;
    public function update(UpdateGdrgTariffRequest $request, GdrgTariff $tariff): RedirectResponse;
    public function destroy(GdrgTariff $tariff): RedirectResponse;
    public function import(ImportGdrgTariffRequest $request): RedirectResponse;
    public function search(Request $request): JsonResponse; // API for searchable dropdown
}
```

#### 2. InsuranceClaimController (Enhanced)
Add modal vetting support and claim diagnosis management.

```php
// New/modified methods
public function getVettingData(InsuranceClaim $claim): JsonResponse;  // Data for modal
public function vetNhis(VetNhisClaimRequest $request, InsuranceClaim $claim): RedirectResponse;
public function updateClaimDiagnoses(Request $request, InsuranceClaim $claim): RedirectResponse;
```

#### 3. ClaimExportController
Handles XML and Excel export for vetted claims.

```php
class ClaimExportController extends Controller
{
    public function exportXml(ExportClaimsRequest $request): StreamedResponse;
    public function exportExcel(ExportClaimsRequest $request): StreamedResponse;
}
```

#### 4. ClaimVettingService
Business logic for claim vetting and total calculation.

```php
class ClaimVettingService
{
    public function calculateNhisTotal(InsuranceClaim $claim, GdrgTariff $gdrg): array;
    public function calculateStandardTotal(InsuranceClaim $claim): array;
    public function getClaimItems(InsuranceClaim $claim): array;
    public function aggregateAdmissionItems(PatientAdmission $admission): array;
}
```

#### 5. ClaimExportService
Handles export formatting for XML and Excel.

```php
class ClaimExportService
{
    public function generateNhisXml(Collection $claims): string;
    public function generateExcel(Collection $claims): StreamedResponse;
}
```

### Frontend Components

#### 1. VettingModal
Main modal component for claim vetting.

```tsx
interface VettingModalProps {
    claim: InsuranceClaim;
    isOpen: boolean;
    onClose: () => void;
    onApproved: () => void;
}
```

#### 2. GdrgSelector
Searchable dropdown for G-DRG selection.

```tsx
interface GdrgSelectorProps {
    value: GdrgTariff | null;
    onChange: (tariff: GdrgTariff | null) => void;
    disabled?: boolean;
}
```

#### 3. DiagnosesManager
Manages claim-specific diagnoses (add/remove).

```tsx
interface DiagnosesManagerProps {
    claimId: number;
    initialDiagnoses: ClaimDiagnosis[];
    onDiagnosesChange: (diagnoses: ClaimDiagnosis[]) => void;
}
```

#### 4. ClaimItemsTabs
Tabbed display for investigations, prescriptions, procedures.

```tsx
interface ClaimItemsTabsProps {
    investigations: LabOrder[];
    prescriptions: Prescription[];
    procedures: Procedure[];
}
```

#### 5. ExportModal
Modal for selecting date range and export format.

```tsx
interface ExportModalProps {
    isOpen: boolean;
    onClose: () => void;
}
```

## Data Models

### New Tables

#### gdrg_tariffs
```sql
CREATE TABLE gdrg_tariffs (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    code VARCHAR(20) NOT NULL UNIQUE,        -- e.g., "OPDC06A"
    name VARCHAR(255) NOT NULL,              -- e.g., "General OPD - Adult"
    mdc_category VARCHAR(100) NOT NULL,      -- e.g., "OUT PATIENT", "IN PATIENT"
    tariff_price DECIMAL(10,2) NOT NULL,     -- e.g., 55.06
    age_category ENUM('adult', 'child', 'all') DEFAULT 'all',
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    
    INDEX idx_code (code),
    INDEX idx_name (name),
    INDEX idx_mdc_category (mdc_category)
);
```

#### insurance_claim_diagnoses
```sql
CREATE TABLE insurance_claim_diagnoses (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    insurance_claim_id BIGINT UNSIGNED NOT NULL,
    diagnosis_id BIGINT UNSIGNED NOT NULL,
    is_primary BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    
    FOREIGN KEY (insurance_claim_id) REFERENCES insurance_claims(id) ON DELETE CASCADE,
    FOREIGN KEY (diagnosis_id) REFERENCES diagnoses(id),
    UNIQUE KEY unique_claim_diagnosis (insurance_claim_id, diagnosis_id)
);
```

### Modified Tables

#### insurance_providers (add column)
```sql
ALTER TABLE insurance_providers 
ADD COLUMN requires_gdrg BOOLEAN DEFAULT FALSE AFTER is_active;
```

#### insurance_claims (add column)
```sql
ALTER TABLE insurance_claims 
ADD COLUMN gdrg_tariff_id BIGINT UNSIGNED NULL AFTER c_drg_code,
ADD COLUMN gdrg_amount DECIMAL(10,2) NULL AFTER gdrg_tariff_id,
ADD FOREIGN KEY (gdrg_tariff_id) REFERENCES gdrg_tariffs(id);
```

### Model Definitions

#### GdrgTariff Model
```php
class GdrgTariff extends Model
{
    protected $fillable = [
        'code',
        'name', 
        'mdc_category',
        'tariff_price',
        'age_category',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'tariff_price' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    // Display format: "Name (Code - GHS Price)"
    public function getDisplayNameAttribute(): string
    {
        return "{$this->name} ({$this->code} - GHS {$this->tariff_price})";
    }

    public function scopeActive($query): void
    {
        $query->where('is_active', true);
    }

    public function scopeSearch($query, string $term): void
    {
        $query->where(function ($q) use ($term) {
            $q->where('code', 'like', "%{$term}%")
              ->orWhere('name', 'like', "%{$term}%");
        });
    }
}
```

#### InsuranceClaimDiagnosis Model
```php
class InsuranceClaimDiagnosis extends Model
{
    protected $fillable = [
        'insurance_claim_id',
        'diagnosis_id',
        'is_primary',
    ];

    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
        ];
    }

    public function claim(): BelongsTo
    {
        return $this->belongsTo(InsuranceClaim::class, 'insurance_claim_id');
    }

    public function diagnosis(): BelongsTo
    {
        return $this->belongsTo(Diagnosis::class);
    }
}
```

#### InsuranceClaim Model (Enhanced)
```php
// Add to existing model
public function gdrgTariff(): BelongsTo
{
    return $this->belongsTo(GdrgTariff::class);
}

public function claimDiagnoses(): HasMany
{
    return $this->hasMany(InsuranceClaimDiagnosis::class);
}

public function requiresGdrg(): bool
{
    return $this->patientInsurance?->plan?->provider?->requires_gdrg ?? false;
}
```

#### InsuranceProvider Model (Enhanced)
```php
// Add to $fillable
'requires_gdrg',

// Add to casts
'requires_gdrg' => 'boolean',
```



## Correctness Properties

*A property is a characteristic or behavior that should hold true across all valid executions of a system-essentially, a formal statement about what the system should do. Properties serve as the bridge between human-readable specifications and machine-verifiable correctness guarantees.*

### Property 1: G-DRG Code Uniqueness
*For any* G-DRG tariff creation attempt, if a tariff with the same code already exists, the system should reject the creation and return a validation error.
**Validates: Requirements 1.2**

### Property 2: G-DRG Search Filtering
*For any* search term entered in the G-DRG dropdown, all returned results should contain the search term in either the code or name field.
**Validates: Requirements 1.5, 4.2**

### Property 3: G-DRG Display Format
*For any* G-DRG tariff displayed in the dropdown, the format should be "Name (Code - GHS Price)" where Price is formatted to 2 decimal places.
**Validates: Requirements 4.3**

### Property 4: Tariff Update Isolation
*For any* G-DRG tariff price update, existing vetted claims that reference that tariff should retain their original gdrg_amount value unchanged.
**Validates: Requirements 1.4**

### Property 5: Provider G-DRG Workflow Selection
*For any* insurance claim, if the claim's provider has requires_gdrg=true, the vetting modal should display the G-DRG selector; otherwise, the G-DRG selector should be hidden.
**Validates: Requirements 2.2, 2.3, 10.1**

### Property 6: Modal Data Completeness
*For any* claim displayed in the vetting modal, all required patient information (surname, other names, DOB, gender, folder ID, membership ID), attendance details (type of attendance, dates, service type), and claim metadata (specialty, prescriber, claim check code) should be present.
**Validates: Requirements 3.2, 3.3, 3.4**

### Property 7: Modal Close Without Save
*For any* vetting modal that is closed without clicking "Approve Claim", no changes to the claim record (diagnoses, G-DRG selection, status) should be persisted.
**Validates: Requirements 3.5**

### Property 8: NHIS Claim Total Calculation
*For any* NHIS claim with a selected G-DRG, the grand total should equal: G-DRG tariff price + sum of investigation prices + sum of prescription prices + sum of procedure prices.
**Validates: Requirements 7.1, 7.3**

### Property 9: Non-NHIS Claim Total Calculation
*For any* non-NHIS claim, the grand total should be calculated using the insurance provider's coverage rules (percentage or fixed amounts) applied to the item prices.
**Validates: Requirements 7.2, 10.2, 10.3**

### Property 10: Claim Diagnosis Isolation
*For any* diagnosis added to or removed from a claim, the original consultation's diagnoses should remain unchanged.
**Validates: Requirements 5.2, 5.3**

### Property 11: Diagnosis Search Filtering
*For any* search term entered when adding a diagnosis, all returned results should contain the search term in either the diagnosis name or ICD-10 code.
**Validates: Requirements 5.5**

### Property 12: Claim Items Aggregation
*For any* claim associated with an admission, the displayed investigations, prescriptions, and procedures should include items from both the initial consultation and all ward rounds.
**Validates: Requirements 6.2, 6.3, 6.4, 6.6**

### Property 13: NHIS Approval Requires G-DRG
*For any* NHIS claim approval attempt, if no G-DRG is selected, the system should reject the approval and display an error message.
**Validates: Requirements 4.5, 8.2**

### Property 14: Approval State Transition
*For any* successfully approved claim, the status should be "vetted", vetted_by should be the current user's ID, and vetted_at should be the current timestamp.
**Validates: Requirements 8.3, 8.4**

### Property 15: Export Date Range Filtering
*For any* export request with a date range, all claims in the exported file should have vetted_at within the specified date range, and no claims outside the range should be included.
**Validates: Requirements 9.2**

### Property 16: XML Export Round Trip
*For any* set of vetted claims exported to XML, parsing the XML should produce data that matches the original claim records.
**Validates: Requirements 9.3**

### Property 17: Excel Export Completeness
*For any* claim exported to Excel, all required fields (claim code, patient name, membership ID, provider, dates, amounts, status) should be present in the output.
**Validates: Requirements 9.4**

## Error Handling

### Validation Errors

| Scenario | Error Message | HTTP Status |
|----------|---------------|-------------|
| G-DRG code already exists | "A G-DRG tariff with this code already exists." | 422 |
| Missing required G-DRG fields | "The {field} field is required." | 422 |
| NHIS claim approval without G-DRG | "G-DRG selection is required for NHIS claims." | 422 |
| Invalid date range for export | "The start date must be before the end date." | 422 |
| No claims to export | "No vetted claims found in the selected date range." | 404 |

### Authorization Errors

| Scenario | Error Message | HTTP Status |
|----------|---------------|-------------|
| Unauthorized G-DRG management | "You do not have permission to manage G-DRG tariffs." | 403 |
| Unauthorized claim vetting | "You do not have permission to vet this claim." | 403 |
| Unauthorized export | "You do not have permission to export claims." | 403 |

### System Errors

| Scenario | Handling |
|----------|----------|
| Database connection failure | Log error, return 500 with generic message |
| Export file generation failure | Log error, return 500 with "Export failed" message |
| Invalid import file format | Return 422 with specific format error details |

## Testing Strategy

### Testing Framework
- **Backend**: Pest v4 for feature and unit tests
- **Property-Based Testing**: Use `spatie/pest-plugin-test-time` for time-based tests and custom generators for property tests

### Unit Tests

1. **GdrgTariff Model Tests**
   - Test `getDisplayNameAttribute()` formatting
   - Test `scopeActive()` filtering
   - Test `scopeSearch()` with various terms

2. **ClaimVettingService Tests**
   - Test `calculateNhisTotal()` with various item combinations
   - Test `calculateStandardTotal()` with different coverage rules
   - Test `aggregateAdmissionItems()` for admitted patients

3. **ClaimExportService Tests**
   - Test XML generation format
   - Test Excel column headers and data mapping

### Property-Based Tests

Each correctness property will have a corresponding property-based test:

- **Property 1**: Generate random G-DRG codes, verify uniqueness constraint
- **Property 2**: Generate random search terms, verify all results match
- **Property 4**: Update tariff prices, verify vetted claims unchanged
- **Property 8**: Generate claims with various items, verify total calculation
- **Property 10**: Add/remove diagnoses on claims, verify consultation unchanged
- **Property 12**: Create admissions with ward rounds, verify all items aggregated
- **Property 15**: Generate date ranges, verify export filtering
- **Property 16**: Export to XML, parse back, verify data integrity

### Feature Tests

1. **G-DRG Tariff Management**
   - Test CRUD operations with authorization
   - Test import functionality with valid/invalid files
   - Test search API endpoint

2. **Claim Vetting Modal**
   - Test modal data loading for NHIS claims
   - Test modal data loading for non-NHIS claims
   - Test G-DRG selection and total recalculation
   - Test diagnosis management
   - Test approval workflow

3. **Export Functionality**
   - Test XML export with date range
   - Test Excel export with date range
   - Test empty result handling

### Test Data Factories

```php
// GdrgTariffFactory
GdrgTariff::factory()->create([
    'code' => 'OPDC06A',
    'name' => 'General OPD - Adult',
    'mdc_category' => 'OUT PATIENT',
    'tariff_price' => 55.06,
]);

// InsuranceClaimDiagnosisFactory
InsuranceClaimDiagnosis::factory()->create([
    'insurance_claim_id' => $claim->id,
    'diagnosis_id' => $diagnosis->id,
    'is_primary' => true,
]);
```

## API Endpoints

### G-DRG Tariff Management

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/admin/gdrg-tariffs` | List all G-DRG tariffs |
| POST | `/admin/gdrg-tariffs` | Create new G-DRG tariff |
| PUT | `/admin/gdrg-tariffs/{id}` | Update G-DRG tariff |
| DELETE | `/admin/gdrg-tariffs/{id}` | Delete G-DRG tariff |
| POST | `/admin/gdrg-tariffs/import` | Import G-DRG tariffs from file |
| GET | `/api/gdrg-tariffs/search` | Search G-DRG tariffs (for dropdown) |

### Claim Vetting

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/admin/insurance/claims/{id}/vetting-data` | Get claim data for vetting modal |
| POST | `/admin/insurance/claims/{id}/vet-nhis` | Approve NHIS claim with G-DRG |
| POST | `/admin/insurance/claims/{id}/diagnoses` | Update claim diagnoses |

### Export

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/admin/insurance/claims/export/xml` | Export vetted claims to XML |
| GET | `/admin/insurance/claims/export/excel` | Export vetted claims to Excel |

## File Structure

```
app/
├── Http/
│   ├── Controllers/
│   │   └── Admin/
│   │       ├── GdrgTariffController.php (new)
│   │       ├── InsuranceClaimController.php (modified)
│   │       └── ClaimExportController.php (new)
│   ├── Requests/
│   │   ├── StoreGdrgTariffRequest.php (new)
│   │   ├── UpdateGdrgTariffRequest.php (new)
│   │   ├── ImportGdrgTariffRequest.php (new)
│   │   ├── VetNhisClaimRequest.php (new)
│   │   └── ExportClaimsRequest.php (new)
│   └── Resources/
│       └── GdrgTariffResource.php (new)
├── Models/
│   ├── GdrgTariff.php (new)
│   ├── InsuranceClaimDiagnosis.php (new)
│   ├── InsuranceClaim.php (modified)
│   └── InsuranceProvider.php (modified)
├── Services/
│   ├── ClaimVettingService.php (new)
│   └── ClaimExportService.php (new)
└── Policies/
    └── GdrgTariffPolicy.php (new)

database/
├── migrations/
│   ├── xxxx_create_gdrg_tariffs_table.php (new)
│   ├── xxxx_create_insurance_claim_diagnoses_table.php (new)
│   ├── xxxx_add_requires_gdrg_to_insurance_providers_table.php (new)
│   └── xxxx_add_gdrg_fields_to_insurance_claims_table.php (new)
└── factories/
    ├── GdrgTariffFactory.php (new)
    └── InsuranceClaimDiagnosisFactory.php (new)

resources/js/
├── pages/
│   └── Admin/
│       ├── GdrgTariffs/
│       │   └── Index.tsx (new)
│       └── Insurance/
│           └── Claims/
│               └── Index.tsx (modified - add modal)
└── components/
    └── Insurance/
        ├── VettingModal.tsx (new)
        ├── GdrgSelector.tsx (new)
        ├── DiagnosesManager.tsx (new)
        ├── ClaimItemsTabs.tsx (new)
        └── ExportModal.tsx (new)

routes/
└── insurance.php (modified - add new routes)

tests/
├── Feature/
│   ├── Admin/
│   │   ├── GdrgTariffTest.php (new)
│   │   └── InsuranceClaimVettingTest.php (new)
│   └── Export/
│       └── ClaimExportTest.php (new)
└── Unit/
    └── Services/
        ├── ClaimVettingServiceTest.php (new)
        └── ClaimExportServiceTest.php (new)
```
