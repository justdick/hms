# Design Document: NHIS Claims Integration

## Overview

This feature implements comprehensive NHIS claims integration for the Hospital Management System. The design introduces an NHIS Tariff Master as the single source of truth for NHIS prices, with item mappings linking hospital items to NHIS codes. Coverage calculation for NHIS patients uses Master prices directly (not coverage rule tariffs), while copay amounts remain configurable via coverage rules.

Key capabilities:
- NHIS Tariff Master management with import functionality
- Item-to-NHIS code mapping for drugs, labs, and procedures
- G-DRG tariff management for consultation-level tariffs
- Modified coverage calculation for NHIS (Master prices + copay from rules)
- NHIS-specific CSV export/import (only copay is editable)
- NHIS member management on patient records
- Modal-based claim vetting with G-DRG selection
- Claim batch management for monthly submissions
- XML export in NHIA-compliant format
- Reimbursement tracking (submitted → approved/rejected → paid)
- NHIS claims reports

## Architecture

```
┌─────────────────────────────────────────────────────────────────────────┐
│                           Frontend (React)                               │
├─────────────────────────────────────────────────────────────────────────┤
│  Admin Pages                                                             │
│  ├── NhisTariffs/Index.tsx (Master management)                          │
│  ├── NhisMapping/Index.tsx (Item mapping)                               │
│  ├── GdrgTariffs/Index.tsx (G-DRG management)                           │
│  └── Insurance/Claims/                                                   │
│      ├── Index.tsx (Claims list + VettingModal)                         │
│      └── Batches/Index.tsx (Batch management)                           │
│                                                                          │
│  Components                                                              │
│  ├── VettingModal.tsx                                                   │
│  ├── GdrgSelector.tsx                                                   │
│  ├── DiagnosesManager.tsx                                               │
│  ├── ClaimItemsTabs.tsx                                                 │
│  ├── BatchManager.tsx                                                   │
│  └── ExportModal.tsx                                                    │
├─────────────────────────────────────────────────────────────────────────┤
│                           Backend (Laravel)                              │
├─────────────────────────────────────────────────────────────────────────┤
│  Controllers                                                             │
│  ├── Admin/NhisTariffController.php                                     │
│  ├── Admin/NhisMappingController.php                                    │
│  ├── Admin/GdrgTariffController.php                                     │
│  ├── Admin/InsuranceClaimController.php (enhanced)                      │
│  ├── Admin/ClaimBatchController.php                                     │
│  └── Admin/ClaimExportController.php                                    │
├─────────────────────────────────────────────────────────────────────────┤
│  Services                                                                │
│  ├── InsuranceCoverageService.php (modified for NHIS)                   │
│  ├── NhisTariffService.php                                              │
│  ├── ClaimVettingService.php                                            │
│  ├── ClaimBatchService.php                                              │
│  └── NhisXmlExportService.php                                           │
├─────────────────────────────────────────────────────────────────────────┤
│  Models                                                                  │
│  ├── NhisTariff.php (new)                                               │
│  ├── NhisItemMapping.php (new)                                          │
│  ├── GdrgTariff.php (new)                                               │
│  ├── ClaimBatch.php (new)                                               │
│  ├── ClaimBatchItem.php (new)                                           │
│  ├── InsuranceClaimDiagnosis.php (new)                                  │
│  ├── InsuranceClaimItem.php (enhanced)                                  │
│  ├── InsuranceClaim.php (enhanced)                                      │
│  ├── InsuranceProvider.php (enhanced)                                   │
│  └── Patient.php (enhanced)                                             │
└─────────────────────────────────────────────────────────────────────────┘
```

## Components and Interfaces

### Backend Components

#### 1. NhisTariffController
Manages NHIS Tariff Master CRUD and import operations.

```php
class NhisTariffController extends Controller
{
    public function index(Request $request): Response;
    public function store(StoreNhisTariffRequest $request): RedirectResponse;
    public function update(UpdateNhisTariffRequest $request, NhisTariff $tariff): RedirectResponse;
    public function destroy(NhisTariff $tariff): RedirectResponse;
    public function import(ImportNhisTariffRequest $request): RedirectResponse;
    public function search(Request $request): JsonResponse;
}
```

#### 2. NhisMappingController
Manages item-to-NHIS code mappings.

```php
class NhisMappingController extends Controller
{
    public function index(Request $request): Response;
    public function store(StoreNhisMappingRequest $request): RedirectResponse;
    public function destroy(NhisItemMapping $mapping): RedirectResponse;
    public function import(ImportNhisMappingRequest $request): RedirectResponse;
    public function unmapped(Request $request): Response;
}
```

#### 3. GdrgTariffController
Manages G-DRG tariff CRUD operations.

```php
class GdrgTariffController extends Controller
{
    public function index(Request $request): Response;
    public function store(StoreGdrgTariffRequest $request): RedirectResponse;
    public function update(UpdateGdrgTariffRequest $request, GdrgTariff $tariff): RedirectResponse;
    public function destroy(GdrgTariff $tariff): RedirectResponse;
    public function import(ImportGdrgTariffRequest $request): RedirectResponse;
    public function search(Request $request): JsonResponse;
}
```

#### 4. ClaimBatchController
Manages claim batch operations.

```php
class ClaimBatchController extends Controller
{
    public function index(Request $request): Response;
    public function store(StoreClaimBatchRequest $request): RedirectResponse;
    public function show(ClaimBatch $batch): Response;
    public function addClaims(AddClaimsToBatchRequest $request, ClaimBatch $batch): RedirectResponse;
    public function removeClaim(ClaimBatch $batch, InsuranceClaim $claim): RedirectResponse;
    public function finalize(ClaimBatch $batch): RedirectResponse;
    public function markSubmitted(MarkBatchSubmittedRequest $request, ClaimBatch $batch): RedirectResponse;
    public function recordResponse(RecordBatchResponseRequest $request, ClaimBatch $batch): RedirectResponse;
}
```

#### 5. NhisTariffService
Business logic for NHIS tariff lookups.

```php
class NhisTariffService
{
    public function getTariffForItem(string $itemType, string $itemCode): ?NhisTariff;
    public function getTariffPrice(string $itemType, string $itemCode): ?float;
    public function isItemMapped(string $itemType, string $itemCode): bool;
    public function importTariffs(UploadedFile $file): ImportResult;
}
```

#### 6. InsuranceCoverageService (Modified)
Enhanced to handle NHIS-specific calculations.

```php
// New method for NHIS
public function calculateNhisCoverage(
    int $insurancePlanId,
    string $category,
    string $itemCode,
    int $quantity = 1
): array {
    // 1. Look up NHIS tariff from Master via mapping
    // 2. Get copay from coverage rule
    // 3. Return: insurance_pays = NHIS tariff, patient_pays = copay
}

// Modified calculateCoverage to detect NHIS and delegate
public function calculateCoverage(...): array {
    if ($this->isNhisPlan($insurancePlanId)) {
        return $this->calculateNhisCoverage(...);
    }
    // Existing logic for non-NHIS
}
```

#### 7. NhisXmlExportService
Generates NHIA-compliant XML for batch submission.

```php
class NhisXmlExportService
{
    public function generateXml(ClaimBatch $batch): string;
    public function generateClaimElement(InsuranceClaim $claim): DOMElement;
    public function generateItemElement(InsuranceClaimItem $item): DOMElement;
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

#### 3. BatchManager
Component for managing claim batches.

```tsx
interface BatchManagerProps {
    batches: ClaimBatch[];
    onCreateBatch: (data: CreateBatchData) => void;
    onExportBatch: (batchId: number) => void;
}
```

## Data Models

### New Tables

#### nhis_tariffs
```sql
CREATE TABLE nhis_tariffs (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    nhis_code VARCHAR(50) NOT NULL UNIQUE,
    name VARCHAR(255) NOT NULL,
    category ENUM('medicine', 'lab', 'procedure', 'consultation', 'consumable') NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    unit VARCHAR(50) NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    
    INDEX idx_nhis_code (nhis_code),
    INDEX idx_category (category),
    INDEX idx_name (name)
);
```

#### nhis_item_mappings
```sql
CREATE TABLE nhis_item_mappings (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    item_type ENUM('drug', 'lab_service', 'procedure', 'consumable') NOT NULL,
    item_id BIGINT UNSIGNED NOT NULL,
    item_code VARCHAR(50) NOT NULL,
    nhis_tariff_id BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    
    FOREIGN KEY (nhis_tariff_id) REFERENCES nhis_tariffs(id),
    UNIQUE KEY unique_item_mapping (item_type, item_id),
    INDEX idx_item_code (item_type, item_code)
);
```

#### gdrg_tariffs
```sql
CREATE TABLE gdrg_tariffs (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    code VARCHAR(20) NOT NULL UNIQUE,
    name VARCHAR(255) NOT NULL,
    mdc_category VARCHAR(100) NOT NULL,
    tariff_price DECIMAL(10,2) NOT NULL,
    age_category ENUM('adult', 'child', 'all') DEFAULT 'all',
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    
    INDEX idx_code (code),
    INDEX idx_mdc_category (mdc_category)
);
```

#### claim_batches
```sql
CREATE TABLE claim_batches (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    batch_number VARCHAR(50) NOT NULL UNIQUE,
    name VARCHAR(255) NOT NULL,
    submission_period DATE NOT NULL,
    status ENUM('draft', 'finalized', 'submitted', 'processing', 'completed') DEFAULT 'draft',
    total_claims INT DEFAULT 0,
    total_amount DECIMAL(12,2) DEFAULT 0,
    approved_amount DECIMAL(12,2) NULL,
    paid_amount DECIMAL(12,2) NULL,
    submitted_at TIMESTAMP NULL,
    exported_at TIMESTAMP NULL,
    paid_at TIMESTAMP NULL,
    notes TEXT NULL,
    created_by BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    
    FOREIGN KEY (created_by) REFERENCES users(id),
    INDEX idx_status (status),
    INDEX idx_submission_period (submission_period)
);
```

#### claim_batch_items
```sql
CREATE TABLE claim_batch_items (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    claim_batch_id BIGINT UNSIGNED NOT NULL,
    insurance_claim_id BIGINT UNSIGNED NOT NULL,
    claim_amount DECIMAL(10,2) NOT NULL,
    approved_amount DECIMAL(10,2) NULL,
    status ENUM('pending', 'approved', 'rejected', 'paid') DEFAULT 'pending',
    rejection_reason TEXT NULL,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    
    FOREIGN KEY (claim_batch_id) REFERENCES claim_batches(id) ON DELETE CASCADE,
    FOREIGN KEY (insurance_claim_id) REFERENCES insurance_claims(id),
    UNIQUE KEY unique_batch_claim (claim_batch_id, insurance_claim_id)
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

#### insurance_providers (add columns)
```sql
ALTER TABLE insurance_providers 
ADD COLUMN is_nhis BOOLEAN DEFAULT FALSE AFTER is_active;
```

#### insurance_claims (add columns)
```sql
ALTER TABLE insurance_claims 
ADD COLUMN gdrg_tariff_id BIGINT UNSIGNED NULL,
ADD COLUMN gdrg_amount DECIMAL(10,2) NULL,
ADD COLUMN vetted_by BIGINT UNSIGNED NULL,
ADD COLUMN vetted_at TIMESTAMP NULL,
ADD FOREIGN KEY (gdrg_tariff_id) REFERENCES gdrg_tariffs(id),
ADD FOREIGN KEY (vetted_by) REFERENCES users(id);
```

#### insurance_claim_items (add columns)
```sql
ALTER TABLE insurance_claim_items
ADD COLUMN nhis_tariff_id BIGINT UNSIGNED NULL,
ADD COLUMN nhis_code VARCHAR(50) NULL,
ADD COLUMN nhis_price DECIMAL(10,2) NULL,
ADD FOREIGN KEY (nhis_tariff_id) REFERENCES nhis_tariffs(id);
```

#### patients (add columns)
```sql
ALTER TABLE patients
ADD COLUMN nhis_member_id VARCHAR(50) NULL,
ADD COLUMN nhis_expiry_date DATE NULL,
ADD INDEX idx_nhis_member_id (nhis_member_id);
```

## Correctness Properties

*A property is a characteristic or behavior that should hold true across all valid executions of a system-essentially, a formal statement about what the system should do. Properties serve as the bridge between human-readable specifications and machine-verifiable correctness guarantees.*

### Property 1: NHIS Tariff Import Upsert
*For any* NHIS tariff import containing existing codes, the system should update prices for those codes without creating duplicate records. The count of tariffs with a given code should always be exactly 1.
**Validates: Requirements 1.2, 1.3**

### Property 2: NHIS Tariff Search Filtering
*For any* search term entered in the NHIS tariff search, all returned results should contain the search term in either the code, name, or category field.
**Validates: Requirements 1.4**

### Property 3: NHIS Price Propagation
*For any* NHIS tariff price update in the Master, all subsequent coverage calculations for mapped items should use the new price.
**Validates: Requirements 1.6, 5.5**

### Property 4: Unmapped Items Flagged
*For any* item that is not mapped to an NHIS tariff, the system should mark it as "NHIS Not Covered" during claim generation and exclude it from the NHIS claim total.
**Validates: Requirements 2.6, 5.4, 12.4**

### Property 5: NHIS Mapping Persistence
*For any* item-to-NHIS mapping created, the link between the hospital item and NHIS tariff code should be persisted and retrievable.
**Validates: Requirements 2.2**

### Property 6: Unmapped Items Filter
*For any* search for unmapped items, all returned results should have no NHIS mapping associated with them.
**Validates: Requirements 2.3**

### Property 7: G-DRG Code Uniqueness
*For any* G-DRG tariff creation attempt, if a tariff with the same code already exists, the system should reject the creation and return a validation error.
**Validates: Requirements 3.2**

### Property 8: G-DRG Price Isolation
*For any* G-DRG tariff price update, existing vetted claims that reference that tariff should retain their original gdrg_amount value unchanged.
**Validates: Requirements 3.4**

### Property 9: NHIS Coverage Uses Master Price
*For any* coverage calculation for an NHIS patient with a mapped item, the insurance_pays amount should equal the NHIS Tariff Master price (not the coverage rule tariff_amount).
**Validates: Requirements 4.2, 5.1, 5.2**

### Property 10: NHIS Patient Pays Only Copay
*For any* coverage calculation for an NHIS patient, the patient_pays amount should equal only the copay amount from the coverage rule, with no percentage-based calculation applied.
**Validates: Requirements 5.3**

### Property 11: NHIS CSV Export Contains Master Prices
*For any* NHIS coverage CSV export, the tariff price column for mapped items should match the current NHIS Tariff Master price.
**Validates: Requirements 6.1, 6.2**

### Property 12: NHIS CSV Import Saves Only Copay
*For any* NHIS coverage CSV import, only the copay amount should be saved to the coverage rule. The tariff values in the CSV should be ignored.
**Validates: Requirements 6.3, 6.4**

### Property 13: Expired NHIS Card Warning
*For any* patient with an NHIS card expiry date in the past, the system should display a warning during check-in.
**Validates: Requirements 7.3**

### Property 14: NHIS Visit Flagging
*For any* patient checked in with NHIS as payment type, the visit should be flagged for NHIS claim generation.
**Validates: Requirements 7.5**

### Property 15: Modal Close Without Save
*For any* vetting modal that is closed without clicking "Approve Claim", no changes to the claim record should be persisted.
**Validates: Requirements 8.5**

### Property 16: G-DRG Search Filtering
*For any* search term entered in the G-DRG dropdown, all returned results should contain the search term in either the code or name field.
**Validates: Requirements 9.2, 3.5**

### Property 17: G-DRG Display Format
*For any* G-DRG tariff displayed in the dropdown, the format should be "Name (Code - GHS Price)" where Price is formatted to 2 decimal places.
**Validates: Requirements 9.3**

### Property 18: NHIS Claim Total Calculation
*For any* NHIS claim with a selected G-DRG, the grand total should equal: G-DRG tariff price + sum of mapped investigation prices + sum of mapped prescription prices + sum of mapped procedure prices.
**Validates: Requirements 12.1, 9.4**

### Property 19: G-DRG Required for NHIS Approval
*For any* NHIS claim approval attempt, if no G-DRG is selected, the system should reject the approval and display an error message.
**Validates: Requirements 9.5, 13.2**

### Property 20: Claim Diagnosis Isolation
*For any* diagnosis added to or removed from a claim, the original consultation's diagnoses should remain unchanged.
**Validates: Requirements 10.2, 10.3**

### Property 21: Claim Items Aggregation for Admissions
*For any* claim associated with an admission, the displayed items should include items from both the initial consultation and all ward rounds.
**Validates: Requirements 11.4**

### Property 22: Approval State Transition
*For any* successfully approved claim, the status should be "vetted", vetted_by should be the current user's ID, vetted_at should be the current timestamp, and NHIS prices should be stored on claim items.
**Validates: Requirements 13.3, 13.4, 13.5**

### Property 23: Batch Only Accepts Vetted Claims
*For any* attempt to add a claim to a batch, only claims with status "vetted" should be accepted.
**Validates: Requirements 14.2**

### Property 24: Finalized Batch Immutability
*For any* finalized batch, attempts to add or remove claims should be rejected.
**Validates: Requirements 14.5**

### Property 25: XML Export Round Trip
*For any* claim batch exported to XML, parsing the XML should produce data that matches the original claim records including patient NHIS ID, G-DRG code, diagnoses, and item NHIS codes.
**Validates: Requirements 15.1, 15.2, 15.3**

### Property 26: Batch Status History
*For any* batch status change, the system should maintain a history record with the previous status, new status, and timestamp.
**Validates: Requirements 16.3**

### Property 27: Rejected Claim Resubmission
*For any* rejected claim, the system should allow it to be corrected and added to a new batch for resubmission.
**Validates: Requirements 17.5**

### Property 28: Claims Summary Report Accuracy
*For any* claims summary report for a period, the totals should accurately reflect the sum of all claims in that period grouped by status.
**Validates: Requirements 18.1**

### Property 29: Outstanding Report Accuracy
*For any* outstanding report, all displayed claims should be approved but not yet paid, with correct aging calculation.
**Validates: Requirements 18.2**

### Property 30: Tariff Coverage Report Accuracy
*For any* tariff coverage report, the percentage should accurately reflect the count of mapped items divided by total items.
**Validates: Requirements 18.4**

## Error Handling

### Validation Errors

| Scenario | Error Message | HTTP Status |
|----------|---------------|-------------|
| NHIS tariff code already exists | "An NHIS tariff with this code already exists." | 422 |
| G-DRG code already exists | "A G-DRG tariff with this code already exists." | 422 |
| Item already mapped | "This item is already mapped to an NHIS tariff." | 422 |
| NHIS claim approval without G-DRG | "G-DRG selection is required for NHIS claims." | 422 |
| Adding non-vetted claim to batch | "Only vetted claims can be added to a batch." | 422 |
| Modifying finalized batch | "This batch has been finalized and cannot be modified." | 422 |
| Invalid import file format | "Invalid file format. Please use the provided template." | 422 |

### Authorization Errors

| Scenario | Error Message | HTTP Status |
|----------|---------------|-------------|
| Unauthorized tariff management | "You do not have permission to manage NHIS tariffs." | 403 |
| Unauthorized claim vetting | "You do not have permission to vet claims." | 403 |
| Unauthorized batch management | "You do not have permission to manage claim batches." | 403 |

## Testing Strategy

### Testing Framework
- **Backend**: Pest v4 for feature and unit tests
- **Property-Based Testing**: Custom generators for property tests

### Unit Tests

1. **NhisTariffService Tests**
   - Test `getTariffForItem()` with mapped and unmapped items
   - Test `importTariffs()` with valid and invalid files

2. **InsuranceCoverageService Tests**
   - Test `calculateNhisCoverage()` returns Master price
   - Test `calculateNhisCoverage()` returns only copay for patient
   - Test non-NHIS calculation unchanged

3. **NhisXmlExportService Tests**
   - Test XML structure compliance
   - Test all required fields present

### Property-Based Tests

Key properties to test:
- Property 1: Tariff import upsert behavior
- Property 3: Price propagation after Master update
- Property 9: NHIS coverage uses Master price
- Property 10: Patient pays only copay
- Property 12: CSV import saves only copay
- Property 18: Claim total calculation
- Property 25: XML export round trip

### Feature Tests

1. **NHIS Tariff Management**
   - Test CRUD operations
   - Test import with valid/invalid files
   - Test search functionality

2. **NHIS Item Mapping**
   - Test mapping creation
   - Test unmapped items filter
   - Test bulk import

3. **Coverage Calculation**
   - Test NHIS patient gets Master price
   - Test non-NHIS patient uses existing logic
   - Test unmapped items marked as not covered

4. **Claim Vetting**
   - Test modal data loading
   - Test G-DRG selection required for NHIS
   - Test approval workflow

5. **Batch Management**
   - Test batch creation
   - Test adding/removing claims
   - Test finalization prevents changes
   - Test XML export

## API Endpoints

### NHIS Tariff Management

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/admin/nhis-tariffs` | List all NHIS tariffs |
| POST | `/admin/nhis-tariffs` | Create new NHIS tariff |
| PUT | `/admin/nhis-tariffs/{id}` | Update NHIS tariff |
| DELETE | `/admin/nhis-tariffs/{id}` | Delete NHIS tariff |
| POST | `/admin/nhis-tariffs/import` | Import NHIS tariffs |
| GET | `/api/nhis-tariffs/search` | Search NHIS tariffs |

### NHIS Item Mapping

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/admin/nhis-mappings` | List all mappings |
| POST | `/admin/nhis-mappings` | Create mapping |
| DELETE | `/admin/nhis-mappings/{id}` | Delete mapping |
| POST | `/admin/nhis-mappings/import` | Bulk import mappings |
| GET | `/admin/nhis-mappings/unmapped` | List unmapped items |

### G-DRG Tariff Management

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/admin/gdrg-tariffs` | List all G-DRG tariffs |
| POST | `/admin/gdrg-tariffs` | Create G-DRG tariff |
| PUT | `/admin/gdrg-tariffs/{id}` | Update G-DRG tariff |
| DELETE | `/admin/gdrg-tariffs/{id}` | Delete G-DRG tariff |
| POST | `/admin/gdrg-tariffs/import` | Import G-DRG tariffs |
| GET | `/api/gdrg-tariffs/search` | Search G-DRG tariffs |

### Claim Vetting

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/admin/insurance/claims/{id}/vetting-data` | Get claim data for modal |
| POST | `/admin/insurance/claims/{id}/vet` | Approve claim |
| POST | `/admin/insurance/claims/{id}/diagnoses` | Update claim diagnoses |

### Claim Batches

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/admin/insurance/batches` | List all batches |
| POST | `/admin/insurance/batches` | Create batch |
| GET | `/admin/insurance/batches/{id}` | View batch details |
| POST | `/admin/insurance/batches/{id}/claims` | Add claims to batch |
| DELETE | `/admin/insurance/batches/{id}/claims/{claimId}` | Remove claim from batch |
| POST | `/admin/insurance/batches/{id}/finalize` | Finalize batch |
| POST | `/admin/insurance/batches/{id}/submit` | Mark as submitted |
| POST | `/admin/insurance/batches/{id}/response` | Record NHIA response |
| GET | `/admin/insurance/batches/{id}/export` | Export batch to XML |

### Reports

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/admin/insurance/reports/summary` | Claims summary report |
| GET | `/admin/insurance/reports/outstanding` | Outstanding claims report |
| GET | `/admin/insurance/reports/rejections` | Rejection analysis report |
| GET | `/admin/insurance/reports/coverage` | Tariff coverage report |



## File Structure

```
app/
├── Http/
│   ├── Controllers/
│   │   └── Admin/
│   │       ├── NhisTariffController.php (new)
│   │       ├── NhisMappingController.php (new)
│   │       ├── GdrgTariffController.php (new)
│   │       ├── InsuranceClaimController.php (modified)
│   │       ├── ClaimBatchController.php (new)
│   │       └── ClaimExportController.php (new)
│   ├── Requests/
│   │   ├── StoreNhisTariffRequest.php (new)
│   │   ├── ImportNhisTariffRequest.php (new)
│   │   ├── StoreNhisMappingRequest.php (new)
│   │   ├── ImportNhisMappingRequest.php (new)
│   │   ├── StoreGdrgTariffRequest.php (new)
│   │   ├── ImportGdrgTariffRequest.php (new)
│   │   ├── VetClaimRequest.php (new)
│   │   ├── StoreClaimBatchRequest.php (new)
│   │   ├── AddClaimsToBatchRequest.php (new)
│   │   └── RecordBatchResponseRequest.php (new)
│   └── Resources/
│       ├── NhisTariffResource.php (new)
│       ├── NhisMappingResource.php (new)
│       ├── GdrgTariffResource.php (new)
│       └── ClaimBatchResource.php (new)
├── Models/
│   ├── NhisTariff.php (new)
│   ├── NhisItemMapping.php (new)
│   ├── GdrgTariff.php (new)
│   ├── ClaimBatch.php (new)
│   ├── ClaimBatchItem.php (new)
│   ├── InsuranceClaimDiagnosis.php (new)
│   ├── InsuranceClaim.php (modified)
│   ├── InsuranceClaimItem.php (modified)
│   ├── InsuranceProvider.php (modified)
│   └── Patient.php (modified)
├── Services/
│   ├── InsuranceCoverageService.php (modified)
│   ├── NhisTariffService.php (new)
│   ├── ClaimVettingService.php (new)
│   ├── ClaimBatchService.php (new)
│   └── NhisXmlExportService.php (new)
├── Exports/
│   ├── NhisTariffExport.php (new)
│   ├── NhisCoverageTemplate.php (new)
│   └── ClaimsReportExport.php (new)
├── Imports/
│   ├── NhisTariffImport.php (new)
│   ├── NhisMappingImport.php (new)
│   ├── GdrgTariffImport.php (new)
│   └── NhisCoverageImport.php (new)
└── Policies/
    ├── NhisTariffPolicy.php (new)
    ├── NhisMappingPolicy.php (new)
    ├── GdrgTariffPolicy.php (new)
    └── ClaimBatchPolicy.php (new)

database/
├── migrations/
│   ├── xxxx_create_nhis_tariffs_table.php (new)
│   ├── xxxx_create_nhis_item_mappings_table.php (new)
│   ├── xxxx_create_gdrg_tariffs_table.php (new)
│   ├── xxxx_create_claim_batches_table.php (new)
│   ├── xxxx_create_claim_batch_items_table.php (new)
│   ├── xxxx_create_insurance_claim_diagnoses_table.php (new)
│   ├── xxxx_add_is_nhis_to_insurance_providers_table.php (new)
│   ├── xxxx_add_gdrg_fields_to_insurance_claims_table.php (new)
│   ├── xxxx_add_nhis_fields_to_insurance_claim_items_table.php (new)
│   └── xxxx_add_nhis_fields_to_patients_table.php (new)
└── factories/
    ├── NhisTariffFactory.php (new)
    ├── NhisItemMappingFactory.php (new)
    ├── GdrgTariffFactory.php (new)
    ├── ClaimBatchFactory.php (new)
    └── InsuranceClaimDiagnosisFactory.php (new)

resources/js/
├── pages/
│   └── Admin/
│       ├── NhisTariffs/
│       │   └── Index.tsx (new)
│       ├── NhisMappings/
│       │   └── Index.tsx (new)
│       ├── GdrgTariffs/
│       │   └── Index.tsx (new)
│       └── Insurance/
│           ├── Claims/
│           │   └── Index.tsx (modified)
│           └── Batches/
│               ├── Index.tsx (new)
│               └── Show.tsx (new)
└── components/
    └── Insurance/
        ├── VettingModal.tsx (new)
        ├── GdrgSelector.tsx (new)
        ├── DiagnosesManager.tsx (new)
        ├── ClaimItemsTabs.tsx (new)
        ├── ClaimTotalDisplay.tsx (new)
        ├── BatchManager.tsx (new)
        └── ExportModal.tsx (new)

routes/
└── admin.php (modified - add new routes)

tests/
├── Feature/
│   ├── Admin/
│   │   ├── NhisTariffTest.php (new)
│   │   ├── NhisMappingTest.php (new)
│   │   ├── GdrgTariffTest.php (new)
│   │   ├── ClaimVettingTest.php (new)
│   │   └── ClaimBatchTest.php (new)
│   └── Services/
│       └── NhisCoverageCalculationTest.php (new)
└── Unit/
    └── Services/
        ├── NhisTariffServiceTest.php (new)
        ├── ClaimVettingServiceTest.php (new)
        └── NhisXmlExportServiceTest.php (new)
```
