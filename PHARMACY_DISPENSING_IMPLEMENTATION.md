# Pharmacy Dispensing System - Implementation Plan

## Overview
Complete pharmacy dispensing workflow with two touchpoints: Review (before billing) and Dispense (after payment).

---

## Workflow Sequence

```
1. Doctor Consultation
   └─> Prescribes medications (full qty, no stock check)
   └─> Auto-creates Charges for each prescription
   └─> Charge status: 'pending', Amount: drug price × quantity

2. Patient → Pharmacy (Touchpoint 1: REVIEW)
   └─> Pharmacist reviews prescriptions
   └─> Checks: stock availability, patient affordability
   └─> Actions available:
       - Keep (full dispensing planned)
       - Partial (adjust quantity based on stock)
       - External (patient will buy elsewhere)
       - Cancel (doctor error)
   └─> Updates Charges accordingly
   └─> Prescription status: 'prescribed' → 'reviewed'

3. Patient → Billing/Revenue
   └─> Pays updated bill amount
   └─> Charge status: 'pending' → 'paid'

4. Patient → Pharmacy (Touchpoint 2: DISPENSE)
   └─> Pharmacist verifies payment (based on billing config)
   └─> If paid: Dispense drugs
   └─> If not paid (and no override): Block dispensing
   └─> Create Dispensing record
   └─> Update inventory
   └─> Prescription status: 'reviewed' → 'dispensed'
```

---

## Phase 1: Database Changes

### 1.1 Update Prescriptions Table
**Migration: `update_prescriptions_table_for_dispensing.php`**

```sql
-- Update status enum
ALTER TABLE prescriptions
  MODIFY COLUMN status ENUM(
    'prescribed',           -- Doctor created
    'reviewed',             -- Pharmacy reviewed, billing adjusted
    'dispensed',            -- Fully dispensed
    'partially_dispensed',  -- Partial given
    'not_dispensed',        -- External (won't dispense)
    'cancelled'             -- Cancelled
  ) DEFAULT 'prescribed';

-- Add new columns
ALTER TABLE prescriptions
  ADD COLUMN quantity_to_dispense INT NULL AFTER quantity,
  ADD COLUMN quantity_dispensed INT DEFAULT 0 AFTER quantity_to_dispense,
  ADD COLUMN reviewed_by BIGINT NULL AFTER status,
  ADD COLUMN reviewed_at TIMESTAMP NULL AFTER reviewed_by,
  ADD COLUMN dispensing_notes TEXT NULL,
  ADD COLUMN external_reason VARCHAR(255) NULL;

-- Add foreign key
ALTER TABLE prescriptions
  ADD CONSTRAINT prescriptions_reviewed_by_foreign
  FOREIGN KEY (reviewed_by) REFERENCES users(id);
```

**Status:** ❌ Not Started

---

### 1.2 Update Charges Table
**Migration: `add_prescription_tracking_to_charges_table.php`**

```sql
-- Add prescription tracking
ALTER TABLE charges
  ADD COLUMN prescription_id BIGINT NULL AFTER patient_checkin_id,
  ADD COLUMN original_amount DECIMAL(10,2) NULL AFTER amount,
  ADD COLUMN adjustment_reason VARCHAR(255) NULL AFTER original_amount;

-- Add foreign key
ALTER TABLE charges
  ADD CONSTRAINT charges_prescription_id_foreign
  FOREIGN KEY (prescription_id) REFERENCES prescriptions(id);

-- Add index
ALTER TABLE charges
  ADD INDEX charges_prescription_id_index (prescription_id);
```

**Status:** ❌ Not Started

---

### 1.3 Add Billing Configuration Seeds
**Seeder: Update `BillingSeeder.php` or create `PharmacyBillingConfigSeeder.php`**

```php
BillingConfiguration::setValue(
    'pharmacy.require_payment_before_dispensing',
    true,
    'pharmacy',
    'Require payment before dispensing medications'
);

BillingConfiguration::setValue(
    'pharmacy.allow_partial_dispensing',
    true,
    'pharmacy',
    'Allow partial dispensing when stock is insufficient'
);

BillingConfiguration::setValue(
    'pharmacy.allow_external_prescriptions',
    true,
    'pharmacy',
    'Allow marking prescriptions as externally dispensed'
);

BillingConfiguration::setValue(
    'pharmacy.default_drug_markup_percentage',
    20,
    'pharmacy',
    'Default markup percentage for drug pricing'
);
```

**Status:** ❌ Not Started

---

## Phase 2: Services Layer

### 2.1 PharmacyStockService
**File: `app/Services/PharmacyStockService.php`**

**Methods:**
- `checkAvailability(Drug $drug, int $quantity): array`
  - Returns: `['available' => bool, 'in_stock' => int, 'shortage' => int]`
- `getAvailableBatches(Drug $drug, int $quantity): Collection`
  - Returns batches sorted by expiry date
- `reserveStock(Drug $drug, int $quantity, Prescription $prescription): bool`
- `releaseReservation(Prescription $prescription): bool`

**Status:** ❌ Not Started

---

### 2.2 PharmacyBillingService
**File: `app/Services/PharmacyBillingService.php`**

**Methods:**
- `createChargeForPrescription(Prescription $prescription): Charge`
  - Auto-called when prescription is created
- `updateChargeForReview(Prescription $prescription, int $newQuantity, ?string $reason): Charge`
  - Adjusts charge when quantity changed during review
- `voidChargeForExternal(Prescription $prescription, string $reason): Charge`
  - Voids charge when marked as external
- `canDispense(Prescription $prescription): bool`
  - Checks payment status based on billing config

**Status:** ❌ Not Started

---

### 2.3 DispensingService
**File: `app/Services/DispensingService.php`**

**Methods:**
- `reviewPrescription(Prescription $prescription, array $data, User $reviewer): Prescription`
  - Updates quantity_to_dispense, status, reviewed_by, reviewed_at
  - Calls PharmacyBillingService to update charges
- `dispensePrescription(Prescription $prescription, array $data, User $dispenser): Dispensing`
  - Creates Dispensing record
  - Updates drug inventory (via DrugBatch)
  - Updates prescription status
- `partialDispense(Prescription $prescription, int $quantity, array $data, User $dispenser): Dispensing`
  - Similar to dispense but tracks partial quantity
- `validatePaymentStatus(Prescription $prescription): bool`

**Status:** ❌ Not Started

---

## Phase 3: Models & Observers

### 3.1 Update Prescription Model
**File: `app/Models/Prescription.php`**

**Add Relationships:**
```php
public function charge(): HasOne
{
    return $this->hasOne(Charge::class);
}

public function reviewedBy(): BelongsTo
{
    return $this->belongsTo(User::class, 'reviewed_by');
}

public function dispensing(): HasOne
{
    return $this->hasOne(Dispensing::class);
}
```

**Add Methods:**
```php
public function isPrescribed(): bool
public function isReviewed(): bool
public function isDispensed(): bool
public function isPartiallyDispensed(): bool
public function isNotDispensed(): bool
public function getRemainingQuantity(): int
public function canBeReviewed(): bool
public function canBeDispensed(): bool
```

**Status:** ❌ Not Started

---

### 3.2 Create PrescriptionObserver
**File: `app/Observers/PrescriptionObserver.php`**

```php
class PrescriptionObserver
{
    public function created(Prescription $prescription): void
    {
        // Auto-create charge when prescription is created
        if ($prescription->drug_id) {
            app(PharmacyBillingService::class)
                ->createChargeForPrescription($prescription);
        }
    }

    public function updating(Prescription $prescription): void
    {
        // Track status changes, update charges if needed
    }
}
```

**Register in:** `app/Providers/AppServiceProvider.php`

**Status:** ❌ Not Started

---

### 3.3 Update Charge Model
**File: `app/Models/Charge.php`**

**Add Relationship:**
```php
public function prescription(): BelongsTo
{
    return $this->belongsTo(Prescription::class);
}
```

**Status:** ❌ Not Started

---

## Phase 4: Policies & Permissions

### 4.1 Update DispensingPolicy
**File: `app/Policies/DispensingPolicy.php`**

**Add Methods:**
```php
// View permissions
public function viewAny(User $user): bool
public function view(User $user, Dispensing $dispensing): bool

// Review (Touchpoint 1)
public function review(User $user, Prescription $prescription): bool
public function adjustQuantity(User $user, Prescription $prescription): bool
public function markExternal(User $user, Prescription $prescription): bool

// Dispense (Touchpoint 2)
public function create(User $user): bool
public function dispense(User $user, Prescription $prescription): bool
public function partialDispense(User $user, Prescription $prescription): bool
public function overridePayment(User $user, Prescription $prescription): bool

// History
public function viewHistory(User $user): bool
```

**Status:** ❌ Not Started

---

### 4.2 Add Permissions
**File: `database/seeders/PermissionSeeder.php`**

```php
// Pharmacy Module
'pharmacy.view',
'pharmacy.manage',

// Dispensing - View
'dispensing.view',
'dispensing.view-all',

// Dispensing - Review (Touchpoint 1)
'dispensing.review',
'dispensing.adjust-quantity',
'dispensing.mark-external',

// Dispensing - Dispense (Touchpoint 2)
'dispensing.process',
'dispensing.partial',
'dispensing.override-payment',

// Dispensing - History
'dispensing.history',
'dispensing.reports',
```

**Status:** ❌ Not Started

---

## Phase 5: Controllers

### 5.1 Update DispensingController
**File: `app/Http/Controllers/Pharmacy/DispensingController.php`**

**Methods to Add/Update:**

```php
// Touchpoint 1: Review
public function review(Patient $patient): Response
{
    // Show review page with prescriptions + stock availability
}

public function updateReview(Request $request, Patient $patient): RedirectResponse
{
    // Process review changes
    // Update prescriptions (quantity_to_dispense, status)
    // Update charges
}

// Touchpoint 2: Dispense
public function dispense(Patient $patient): Response
{
    // Show dispense page
    // Check payment status
    // Show only reviewed prescriptions ready to dispense
}

public function processDispensing(Request $request, Prescription $prescription): RedirectResponse
{
    // Validate payment
    // Create Dispensing record
    // Update inventory
    // Update prescription status
}

// Utilities
public function checkStock(Drug $drug): JsonResponse
{
    // AJAX endpoint for stock checking
}
```

**Status:** ❌ Not Started

---

### 5.2 Create Form Requests

**Files to Create:**
- `app/Http/Requests/ReviewPrescriptionRequest.php`
- `app/Http/Requests/DispensePrescriptionRequest.php`

**Status:** ❌ Not Started

---

## Phase 6: Frontend Components

### 6.1 Prescription Review Page (Touchpoint 1)
**File: `resources/js/pages/Pharmacy/Dispensing/Review.tsx`**

**Features:**
- Data table showing all patient prescriptions
- Stock availability indicators (✅ In Stock, ⚠️ Partial, ❌ Out)
- Quantity adjustment dropdowns
- Action buttons: Keep / Partial / External / Cancel
- Real-time bill calculation
- Save review button

**Status:** ❌ Not Started

---

### 6.2 Prescription Dispense Page (Touchpoint 2)
**File: `resources/js/pages/Pharmacy/Dispensing/Dispense.tsx`**

**Features:**
- Data table showing reviewed prescriptions
- Payment status indicator
- Batch selection for each drug
- Expiry date warnings
- Dispense buttons (individual or bulk)
- Payment override option (if permitted)

**Status:** ❌ Not Started

---

### 6.3 Shared Components

**Files to Create:**
- `resources/js/components/Pharmacy/StockIndicator.tsx`
- `resources/js/components/Pharmacy/PrescriptionStatusBadge.tsx`
- `resources/js/components/Pharmacy/PaymentStatusCard.tsx`
- `resources/js/components/Pharmacy/BatchSelector.tsx`

**Status:** ❌ Not Started

---

## Phase 7: Routes

### 7.1 Update Pharmacy Routes
**File: `routes/pharmacy.php`**

```php
// Dispensing Workflow
Route::get('dispensing', [DispensingController::class, 'index'])
    ->name('dispensing.index');

Route::get('dispensing/search', [DispensingController::class, 'search'])
    ->name('dispensing.search');

Route::get('dispensing/patients/{patient}', [DispensingController::class, 'show'])
    ->name('dispensing.show');

// Touchpoint 1: Review
Route::get('dispensing/patients/{patient}/review', [DispensingController::class, 'review'])
    ->name('dispensing.review')
    ->middleware('can:dispensing.review');

Route::post('dispensing/patients/{patient}/review', [DispensingController::class, 'updateReview'])
    ->name('dispensing.review.update')
    ->middleware('can:dispensing.review');

// Touchpoint 2: Dispense
Route::get('dispensing/patients/{patient}/dispense', [DispensingController::class, 'dispense'])
    ->name('dispensing.dispense')
    ->middleware('can:dispensing.process');

Route::post('prescriptions/{prescription}/dispense', [DispensingController::class, 'processDispensing'])
    ->name('dispensing.process')
    ->middleware('can:dispensing.process');

// Utilities
Route::get('drugs/{drug}/stock', [DispensingController::class, 'checkStock'])
    ->name('drugs.check-stock');
```

**Status:** ❌ Not Started

---

## Phase 8: Testing

### 8.1 Feature Tests

**Files to Create:**
- `tests/Feature/Pharmacy/PrescriptionReviewTest.php`
- `tests/Feature/Pharmacy/PrescriptionDispensingTest.php`
- `tests/Feature/Pharmacy/PharmacyBillingTest.php`
- `tests/Feature/Pharmacy/StockCheckingTest.php`

**Test Cases:**
- ✅ Auto-create charge when prescription created
- ✅ Review prescription and adjust quantity
- ✅ Mark prescription as external (voids charge)
- ✅ Partial dispensing when insufficient stock
- ✅ Block dispensing when payment not made
- ✅ Override payment with proper permission
- ✅ Update inventory on dispensing
- ✅ Permissions enforcement

**Status:** ❌ Not Started

---

## Implementation Checklist

### Phase 1: Database ❌
- [ ] Migration: Update prescriptions table
- [ ] Migration: Update charges table
- [ ] Seeder: Billing configuration for pharmacy
- [ ] Run migrations and seeders

### Phase 2: Services ❌
- [ ] Create PharmacyStockService
- [ ] Create PharmacyBillingService
- [ ] Create DispensingService

### Phase 3: Models & Observers ❌
- [ ] Update Prescription model
- [ ] Create PrescriptionObserver
- [ ] Update Charge model
- [ ] Register observer in AppServiceProvider

### Phase 4: Policies & Permissions ❌
- [ ] Update DispensingPolicy
- [ ] Add permissions to PermissionSeeder
- [ ] Assign permissions to roles

### Phase 5: Controllers ❌
- [ ] Update DispensingController (review methods)
- [ ] Update DispensingController (dispense methods)
- [ ] Create ReviewPrescriptionRequest
- [ ] Create DispensePrescriptionRequest

### Phase 6: Frontend ❌
- [ ] Create Review.tsx page
- [ ] Create Dispense.tsx page
- [ ] Create StockIndicator component
- [ ] Create PrescriptionStatusBadge component
- [ ] Create PaymentStatusCard component
- [ ] Create BatchSelector component

### Phase 7: Routes ❌
- [ ] Update pharmacy routes
- [ ] Test all routes

### Phase 8: Testing ❌
- [ ] Write feature tests
- [ ] Run all tests
- [ ] Fix any failing tests

---

## Notes & Considerations

### Business Rules
1. **Payment Before Dispensing**: Controlled by `pharmacy.require_payment_before_dispensing` config
2. **Partial Dispensing**: Allowed when `pharmacy.allow_partial_dispensing` is true
3. **External Prescriptions**: Voids the charge, no inventory impact
4. **Stock Checking**: Real-time, shows available quantity from all batches
5. **Batch Selection**: FIFO (First In, First Out) by default, but pharmacist can override

### Security
- All actions protected by policies
- Payment override requires special permission
- Audit trail: reviewed_by, dispensed_by tracked

### Performance
- Stock checking should be cached (5 minutes)
- Batch queries optimized with proper indexes
- Consider pagination for large prescription lists

### Future Enhancements
- SMS notifications when prescriptions ready
- Barcode scanning for batch selection
- Integration with external pharmacy systems
- Automated stock reordering

---

## Current Status: Planning Phase ✅
**Next Step:** Begin Phase 1 - Database Changes
