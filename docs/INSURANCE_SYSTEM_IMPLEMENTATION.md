# Insurance System Implementation Plan

## Overview

This document outlines the complete implementation plan for integrating insurance providers into the Hospital Management System (HMS). The system will support insurance coverage for services and drugs, with patient co-payments/top-ups where applicable.

---

## Key Concepts

### Core Features
- **Insurance Providers** - Companies offering coverage (NHIS, VET Insurance, AAR, etc.)
- **Insurance Plans** - Different coverage levels per provider (Gold, Basic, Premium)
- **Patient Insurance Enrollment** - Link patients to active insurance coverage with membership IDs
- **Service Coverage Rules** - Define which services/drugs are covered and at what percentage
- **Co-payments & Top-ups** - Patients pay a portion, insurance covers the rest
- **Claims Management** - Track what's billed to insurance vs patient
- **Claim Check Code (CCC)** - Unique code manually entered at check-in to link all visit services

### Important Design Decisions

✅ **CCC is MANUALLY entered** by receptionist at check-in (NOT auto-generated)
✅ **CCC is NOT displayed everywhere** - Only in claims list and vetting modal
✅ **Modal-based vetting** - Officer reviews claims in a modal, not separate pages
✅ **List-first approach** - Claims dashboard shows all insured patients, filter/search to find specific claims
✅ **Auto-linking** - All services during an insured visit automatically link to the claim

### ⚠️ CRITICAL: Payment Collection Model

✅ **Service Providers** (Pharmacy, Lab, Nursing, Ward) - **DISPLAY coverage info ONLY**, NO payment collection
✅ **Billing/Cashier** - **SINGLE POINT** for all payment collection (centralized model)
✅ **Service Charge Rules Integration** - Insurance respects existing payment enforcement rules (before_service, after_service, etc.)
✅ **Payment Timing** - Follows Service Charge Rules per service type (some require payment before, some after)
✅ **Coverage Display** - Service points show insurance coverage to inform patient what they'll owe
✅ **Insurance Claims** - Hospital submits claims to insurance AFTER patient discharged and copays collected

### 🔗 Integration with Service Charge Rules

The insurance system **integrates with** your existing Service Charge Rules system:

**For Services with `payment_required: 'mandatory'` and `payment_timing: 'before_service'`:**
- Patient **MUST** pay copay at billing **BEFORE** service delivery
- Example: Lab tests with strict enforcement, patient pays copay → lab processes
- Insurance coverage doesn't bypass payment rules

**For Services with `payment_required: 'optional'` or `payment_timing: 'after_service'`:**
- Service provided immediately, patient pays copay at discharge
- Example: Pharmacy with flexible policy, dispense → patient pays later at billing
- Coverage shown for information, copay collected before discharge

**For Services with `service_blocking_enabled: true`:**
- Service blocked until payment (even if just copay)
- Example: Consultation fee copay must be paid before doctor can start
- Billing collects copay → Service unblocked

**For Services with `hide_details_until_paid: true`:**
- Lab details hidden until copay paid (if any)
- Example: Lab test 80% covered, patient pays GHS 5 copay → details revealed
- Even with insurance, payment rule still applies to patient portion

---

## Database Schema

### 1. Insurance Providers Table
```sql
CREATE TABLE insurance_providers (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(191) NOT NULL,
    code VARCHAR(50) UNIQUE NOT NULL,
    contact_person VARCHAR(191),
    phone VARCHAR(191),
    email VARCHAR(191),
    address TEXT,
    claim_submission_method ENUM('online', 'manual', 'api') DEFAULT 'manual',
    payment_terms_days INT DEFAULT 30,
    is_active BOOLEAN DEFAULT TRUE,
    notes TEXT,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);
```

**Example Data:**
- VET Insurance, NHIS, AAR Health, Jubilee Insurance

---

### 2. Insurance Plans Table
```sql
CREATE TABLE insurance_plans (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    insurance_provider_id BIGINT UNSIGNED,
    plan_name VARCHAR(191) NOT NULL,
    plan_code VARCHAR(50) NOT NULL,
    plan_type ENUM('individual', 'family', 'corporate') DEFAULT 'individual',
    coverage_type ENUM('inpatient', 'outpatient', 'comprehensive') DEFAULT 'comprehensive',
    annual_limit DECIMAL(12,2),
    visit_limit INT,
    default_copay_percentage DECIMAL(5,2),
    requires_referral BOOLEAN DEFAULT FALSE,
    is_active BOOLEAN DEFAULT TRUE,
    effective_from DATE,
    effective_to DATE,
    description TEXT,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,

    FOREIGN KEY (insurance_provider_id) REFERENCES insurance_providers(id),
    UNIQUE KEY unique_plan_code_per_provider (insurance_provider_id, plan_code)
);
```

**Example Data:**
- VET Insurance - Gold Plan (100% coverage)
- NHIS - Standard Plan (90% coverage, 10% copay)
- AAR Health - Premium Plan (100% inpatient, 80% outpatient)

---

### 3. Patient Insurance Table
```sql
CREATE TABLE patient_insurance (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    patient_id BIGINT UNSIGNED NOT NULL,
    insurance_plan_id BIGINT UNSIGNED NOT NULL,
    membership_id VARCHAR(191) NOT NULL,
    policy_number VARCHAR(191),
    folder_id_prefix VARCHAR(50),
    is_dependent BOOLEAN DEFAULT FALSE,
    principal_member_name VARCHAR(191),
    relationship_to_principal ENUM('self', 'spouse', 'child', 'parent', 'other'),
    coverage_start_date DATE NOT NULL,
    coverage_end_date DATE,
    status ENUM('active', 'expired', 'suspended', 'cancelled') DEFAULT 'active',
    card_number VARCHAR(191),
    notes TEXT,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,

    FOREIGN KEY (patient_id) REFERENCES patients(id),
    FOREIGN KEY (insurance_plan_id) REFERENCES insurance_plans(id),
    INDEX idx_membership (membership_id),
    INDEX idx_status (status)
);
```

**Example Data:**
- Patient: MARFO EUGENE, Membership ID: 13879209, VET Gold Plan

---

### 4. Insurance Coverage Rules Table
```sql
CREATE TABLE insurance_coverage_rules (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    insurance_plan_id BIGINT UNSIGNED NOT NULL,
    coverage_category ENUM('consultation', 'drug', 'lab', 'procedure', 'ward', 'nursing') NOT NULL,
    item_code VARCHAR(191),
    item_description VARCHAR(191),
    is_covered BOOLEAN DEFAULT TRUE,
    coverage_type ENUM('percentage', 'fixed', 'full', 'excluded') DEFAULT 'percentage',
    coverage_value DECIMAL(10,2) DEFAULT 100.00,
    patient_copay_percentage DECIMAL(5,2) DEFAULT 0.00,
    max_quantity_per_visit INT,
    max_amount_per_visit DECIMAL(10,2),
    requires_preauthorization BOOLEAN DEFAULT FALSE,
    is_active BOOLEAN DEFAULT TRUE,
    effective_from DATE,
    effective_to DATE,
    notes TEXT,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,

    FOREIGN KEY (insurance_plan_id) REFERENCES insurance_plans(id),
    INDEX idx_plan_category (insurance_plan_id, coverage_category)
);
```

**Example Rules:**
- VET Gold: All generic drugs 100% covered
- NHIS Standard: Consultations 90% covered, patient pays 10%
- AAR Premium: Lab tests 80% covered, patient pays 20%

---

### 5. Insurance Tariffs Table
```sql
CREATE TABLE insurance_tariffs (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    insurance_plan_id BIGINT UNSIGNED NOT NULL,
    item_type ENUM('drug', 'service', 'lab', 'procedure', 'ward') NOT NULL,
    item_code VARCHAR(191) NOT NULL,
    item_description VARCHAR(191),
    standard_price DECIMAL(10,2) NOT NULL,
    insurance_tariff DECIMAL(10,2) NOT NULL,
    effective_from DATE NOT NULL,
    effective_to DATE,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,

    FOREIGN KEY (insurance_plan_id) REFERENCES insurance_plans(id),
    UNIQUE KEY unique_tariff (insurance_plan_id, item_type, item_code, effective_from)
);
```

**Purpose:**
Insurance companies may pay different rates than standard hospital prices. If a tariff exists, use it; otherwise use standard price.

---

### 6. Insurance Claims Table
```sql
CREATE TABLE insurance_claims (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    claim_check_code VARCHAR(50) UNIQUE NOT NULL,
    folder_id VARCHAR(50),
    patient_id BIGINT UNSIGNED NOT NULL,
    patient_insurance_id BIGINT UNSIGNED NOT NULL,
    patient_checkin_id BIGINT UNSIGNED,
    consultation_id BIGINT UNSIGNED,
    patient_admission_id BIGINT UNSIGNED,

    -- Patient details (denormalized snapshot)
    patient_surname VARCHAR(191),
    patient_other_names VARCHAR(191),
    patient_dob DATE,
    patient_gender ENUM('male', 'female'),
    membership_id VARCHAR(191),

    -- Visit details
    date_of_attendance DATE NOT NULL,
    date_of_discharge DATE,
    type_of_service ENUM('inpatient', 'outpatient') NOT NULL,
    type_of_attendance ENUM('emergency', 'acute', 'routine') DEFAULT 'routine',
    specialty_attended VARCHAR(191),
    attending_prescriber VARCHAR(191),
    is_unbundled BOOLEAN DEFAULT FALSE,
    is_pharmacy_included BOOLEAN DEFAULT TRUE,

    -- Diagnosis
    primary_diagnosis_code VARCHAR(20),
    primary_diagnosis_description VARCHAR(191),
    secondary_diagnoses JSON,
    c_drg_code VARCHAR(50),
    hin_number VARCHAR(191),

    -- Financial
    total_claim_amount DECIMAL(12,2) DEFAULT 0.00,
    approved_amount DECIMAL(12,2) DEFAULT 0.00,
    patient_copay_amount DECIMAL(12,2) DEFAULT 0.00,
    insurance_covered_amount DECIMAL(12,2) DEFAULT 0.00,

    -- Workflow status
    status ENUM('draft', 'pending_vetting', 'vetted', 'submitted', 'approved', 'rejected', 'paid', 'partial') DEFAULT 'draft',
    vetted_by BIGINT UNSIGNED,
    vetted_at TIMESTAMP,
    submitted_by BIGINT UNSIGNED,
    submitted_at TIMESTAMP,
    submission_date DATE,
    approval_date DATE,
    payment_date DATE,
    rejection_reason TEXT,
    notes TEXT,

    created_at TIMESTAMP,
    updated_at TIMESTAMP,

    FOREIGN KEY (patient_id) REFERENCES patients(id),
    FOREIGN KEY (patient_insurance_id) REFERENCES patient_insurance(id),
    FOREIGN KEY (patient_checkin_id) REFERENCES patient_checkins(id),
    FOREIGN KEY (vetted_by) REFERENCES users(id),
    FOREIGN KEY (submitted_by) REFERENCES users(id),

    INDEX idx_claim_check_code (claim_check_code),
    INDEX idx_status (status),
    INDEX idx_attendance_date (date_of_attendance)
);
```

**Key Fields:**
- `claim_check_code` - Unique code manually entered at check-in (e.g., "71834")
- `status` - Tracks claim lifecycle: draft → vetted → submitted → paid
- Patient/visit details denormalized for historical snapshot

---

### 7. Insurance Claim Items Table
```sql
CREATE TABLE insurance_claim_items (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    insurance_claim_id BIGINT UNSIGNED NOT NULL,
    charge_id BIGINT UNSIGNED,
    item_date DATE NOT NULL,
    item_type ENUM('consultation', 'drug', 'lab', 'procedure', 'ward', 'nursing') NOT NULL,

    -- Item details
    code VARCHAR(191) NOT NULL,
    description TEXT NOT NULL,
    quantity INT DEFAULT 1,
    unit_tariff DECIMAL(10,2) NOT NULL,
    subtotal DECIMAL(10,2) NOT NULL,

    -- Coverage split
    is_covered BOOLEAN DEFAULT TRUE,
    coverage_percentage DECIMAL(5,2),
    insurance_pays DECIMAL(10,2) DEFAULT 0.00,
    patient_pays DECIMAL(10,2) DEFAULT 0.00,

    -- Vetting
    is_approved BOOLEAN DEFAULT FALSE,
    rejection_reason TEXT,
    notes TEXT,

    created_at TIMESTAMP,
    updated_at TIMESTAMP,

    FOREIGN KEY (insurance_claim_id) REFERENCES insurance_claims(id) ON DELETE CASCADE,
    FOREIGN KEY (charge_id) REFERENCES charges(id),

    INDEX idx_claim_type (insurance_claim_id, item_type)
);
```

**Purpose:**
Every service/drug/lab during an insured visit creates a claim item. These are reviewed during vetting.

---

### 8. Modifications to Existing Tables

#### A. Patient Checkins Table
```sql
ALTER TABLE patient_checkins ADD COLUMN claim_check_code VARCHAR(50) UNIQUE AFTER status;
ALTER TABLE patient_checkins ADD INDEX idx_claim_check_code (claim_check_code);
```

**Why:**
Store the CCC at check-in level so all services during that visit can auto-link to the claim.

#### B. Charges Table
```sql
ALTER TABLE charges ADD COLUMN insurance_claim_id BIGINT UNSIGNED AFTER prescription_id;
ALTER TABLE charges ADD COLUMN insurance_claim_item_id BIGINT UNSIGNED AFTER insurance_claim_id;
ALTER TABLE charges ADD COLUMN is_insurance_claim BOOLEAN DEFAULT FALSE AFTER status;
ALTER TABLE charges ADD COLUMN insurance_tariff_amount DECIMAL(10,2) AFTER amount;
ALTER TABLE charges ADD COLUMN insurance_covered_amount DECIMAL(10,2) DEFAULT 0.00 AFTER paid_amount;
ALTER TABLE charges ADD COLUMN patient_copay_amount DECIMAL(10,2) DEFAULT 0.00 AFTER insurance_covered_amount;

ALTER TABLE charges ADD FOREIGN KEY (insurance_claim_id) REFERENCES insurance_claims(id);
ALTER TABLE charges ADD FOREIGN KEY (insurance_claim_item_id) REFERENCES insurance_claim_items(id);
ALTER TABLE charges ADD INDEX idx_insurance_claim (insurance_claim_id);
```

**Why:**
Track insurance vs patient portion for every charge. Links charges to claims for reconciliation.

---

## Business Logic & Workflows

### Workflow 1: Patient Check-in WITH Insurance

**Step 1: Receptionist checks in patient**
```
1. Select patient: MARFO EUGENE
2. System detects: Active insurance (VET Gold Plan, Member: 13879209)
3. Dialog appears:
   ┌────────────────────────────────────────────┐
   │ Patient has Active Insurance               │
   │ VET Insurance - Gold Plan                  │
   │ Coverage: Valid until 2026-12-31           │
   │                                            │
   │ Claim Check Code: [________]  ← MANUAL     │
   │                                            │
   │ [✓ Create Insured Visit] [Cash Visit]     │
   └────────────────────────────────────────────┘
4. Receptionist enters CCC from insurance card: "71834"
5. System validates uniqueness
6. Creates:
   - patient_checkins.claim_check_code = "71834"
   - insurance_claims record (status: 'draft')
7. Patient proceeds to consultation
```

**Validation:**
- CCC must be unique across all active claims
- Insurance coverage must be active (within date range)
- Insurance plan must be active

---

### Workflow 2: Service Delivery & Coverage Display

**At Service Points (Pharmacy, Lab, Ward) - INFORMATION DISPLAY ONLY:**

```php
// Pseudo-code
function createCharge($serviceData) {
    $checkin = PatientCheckin::find($serviceData['patient_checkin_id']);

    // Check if this visit has an active insurance claim
    if ($checkin->claim_check_code) {
        $claim = InsuranceClaim::where('claim_check_code', $checkin->claim_check_code)->first();
        $insurancePlan = $claim->patientInsurance->insurancePlan;

        // Find coverage rule for this service
        $coverageRule = InsuranceCoverageRule::where('insurance_plan_id', $insurancePlan->id)
            ->where('item_code', $serviceData['service_code'])
            ->where('is_active', true)
            ->first();

        if ($coverageRule && $coverageRule->is_covered) {
            // Get insurance tariff or use standard price
            $tariff = InsuranceTariff::where('insurance_plan_id', $insurancePlan->id)
                ->where('item_code', $serviceData['service_code'])
                ->where('effective_from', '<=', now())
                ->whereNull('effective_to')
                ->first()?->insurance_tariff ?? $serviceData['amount'];

            // Calculate split
            if ($coverageRule->coverage_type === 'percentage') {
                $insurancePays = $tariff * ($coverageRule->coverage_value / 100);
                $patientPays = $tariff - $insurancePays;
            } elseif ($coverageRule->coverage_type === 'full') {
                $insurancePays = $tariff;
                $patientPays = 0;
            } elseif ($coverageRule->coverage_type === 'fixed') {
                $insurancePays = min($coverageRule->coverage_value, $tariff);
                $patientPays = $tariff - $insurancePays;
            }

            // Create charge with insurance split
            $charge = Charge::create([
                'patient_checkin_id' => $checkin->id,
                'service_type' => $serviceData['service_type'],
                'service_code' => $serviceData['service_code'],
                'description' => $serviceData['description'],
                'amount' => $tariff,
                'insurance_claim_id' => $claim->id,
                'is_insurance_claim' => true,
                'insurance_tariff_amount' => $tariff,
                'insurance_covered_amount' => $insurancePays * $serviceData['quantity'],
                'patient_copay_amount' => $patientPays * $serviceData['quantity'],
                'status' => 'pending',
                // ... other fields
            ]);

            // Create claim item
            InsuranceClaimItem::create([
                'insurance_claim_id' => $claim->id,
                'charge_id' => $charge->id,
                'item_date' => now()->toDateString(),
                'item_type' => $serviceData['item_type'],
                'code' => $serviceData['service_code'],
                'description' => $serviceData['description'],
                'quantity' => $serviceData['quantity'],
                'unit_tariff' => $tariff,
                'subtotal' => $tariff * $serviceData['quantity'],
                'coverage_percentage' => $coverageRule->coverage_value,
                'insurance_pays' => $insurancePays * $serviceData['quantity'],
                'patient_pays' => $patientPays * $serviceData['quantity'],
                'is_approved' => false,
            ]);

            // Update claim total
            $claim->increment('total_claim_amount', $tariff * $serviceData['quantity']);
            $claim->increment('insurance_covered_amount', $insurancePays * $serviceData['quantity']);
            $claim->increment('patient_copay_amount', $patientPays * $serviceData['quantity']);
        }
    }

    // If no insurance or not covered, create normal charge
}
```

**Examples:**

**Example A: Drug 100% Covered**
```
Drug: Artemether 20mg+Lumefantrine 120mg
Hospital Price: GHS 2.24
Insurance Tariff: GHS 2.24 (same)
Coverage Rule: 100% covered

Result:
- Insurance pays: GHS 2.24
- Patient pays: GHS 0.00
- Pharmacist displays: "Covered 100% - Patient owes: GHS 0.00"
```

**Example B: Lab Test 80% Covered**
```
Lab: Complete Blood Count (CBC)
Hospital Price: GHS 30.00
Insurance Tariff: GHS 28.00 (insurer pays less)
Coverage Rule: 80% covered, 20% copay

Result:
- Insurance pays: GHS 22.40 (80% of GHS 28.00)
- Patient pays: GHS 5.60 (20% of GHS 28.00)
- Lab displays: "Covered 80% - Patient copay: GHS 5.60"
```

**Example C: Service Not Covered**
```
Service: Cosmetic Procedure
Hospital Price: GHS 150.00
Coverage Rule: Not covered (excluded)

Result:
- Insurance pays: GHS 0.00
- Patient pays: GHS 150.00
- Normal cash payment required
```

---

### Workflow 3: Service Charge Rules + Insurance Integration Examples

**Example A: Lab Test - Strict Enforcement (Payment BEFORE Service)**
```
Lab Test: Complete Blood Count (CBC)
Hospital Price: GHS 30.00
Insurance Coverage: 80% (VET Gold)
Service Charge Rule:
  - payment_required: 'mandatory'
  - payment_timing: 'before_service'
  - service_blocking_enabled: true
  - hide_details_until_paid: true

Flow:
1. Doctor orders CBC for insured patient
2. System creates charge:
   - Total: GHS 30.00
   - Insurance covers: GHS 24.00 (80%)
   - Patient copay: GHS 6.00 (20%)
   - Status: pending
   - Linked to claim 71834

3. Lab technician sees:
   ┌──────────────────────────────────────────┐
   │ ⚠ Payment Required Before Processing    │
   │ Patient: MARFO EUGENE                    │
   │ Order exists - payment pending           │
   │ Amount due: GHS 6.00 (copay)            │
   │ [Test details hidden until paid]         │
   └──────────────────────────────────────────┘

4. Patient goes to BILLING FIRST
5. Cashier collects GHS 6.00 copay
6. Marks charge as "paid" (patient portion)
7. Lab technician can now see:
   ┌──────────────────────────────────────────┐
   │ ✓ Payment Received - Proceed            │
   │ Complete Blood Count (CBC)               │
   │ Sample: Whole Blood                      │
   │ Parameters: WBC, RBC, HGB, PLT...       │
   │ [Process Test] button enabled            │
   └──────────────────────────────────────────┘

8. Lab processes test
9. After discharge: Submit GHS 24.00 to insurance
```

**Example B: Pharmacy - Flexible Enforcement (Payment AFTER Service)**
```
Drug: Paracetamol 500mg x 10
Hospital Price: GHS 10.00
Insurance Coverage: 100% (VET Gold)
Service Charge Rule:
  - payment_required: 'optional'
  - payment_timing: 'after_service'
  - service_blocking_enabled: false

Flow:
1. Doctor prescribes Paracetamol
2. System creates charge:
   - Total: GHS 10.00
   - Insurance covers: GHS 10.00 (100%)
   - Patient copay: GHS 0.00
   - Status: pending

3. Pharmacist sees:
   ┌──────────────────────────────────────────┐
   │ ✓ Covered 100% by VET Gold              │
   │ Paracetamol 500mg x 10 tablets           │
   │ Insurance Pays: GHS 10.00                │
   │ Patient Owes: GHS 0.00                   │
   │ Note: Fully covered                      │
   │ [Dispense] button enabled                │
   └──────────────────────────────────────────┘

4. Pharmacist dispenses immediately (no payment required per rule)
5. Patient goes to billing before discharge
6. Cashier sees copay is GHS 0.00 → no collection needed
7. After discharge: Submit GHS 10.00 to insurance
```

**Example C: Consultation - Strict Enforcement + Copay**
```
Service: Cardiology Consultation
Hospital Price: GHS 50.00
Insurance Coverage: 70% (VET Gold)
Service Charge Rule:
  - payment_required: 'mandatory'
  - payment_timing: 'before_service'
  - service_blocking_enabled: true

Flow:
1. Patient checks in to Cardiology
2. System creates charge:
   - Total: GHS 50.00
   - Insurance covers: GHS 35.00 (70%)
   - Patient copay: GHS 15.00 (30%)
   - Status: pending

3. Doctor sees consultation blocked:
   ┌──────────────────────────────────────────┐
   │ ⚠ Payment Required Before Consultation  │
   │ Patient must pay copay at billing        │
   │ Amount due: GHS 15.00                    │
   │ [Start Consultation] button disabled     │
   └──────────────────────────────────────────┘

4. Patient redirected to BILLING
5. Cashier collects GHS 15.00 copay
6. Marks charge as "paid"
7. Doctor can now start consultation:
   ┌──────────────────────────────────────────┐
   │ ✓ Payment Received                       │
   │ [Start Consultation] button enabled      │
   └──────────────────────────────────────────┘

8. Doctor conducts consultation
9. After discharge: Submit GHS 35.00 to insurance
```

**Example D: Mixed Services - Different Payment Rules**
```
Patient has insurance (VET Gold)
Visit includes:
- Consultation (strict - pay before)
- Lab CBC (strict - pay before)
- Pharmacy drugs (flexible - pay after)

Patient Journey:
1. Check-in → Claim 71834 created
2. Doctor consultation BLOCKED → Go to billing
3. Billing: Pay GHS 15.00 consultation copay
4. Consultation proceeds
5. Doctor orders CBC → Lab BLOCKED → Go to billing
6. Billing: Pay GHS 6.00 lab copay
7. Lab processes test
8. Doctor prescribes drugs → Pharmacy dispenses (no payment required)
9. Patient goes to billing for final checkout
10. Billing shows:
    - Consultation copay: GHS 15.00 ✓ paid
    - Lab copay: GHS 6.00 ✓ paid
    - Pharmacy copay: GHS 0.00 (fully covered)
    - Total paid: GHS 21.00
    - Ready for discharge
11. After discharge: Submit GHS 59.00 to insurance
```

---

### Workflow 4: Complete Patient Journey (Check-in to Discharge)

**Step 1: Check-in (Reception)**
```
Patient: "I have VET Insurance"
Receptionist: Enters CCC "71834" from insurance card
System: Creates insurance claim container
Patient: Proceeds to see doctor
```

**Step 2: Consultation (Doctor)**
```
Doctor: Examines patient → Diagnosis: Malaria
Doctor: Prescribes:
  - Paracetamol 500mg x 10 tablets
  - Orders lab: Complete Blood Count (CBC)

System: Creates charges, links to claim 71834
Patient: Goes to pharmacy
```

**Step 3: Pharmacy (Pharmacist)**
```
Pharmacist sees prescription: Paracetamol

┌────────────────────────────────────────────┐
│ Paracetamol 500mg x 10                     │
│ Hospital Price: GHS 10.00                  │
│                                            │
│ ✓ Covered by VET Gold Plan                │
│ Insurance Pays: GHS 8.00 (80%)            │
│ Patient Owes: GHS 2.00 (20%)              │
│                                            │
│ Note: Patient will pay at billing          │
└────────────────────────────────────────────┘

Pharmacist: Dispenses drug (no payment collected)
Charge created and linked to claim 71834
Patient: Goes to lab
```

**Step 4: Lab (Lab Technician)**
```
Lab tech sees order: CBC

┌────────────────────────────────────────────┐
│ Complete Blood Count (CBC)                 │
│ Hospital Price: GHS 30.00                  │
│                                            │
│ ✓ Covered by VET Gold Plan                │
│ Insurance Pays: GHS 30.00 (100%)          │
│ Patient Owes: GHS 0.00                    │
│                                            │
│ Note: Fully covered, no copay             │
└────────────────────────────────────────────┘

Lab tech: Processes test (no payment collected)
Charge created and linked to claim 71834
Patient: Returns for results, then goes to billing
```

**Step 5: Billing/Cashier (Payment Collection)** ★★★
```
Cashier opens patient bill for claim 71834:

┌─────────────────────────────────────────────────────────┐
│ PATIENT BILL - EUGENE MARFO                            │
│ Claim Check Code: 71834                                │
│ Insurance: VET Insurance - Gold Plan                   │
├─────────────────────────────────────────────────────────┤
│                                                         │
│ Service         │ Total  │ Insurance │ Patient Pays   │
│─────────────────┼────────┼───────────┼────────────────│
│ Consultation    │  50.00 │    35.00  │     15.00      │
│ Paracetamol x10 │  10.00 │     8.00  │      2.00      │
│ CBC Lab Test    │  30.00 │    30.00  │      0.00      │
│─────────────────┼────────┼───────────┼────────────────│
│ TOTALS:         │  90.00 │    73.00  │     17.00      │
│                                                         │
├─────────────────────────────────────────────────────────┤
│                                                         │
│ Total Billed: GHS 90.00                                │
│ Insurance Will Pay: GHS 73.00 (claim after discharge)  │
│                                                         │
│ >>> PATIENT MUST PAY NOW: GHS 17.00 <<<               │
│                                                         │
└─────────────────────────────────────────────────────────┘

Cashier: "Your total is GHS 17.00"
Patient: Pays GHS 17.00
Cashier: Issues receipt, clears patient to leave

Charges updated:
- Status: paid (patient portion)
- Insurance portion: pending claim submission
```

**Step 6: Patient Discharge**
```
Patient leaves hospital with:
✓ Services received (drugs, lab results)
✓ Zero balance (copays paid at billing)
✓ Receipt for GHS 17.00 paid

Hospital has:
✓ Collected GHS 17.00 from patient
✓ Claim 71834 ready for vetting (GHS 73.00 to claim from insurance)
```

**Step 7: Claims Vetting (After Patient Gone)**
```
Officer reviews claim 71834:
- Patient: MARFO EUGENE
- Total billed: GHS 90.00
- Patient paid: GHS 17.00 ✓
- To claim from insurance: GHS 73.00

Officer verifies services match diagnosis
Officer approves claim
Status: draft → vetted
```

**Step 8: Submit to Insurance**
```
Export claim 71834 to VET Insurance
Amount to claim: GHS 73.00
Status: vetted → submitted
Wait for insurance payment
```

**Step 9: Insurance Payment Received**
```
VET Insurance pays hospital: GHS 73.00
Record payment against claim 71834
Status: submitted → paid
Claim closed ✓
```

---

### Workflow 4: Claims Vetting (Officer Review)

**URL:** `/insurance/claims/vetting`

**Page Structure:**

```
┌─────────────────────────────────────────────────────────────────────────┐
│ Insurance Claims Vetting                                                │
├─────────────────────────────────────────────────────────────────────────┤
│ Filters:                                                                │
│ Status: [All ▼] [Draft] [Vetted] [Submitted]                          │
│ Insurance: [All ▼] [VET] [NHIS] [AAR]                                 │
│ Date: [From: 01/09/25] [To: 30/09/25]                                 │
│ Search: [Patient name or CCC...]                                       │
├─────────────────────────────────────────────────────────────────────────┤
│                                                                         │
│ Pending Review (23)                                                    │
│                                                                         │
│ ┌─────┬───────────────┬────────────┬──────────┬─────────┬──────────┐  │
│ │ CCC │ Patient       │ Insurance  │ Visit    │ Amount  │ Actions  │  │
│ ├─────┼───────────────┼────────────┼──────────┼─────────┼──────────┤  │
│ │71834│ MARFO EUGENE  │ VET Gold   │ 05/09/25 │ GHS  60 │ [Review] │  │
│ │71835│ KWAME RICHMOND│ NHIS Basic │ 06/09/25 │ GHS 147 │ [Review] │  │
│ │71836│ ABENA AKOSUA  │ AAR Premium│ 06/09/25 │ GHS 230 │ [Review] │  │
│ │71837│ KOFI MENSAH   │ VET Gold   │ 07/09/25 │ GHS  85 │ [Review] │  │
│ └─────┴───────────────┴────────────┴──────────┴─────────┴──────────┘  │
│                                                                         │
│ [1] [2] [3] ... [5]                                      Showing 1-20  │
└─────────────────────────────────────────────────────────────────────────┘
```

**Click "Review" → Modal Opens:**

```
┌───────────────────────────────────────────────────────────────────────┐
│ × Vet Claim - EUGENE MARFO (71834)                                    │
├───────────────────────────────────────────────────────────────────────┤
│                                                                       │
│ ┌────────────────────────┬────────────────────────┐                  │
│ │ Patient                │ Membership ID          │                  │
│ │ MARFO EUGENE           │ 13879209               │                  │
│ ├────────────────────────┼────────────────────────┤                  │
│ │ DOB: 06/10/2002        │ Gender: Male           │                  │
│ ├────────────────────────┼────────────────────────┤                  │
│ │ Insurance              │ Type of Service        │                  │
│ │ VET Insurance - Gold   │ Inpatient              │                  │
│ ├────────────────────────┼────────────────────────┤                  │
│ │ Visit Date             │ Discharge Date         │                  │
│ │ 05 Sep 2025            │ 07 Sep 2025            │                  │
│ ├────────────────────────┼────────────────────────┤                  │
│ │ Claim Check Code       │ Folder ID              │                  │
│ │ 71834                  │ 685/2024               │                  │
│ ├────────────────────────┼────────────────────────┤                  │
│ │ Diagnosis              │ Attending Doctor       │                  │
│ │ Severe Malaria (B50)   │ Dr. Prosper Kwaku      │                  │
│ └────────────────────────┴────────────────────────┘                  │
│                                                                       │
│ ┌─────────────────────────────────────────────────────────────────┐  │
│ │ [Prescriptions (8)] [Investigations (3)] [Procedures (1)]      │  │
│ ├─────────────────────────────────────────────────────────────────┤  │
│ │                                                                 │  │
│ │ Date    │ Code        │ Description         │Qty│Tariff│Total  │  │
│ ├─────────┼─────────────┼─────────────────────┼───┼──────┼───────┤  │
│ │ 06/09   │ ARTEMLUMTA1 │ Artemether+Lume...  │ 1 │ 2.24 │  2.24 │  │
│ │ 05/09   │ ARTESUIN3   │ Artesunate 120mg    │ 3 │ 6.25 │ 18.75 │  │
│ │ 05/09   │ ARTESUIN3   │ Artesunate 120mg    │ 3 │ 6.25 │ 18.75 │  │
│ │ 05/09   │ DESOCHIN2   │ Dextrose IV 5%      │ 3 │ 6.50 │ 19.50 │  │
│ │ 05/09   │ DICLOFIN1   │ Diclofenac 75mg     │ 2 │ 0.56 │  1.12 │  │
│ │                                                                 │  │
│ │ [✓ Approve All] [✗ Reject Selected]                           │  │
│ └─────────────────────────────────────────────────────────────────┘  │
│                                                                       │
├───────────────────────────────────────────────────────────────────────┤
│ Total Claim Amount: GHS 60.36                                         │
│                                                                       │
│                            [Close] [Approve & Submit Claim]           │
└───────────────────────────────────────────────────────────────────────┘
```

**Officer Actions:**
1. Review patient details
2. Verify diagnosis matches services
3. Check quantities are reasonable
4. Switch between tabs (Prescriptions, Labs, Procedures)
5. Approve all items or reject specific ones
6. Click "Approve & Submit Claim" when satisfied
7. Modal closes, claim moves to "Vetted" status

---

### Workflow 4: Claims Submission

**URL:** `/insurance/claims/submission`

```
┌─────────────────────────────────────────────────────────────────────────┐
│ Submit Claims to Insurance                                              │
├─────────────────────────────────────────────────────────────────────────┤
│ Filters: Insurance: [VET Insurance ▼]  Status: [Vetted ▼]             │
├─────────────────────────────────────────────────────────────────────────┤
│                                                                         │
│ Vetted Claims Ready for Submission (12)                                │
│                                                                         │
│ ┌───┬─────┬───────────────┬────────────┬──────────┬─────────┬────────┐ │
│ │ ☐ │ CCC │ Patient       │ Insurance  │ Vetted   │ Amount  │ Vetted │ │
│ │   │     │               │            │ Date     │         │ By     │ │
│ ├───┼─────┼───────────────┼────────────┼──────────┼─────────┼────────┤ │
│ │ ☑ │71834│ MARFO EUGENE  │ VET Gold   │ 10/09/25 │ GHS  60 │ Jane D.│ │
│ │ ☑ │71835│ KWAME RICHMOND│ VET Gold   │ 10/09/25 │ GHS 147 │ Jane D.│ │
│ │ ☐ │71840│ ABENA AKOSUA  │ VET Gold   │ 11/09/25 │ GHS 230 │ John K.│ │
│ └───┴─────┴───────────────┴────────────┴──────────┴─────────┴────────┘ │
│                                                                         │
│ Selected: 2 claims, Total: GHS 207.00                                  │
│                                                                         │
│ [Export to Excel] [Generate PDF] [Submit to Insurance]                 │
└─────────────────────────────────────────────────────────────────────────┘
```

**Submission Actions:**
1. Filter by insurance provider and "vetted" status
2. Select multiple claims via checkboxes
3. Click "Export to Excel" → Downloads submission file
4. Click "Submit to Insurance" → Marks claims as "submitted"
5. Record submission date
6. Track until payment received

---

## Implementation Phases (10-12 Weeks)

### Phase 1: Database Foundation (Weeks 1-2)
**Tasks:**
- [x] Create migration for `insurance_providers` table
- [x] Create migration for `insurance_plans` table
- [x] Create migration for `patient_insurance` table
- [x] Create migration for `insurance_coverage_rules` table
- [x] Create migration for `insurance_tariffs` table
- [x] Create migration for `insurance_claims` table
- [x] Create migration for `insurance_claim_items` table
- [x] Modify `patient_checkins` table (add `claim_check_code`)
- [x] Modify `charges` table (add insurance columns)
- [x] Create all models with relationships
- [x] Create factories for testing
- [x] Create seeders with sample data

**Deliverables:**
- ✅ 7 new database tables
- ✅ 2 modified tables (patient_checkins, charges)
- ✅ All models with proper relationships (InsuranceProvider, InsurancePlan, PatientInsurance, InsuranceCoverageRule, InsuranceTariff, InsuranceClaim, InsuranceClaimItem)
- ✅ Seeded test data (3 providers: VET, NHIS, AAR | 3 plans | 18 coverage rules | 5 sample patient insurance records)

**Status: ✅ COMPLETED** - All migrations run successfully, data seeded, code formatted with Pint

---

### Phase 2: Patient Insurance Enrollment (Week 3)
**Tasks:**
- [ ] Create patient insurance registration form
- [ ] Add insurance section to patient profile
- [ ] Validate insurance coverage dates
- [ ] Display active/expired insurance status
- [ ] Support multiple insurance policies per patient (primary, secondary)
- [ ] Upload insurance card images (optional)

**Deliverables:**
- Patient insurance enrollment UI
- Active insurance badge on patient profile
- Insurance validation logic

---

### Phase 3: Check-in Enhancement (Week 4)
**Tasks:**
- [ ] Detect active insurance during check-in
- [ ] Show insurance dialog with manual CCC entry
- [ ] Validate CCC uniqueness
- [ ] Create `insurance_claims` record on check-in
- [ ] Store CCC in `patient_checkins.claim_check_code`
- [ ] Toggle between insured visit vs cash visit
- [ ] Handle check-in without insurance

**Deliverables:**
- Enhanced check-in flow with insurance detection
- Manual CCC entry and validation
- Automatic claim creation

---

### Phase 4: Coverage Rules Configuration (Week 5)
**Tasks:**
- [ ] Admin page to manage insurance providers
- [ ] Admin page to manage insurance plans
- [ ] Admin page to configure coverage rules
- [ ] Map services/drugs to insurance plans
- [ ] Set coverage percentages (0-100%)
- [ ] Define insurance tariffs (if different from standard prices)
- [ ] Set exclusions and special rules
- [ ] Import coverage rules from CSV/Excel (optional)

**Deliverables:**
- Admin UI for insurance configuration
- Coverage rules CRUD
- Tariff management

---

### Phase 5: Automatic Charge Splitting (Weeks 6-7)
**Tasks:**
- [ ] Modify charge creation logic
- [ ] Check for active insurance claim
- [ ] Find applicable coverage rule
- [ ] Calculate insurance vs patient split
- [ ] Create `insurance_claim_item` for each charge
- [ ] Update `insurance_claims` totals
- [ ] Display split amounts at service points
- [ ] Handle services not covered by insurance

**Deliverables:**
- Auto-linking logic for charges to claims
- Coverage calculation engine
- Split billing (insurance + patient copay)

---

### Phase 6: Service Point Integration (Week 8)
**Tasks:**
- [ ] Pharmacy: Display coverage information (display only, no payment)
- [ ] Lab: Show coverage information (display only, no payment)
- [ ] Ward: Display coverage for admissions (display only, no payment)
- [ ] Billing/Cashier: **PAYMENT COLLECTION** - Calculate and collect all copays
- [ ] Add informational coverage badges to service point UIs
- [ ] Build comprehensive billing page with insurance breakdown
- [ ] Block patient discharge until copays paid at billing

**Deliverables:**
- Read-only coverage indicators at pharmacy/lab/ward (green badges showing coverage %)
- Comprehensive billing/cashier UI with insurance breakdown
- Payment collection interface at billing point
- Discharge blocking logic until payment complete

---

### Phase 7: Claims Vetting Page (Weeks 9-10) ★★★
**Tasks:**
- [ ] Build claims list/dashboard page
- [ ] Add filters (status, insurance, date, search)
- [ ] Pagination for claims list
- [ ] Build vetting modal component
- [ ] Patient & visit details section
- [ ] Tabs: Prescriptions, Investigations, Procedures
- [ ] Line items table with approve/reject actions
- [ ] "Approve Claim" button
- [ ] Status workflow: draft → vetted
- [ ] Rejection reason tracking
- [ ] Vetting audit trail

**Deliverables:**
- Claims vetting dashboard
- Modal-based vetting UI (matching screenshot design)
- Approve/reject workflow
- Audit trail for vetting actions

---

### Phase 8: Claims Submission & Tracking (Week 11)
**Tasks:**
- [ ] Claims submission dashboard
- [ ] Batch claim selection
- [ ] Export to Excel/PDF
- [ ] Mark claims as submitted
- [ ] Track submission date
- [ ] Payment recording interface
- [ ] Reconciliation tools
- [ ] Handle partial payments
- [ ] Rejection handling and resubmission

**Deliverables:**
- Claims submission UI
- Export functionality
- Payment tracking
- Reconciliation tools

---

### Phase 9: Reporting & Analytics (Week 12)
**Tasks:**
- [ ] Claims summary dashboard
- [ ] Revenue reports (insurance vs cash)
- [ ] Outstanding claims by insurer
- [ ] Aging report (30/60/90 days)
- [ ] Vetting officer performance metrics
- [ ] Coverage utilization analytics
- [ ] Top services/drugs by insurance
- [ ] Rejection analysis report

**Deliverables:**
- Insurance analytics dashboard
- Financial reports
- Operational metrics

---

## Key Technical Details

### Models & Relationships

```php
// app/Models/InsuranceProvider.php
class InsuranceProvider extends Model
{
    public function plans(): HasMany
    {
        return $this->hasMany(InsurancePlan::class);
    }
}

// app/Models/InsurancePlan.php
class InsurancePlan extends Model
{
    public function provider(): BelongsTo
    {
        return $this->belongsTo(InsuranceProvider::class);
    }

    public function coverageRules(): HasMany
    {
        return $this->hasMany(InsuranceCoverageRule::class);
    }

    public function tariffs(): HasMany
    {
        return $this->hasMany(InsuranceTariff::class);
    }
}

// app/Models/PatientInsurance.php
class PatientInsurance extends Model
{
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function insurancePlan(): BelongsTo
    {
        return $this->belongsTo(InsurancePlan::class);
    }

    public function claims(): HasMany
    {
        return $this->hasMany(InsuranceClaim::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active'
            && $this->coverage_start_date <= now()
            && ($this->coverage_end_date === null || $this->coverage_end_date >= now());
    }
}

// app/Models/InsuranceClaim.php
class InsuranceClaim extends Model
{
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function patientInsurance(): BelongsTo
    {
        return $this->belongsTo(PatientInsurance::class);
    }

    public function patientCheckin(): BelongsTo
    {
        return $this->belongsTo(PatientCheckin::class);
    }

    public function claimItems(): HasMany
    {
        return $this->hasMany(InsuranceClaimItem::class);
    }

    public function scopeDraft($query)
    {
        return $query->where('status', 'draft');
    }

    public function scopeVetted($query)
    {
        return $query->where('status', 'vetted');
    }

    public function approveClaim(User $user): void
    {
        $this->update([
            'status' => 'vetted',
            'vetted_by' => $user->id,
            'vetted_at' => now(),
        ]);
    }
}

// app/Models/InsuranceClaimItem.php
class InsuranceClaimItem extends Model
{
    public function claim(): BelongsTo
    {
        return $this->belongsTo(InsuranceClaim::class);
    }

    public function charge(): BelongsTo
    {
        return $this->belongsTo(Charge::class);
    }
}

// Enhanced Patient Model
class Patient extends Model
{
    public function insurancePolicies(): HasMany
    {
        return $this->hasMany(PatientInsurance::class);
    }

    public function activeInsurance(): HasOne
    {
        return $this->hasOne(PatientInsurance::class)
            ->where('status', 'active')
            ->where('coverage_start_date', '<=', now())
            ->where(function($q) {
                $q->whereNull('coverage_end_date')
                  ->orWhere('coverage_end_date', '>=', now());
            });
    }

    public function hasActiveInsurance(): bool
    {
        return $this->activeInsurance()->exists();
    }
}

// Enhanced PatientCheckin Model
class PatientCheckin extends Model
{
    public function insuranceClaim(): HasOne
    {
        return $this->hasOne(InsuranceClaim::class);
    }

    public function hasInsuranceClaim(): bool
    {
        return $this->claim_check_code !== null;
    }
}
```

---

### Services

```php
// app/Services/InsuranceCoverageService.php
class InsuranceCoverageService
{
    public function findCoverageRule(InsurancePlan $plan, string $itemCode, string $category): ?InsuranceCoverageRule
    {
        return InsuranceCoverageRule::where('insurance_plan_id', $plan->id)
            ->where('coverage_category', $category)
            ->where('item_code', $itemCode)
            ->where('is_active', true)
            ->where(function($q) {
                $q->whereNull('effective_from')
                  ->orWhere('effective_from', '<=', now());
            })
            ->where(function($q) {
                $q->whereNull('effective_to')
                  ->orWhere('effective_to', '>=', now());
            })
            ->first();
    }

    public function calculateCoverage(
        InsurancePlan $plan,
        string $itemCode,
        string $category,
        float $amount,
        int $quantity = 1
    ): array {
        $coverageRule = $this->findCoverageRule($plan, $itemCode, $category);

        if (!$coverageRule || !$coverageRule->is_covered) {
            return [
                'is_covered' => false,
                'insurance_pays' => 0,
                'patient_pays' => $amount * $quantity,
                'coverage_percentage' => 0,
            ];
        }

        // Get insurance tariff or use standard price
        $tariff = $this->getInsuranceTariff($plan, $itemCode, $category) ?? $amount;

        // Calculate based on coverage type
        $insurancePays = match($coverageRule->coverage_type) {
            'percentage' => $tariff * ($coverageRule->coverage_value / 100),
            'fixed' => min($coverageRule->coverage_value, $tariff),
            'full' => $tariff,
            'excluded' => 0,
            default => 0,
        };

        $patientPays = $tariff - $insurancePays;

        return [
            'is_covered' => true,
            'tariff' => $tariff,
            'insurance_pays' => $insurancePays * $quantity,
            'patient_pays' => $patientPays * $quantity,
            'coverage_percentage' => $coverageRule->coverage_value,
            'coverage_rule_id' => $coverageRule->id,
        ];
    }

    public function getInsuranceTariff(InsurancePlan $plan, string $itemCode, string $itemType): ?float
    {
        return InsuranceTariff::where('insurance_plan_id', $plan->id)
            ->where('item_code', $itemCode)
            ->where('item_type', $itemType)
            ->where('effective_from', '<=', now())
            ->where(function($q) {
                $q->whereNull('effective_to')
                  ->orWhere('effective_to', '>=', now());
            })
            ->first()?->insurance_tariff;
    }
}

// app/Services/InsuranceClaimService.php
class InsuranceClaimService
{
    public function __construct(
        private InsuranceCoverageService $coverageService,
        private BillingService $billingService
    ) {}

    public function createClaimForCheckin(
        PatientCheckin $checkin,
        string $claimCheckCode
    ): InsuranceClaim {
        $patient = $checkin->patient;
        $activeInsurance = $patient->activeInsurance;

        if (!$activeInsurance) {
            throw new \Exception('Patient does not have active insurance');
        }

        return InsuranceClaim::create([
            'claim_check_code' => $claimCheckCode,
            'patient_id' => $patient->id,
            'patient_insurance_id' => $activeInsurance->id,
            'patient_checkin_id' => $checkin->id,
            'patient_surname' => $patient->last_name,
            'patient_other_names' => $patient->first_name,
            'patient_dob' => $patient->date_of_birth,
            'patient_gender' => $patient->gender,
            'membership_id' => $activeInsurance->membership_id,
            'date_of_attendance' => $checkin->checked_in_at->toDateString(),
            'type_of_service' => 'outpatient', // or 'inpatient'
            'status' => 'draft',
        ]);
    }

    public function linkChargeToInsurance(Charge $charge): void
    {
        $checkin = $charge->patientCheckin;

        if (!$checkin->hasInsuranceClaim()) {
            return;
        }

        $claim = $checkin->insuranceClaim;
        $insurancePlan = $claim->patientInsurance->insurancePlan;

        // Calculate coverage
        $coverage = $this->coverageService->calculateCoverage(
            $insurancePlan,
            $charge->service_code,
            $charge->service_type,
            $charge->amount,
            1
        );

        if (!$coverage['is_covered']) {
            return; // Not covered, charge remains as normal
        }

        // Update charge with insurance split
        $charge->update([
            'insurance_claim_id' => $claim->id,
            'is_insurance_claim' => true,
            'insurance_tariff_amount' => $coverage['tariff'],
            'insurance_covered_amount' => $coverage['insurance_pays'],
            'patient_copay_amount' => $coverage['patient_pays'],
        ]);

        // Create claim item
        $claimItem = InsuranceClaimItem::create([
            'insurance_claim_id' => $claim->id,
            'charge_id' => $charge->id,
            'item_date' => now()->toDateString(),
            'item_type' => $charge->service_type,
            'code' => $charge->service_code,
            'description' => $charge->description,
            'quantity' => 1,
            'unit_tariff' => $coverage['tariff'],
            'subtotal' => $coverage['tariff'],
            'coverage_percentage' => $coverage['coverage_percentage'],
            'insurance_pays' => $coverage['insurance_pays'],
            'patient_pays' => $coverage['patient_pays'],
            'is_approved' => false,
        ]);

        // Update claim totals
        $claim->increment('total_claim_amount', $coverage['tariff']);
        $claim->increment('insurance_covered_amount', $coverage['insurance_pays']);
        $claim->increment('patient_copay_amount', $coverage['patient_pays']);

        // ★★★ CRITICAL: Check Service Charge Rules for payment enforcement
        $this->enforceServiceChargeRules($charge);
    }

    /**
     * Enforce Service Charge Rules for insurance charges
     * Insurance doesn't bypass payment rules - patient still pays copay according to rules
     */
    private function enforceServiceChargeRules(Charge $charge): void
    {
        $serviceRule = ServiceChargeRule::active()
            ->forService($charge->service_type, $charge->service_code)
            ->first();

        if (!$serviceRule) {
            return; // No specific rule, use default billing flow
        }

        // If service requires payment before delivery and patient has copay
        if ($serviceRule->payment_timing === 'before_service'
            && $charge->patient_copay_amount > 0
            && $serviceRule->service_blocking_enabled) {

            // Mark charge as "blocked" until copay paid
            $charge->update([
                'status' => 'pending_payment',
                'metadata' => array_merge($charge->metadata ?? [], [
                    'blocked_until_paid' => true,
                    'blocking_reason' => 'Service rule requires payment before service',
                    'patient_copay_required' => $charge->patient_copay_amount,
                ]),
            ]);
        }

        // If service hides details until paid
        if ($serviceRule->hide_details_until_paid
            && $charge->patient_copay_amount > 0) {

            $charge->update([
                'metadata' => array_merge($charge->metadata ?? [], [
                    'hide_details' => true,
                    'reveal_after_payment' => true,
                ]),
            ]);
        }
    }
}
```

---

### Payment Enforcement Integration

**How Service Charge Rules Work with Insurance:**

```php
// app/Services/BillingService.php (Enhanced)
class BillingService
{
    public function canProceedWithService(Charge $charge): bool
    {
        // Check service charge rules
        $serviceRule = ServiceChargeRule::active()
            ->forService($charge->service_type, $charge->service_code)
            ->first();

        if (!$serviceRule || !$serviceRule->service_blocking_enabled) {
            return true; // No blocking rule, proceed
        }

        // If patient has insurance, check copay instead of full amount
        if ($charge->is_insurance_claim) {
            // Insurance charge - check if patient copay is paid
            if ($serviceRule->payment_timing === 'before_service') {
                // Patient must pay copay before service
                return $charge->patient_copay_amount == 0 || $charge->status === 'paid';
            }

            // After-service payment - always allow
            return true;
        }

        // Cash patient - use standard payment check
        return $charge->isPaid() || $charge->is_emergency_override;
    }

    public function shouldHideDetails(Charge $charge): bool
    {
        $serviceRule = ServiceChargeRule::active()
            ->forService($charge->service_type, $charge->service_code)
            ->first();

        if (!$serviceRule || !$serviceRule->hide_details_until_paid) {
            return false;
        }

        // If insurance claim, hide details until copay paid
        if ($charge->is_insurance_claim) {
            return $charge->patient_copay_amount > 0 && $charge->status !== 'paid';
        }

        // Cash patient - hide until full payment
        return !$charge->isPaid();
    }
}
```

---

### Controllers

```php
// app/Http/Controllers/Insurance/ClaimVettingController.php
class ClaimVettingController extends Controller
{
    public function index(Request $request)
    {
        $claims = InsuranceClaim::query()
            ->with([
                'patient',
                'patientInsurance.insurancePlan.provider',
            ])
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            ->when($request->provider_id, fn($q) =>
                $q->whereHas('patientInsurance.insurancePlan',
                    fn($q2) => $q2->where('insurance_provider_id', $request->provider_id)
                )
            )
            ->when($request->search, fn($q) =>
                $q->where('claim_check_code', 'like', "%{$request->search}%")
                  ->orWhere('patient_surname', 'like', "%{$request->search}%")
                  ->orWhere('patient_other_names', 'like', "%{$request->search}%")
                  ->orWhere('membership_id', 'like', "%{$request->search}%")
            )
            ->when($request->date_from, fn($q) =>
                $q->where('date_of_attendance', '>=', $request->date_from)
            )
            ->when($request->date_to, fn($q) =>
                $q->where('date_of_attendance', '<=', $request->date_to)
            )
            ->latest('date_of_attendance')
            ->paginate(20);

        return Inertia::render('Insurance/Claims/Vetting', [
            'claims' => $claims,
            'filters' => $request->only(['status', 'provider_id', 'search', 'date_from', 'date_to']),
            'providers' => InsuranceProvider::active()->get(),
        ]);
    }

    public function show(InsuranceClaim $claim)
    {
        $claim->load([
            'patient',
            'patientInsurance.insurancePlan.provider',
            'patientCheckin.department',
            'claimItems' => fn($q) => $q->orderBy('item_date')->orderBy('item_type'),
        ]);

        // Group items by type for tabs
        $claim->prescription_items = $claim->claimItems->where('item_type', 'drug')->values();
        $claim->investigation_items = $claim->claimItems->where('item_type', 'lab')->values();
        $claim->procedure_items = $claim->claimItems->whereIn('item_type', ['procedure', 'consultation', 'ward'])->values();

        return response()->json($claim);
    }

    public function approve(InsuranceClaim $claim)
    {
        $claim->update([
            'status' => 'vetted',
            'vetted_by' => auth()->id(),
            'vetted_at' => now(),
        ]);

        // Auto-approve all items
        $claim->claimItems()->update(['is_approved' => true]);

        return back()->with('success', 'Claim approved successfully');
    }

    public function approveItem(InsuranceClaimItem $item)
    {
        $item->update(['is_approved' => true]);

        return response()->json(['success' => true]);
    }

    public function rejectItem(Request $request, InsuranceClaimItem $item)
    {
        $validated = $request->validate([
            'rejection_reason' => 'required|string|max:500',
        ]);

        $item->update([
            'is_approved' => false,
            'rejection_reason' => $validated['rejection_reason'],
        ]);

        return response()->json(['success' => true]);
    }
}
```

---

## UI Components (React)

### Service Point Coverage Display (Read-Only)

**At Pharmacy - Display Only Component:**
```tsx
// resources/js/components/Pharmacy/InsuranceCoverageDisplay.tsx
export function InsuranceCoverageDisplay({ drug, insuranceCoverage }) {
  if (!insuranceCoverage) return null;

  return (
    <div className="bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-lg p-4 mb-4">
      <div className="flex items-center gap-2 mb-3">
        <CheckCircle className="h-5 w-5 text-green-600" />
        <span className="font-semibold text-green-800 dark:text-green-300">
          Covered by {insuranceCoverage.plan_name}
        </span>
      </div>

      <div className="grid grid-cols-2 gap-3 text-sm">
        <div>
          <span className="text-gray-600 dark:text-gray-400">Coverage:</span>
          <span className="ml-2 font-medium">{insuranceCoverage.coverage_percentage}%</span>
        </div>
        <div>
          <span className="text-gray-600 dark:text-gray-400">Insurance Pays:</span>
          <span className="ml-2 font-medium text-green-700">GHS {insuranceCoverage.insurance_pays}</span>
        </div>
        <div>
          <span className="text-gray-600 dark:text-gray-400">Patient Owes:</span>
          <span className="ml-2 font-medium text-orange-700">GHS {insuranceCoverage.patient_copay}</span>
        </div>
        <div className="col-span-2 text-xs text-gray-500 italic border-t pt-2 mt-1">
          Note: Patient will pay copay at billing/cashier before discharge
        </div>
      </div>
    </div>
  );
}
```

**At Lab - Display Only Component:**
```tsx
// resources/js/components/Lab/InsuranceCoverageDisplay.tsx
export function LabInsuranceCoverageDisplay({ test, insuranceCoverage }) {
  if (!insuranceCoverage) {
    return <Badge variant="secondary">Cash Payment</Badge>;
  }

  const isFullyCovered = insuranceCoverage.patient_copay === 0;

  return (
    <div className={`rounded-md p-3 ${isFullyCovered ? 'bg-blue-50' : 'bg-yellow-50'}`}>
      <div className="flex items-center gap-2 mb-2">
        {isFullyCovered ? (
          <>
            <CheckCircle className="h-4 w-4 text-blue-600" />
            <span className="text-sm font-medium text-blue-800">Fully Covered</span>
          </>
        ) : (
          <>
            <AlertCircle className="h-4 w-4 text-yellow-600" />
            <span className="text-sm font-medium text-yellow-800">Partial Coverage</span>
          </>
        )}
      </div>
      <div className="text-xs text-gray-600">
        Insurance: GHS {insuranceCoverage.insurance_pays} |
        Patient: GHS {insuranceCoverage.patient_copay}
        {!isFullyCovered && ' (pay at billing)'}
      </div>
    </div>
  );
}
```

---

### Billing/Cashier Payment Collection UI ★★★

**Comprehensive Billing Page with Insurance Breakdown:**
```tsx
// resources/js/pages/Billing/PatientBill.tsx
export default function PatientBillPage({ patient, checkin, charges, insuranceClaim }) {
  const [selectedPaymentMethod, setSelectedPaymentMethod] = useState('cash');
  const { post } = useForm();

  // Calculate totals
  const totalBilled = charges.reduce((sum, c) => sum + parseFloat(c.amount), 0);
  const insuranceTotal = insuranceClaim
    ? charges.reduce((sum, c) => sum + parseFloat(c.insurance_covered_amount || 0), 0)
    : 0;
  const patientOwes = insuranceClaim
    ? charges.reduce((sum, c) => sum + parseFloat(c.patient_copay_amount || 0), 0)
    : totalBilled;

  const handleCollectPayment = () => {
    post(route('billing.collect-payment', checkin.id), {
      amount: patientOwes,
      payment_method: selectedPaymentMethod,
    });
  };

  return (
    <div className="p-6 max-w-4xl mx-auto">
      {/* Patient & Insurance Info */}
      <Card className="mb-6">
        <CardHeader>
          <CardTitle>Patient Bill - {patient.full_name}</CardTitle>
          {insuranceClaim && (
            <div className="flex items-center gap-2 text-sm text-muted-foreground">
              <Shield className="h-4 w-4" />
              <span>
                {insuranceClaim.patient_insurance.insurance_plan.provider.name} -
                {insuranceClaim.patient_insurance.insurance_plan.plan_name}
              </span>
              <Badge variant="outline">CCC: {insuranceClaim.claim_check_code}</Badge>
            </div>
          )}
        </CardHeader>

        <CardContent>
          {/* Charges Table */}
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>Service/Item</TableHead>
                <TableHead className="text-right">Total</TableHead>
                {insuranceClaim && (
                  <>
                    <TableHead className="text-right">Insurance Pays</TableHead>
                    <TableHead className="text-right">Patient Pays</TableHead>
                  </>
                )}
              </TableRow>
            </TableHeader>
            <TableBody>
              {charges.map(charge => (
                <TableRow key={charge.id}>
                  <TableCell>
                    <div className="font-medium">{charge.description}</div>
                    <div className="text-sm text-muted-foreground">
                      {charge.service_code}
                    </div>
                  </TableCell>
                  <TableCell className="text-right">
                    GHS {parseFloat(charge.amount).toFixed(2)}
                  </TableCell>
                  {insuranceClaim && (
                    <>
                      <TableCell className="text-right text-green-600">
                        GHS {parseFloat(charge.insurance_covered_amount || 0).toFixed(2)}
                      </TableCell>
                      <TableCell className="text-right text-orange-600 font-medium">
                        GHS {parseFloat(charge.patient_copay_amount || 0).toFixed(2)}
                      </TableCell>
                    </>
                  )}
                </TableRow>
              ))}
            </TableBody>
            <TableFooter>
              <TableRow className="font-bold">
                <TableCell>TOTALS</TableCell>
                <TableCell className="text-right">GHS {totalBilled.toFixed(2)}</TableCell>
                {insuranceClaim && (
                  <>
                    <TableCell className="text-right text-green-600">
                      GHS {insuranceTotal.toFixed(2)}
                    </TableCell>
                    <TableCell className="text-right text-orange-600">
                      GHS {patientOwes.toFixed(2)}
                    </TableCell>
                  </>
                )}
              </TableRow>
            </TableFooter>
          </Table>

          {/* Payment Collection Section */}
          <div className="mt-6 p-6 bg-blue-50 dark:bg-blue-900/20 rounded-lg border-2 border-blue-200 dark:border-blue-800">
            <div className="flex items-center justify-between mb-4">
              <div>
                <h3 className="text-lg font-semibold">Payment Due Now</h3>
                {insuranceClaim && (
                  <p className="text-sm text-muted-foreground">
                    Insurance will be billed GHS {insuranceTotal.toFixed(2)} after discharge
                  </p>
                )}
              </div>
              <div className="text-3xl font-bold text-blue-600">
                GHS {patientOwes.toFixed(2)}
              </div>
            </div>

            <div className="grid grid-cols-2 gap-4 mb-4">
              <div>
                <Label>Payment Method</Label>
                <Select value={selectedPaymentMethod} onValueChange={setSelectedPaymentMethod}>
                  <SelectTrigger>
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="cash">Cash</SelectItem>
                    <SelectItem value="card">Card</SelectItem>
                    <SelectItem value="mobile_money">Mobile Money</SelectItem>
                  </SelectContent>
                </Select>
              </div>
              <div>
                <Label>Amount Received</Label>
                <Input
                  type="number"
                  value={patientOwes.toFixed(2)}
                  readOnly
                  className="font-bold text-lg"
                />
              </div>
            </div>

            <Button
              size="lg"
              className="w-full"
              onClick={handleCollectPayment}
            >
              Collect Payment GHS {patientOwes.toFixed(2)}
            </Button>
          </div>
        </CardContent>
      </Card>
    </div>
  );
}
```

---

### Claims Vetting Page

```tsx
// resources/js/pages/Insurance/Claims/Vetting.tsx
import { useState } from 'react';
import { usePage } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select } from '@/components/ui/select';
import ClaimVettingModal from '@/components/Insurance/ClaimVettingModal';

export default function ClaimsVetting() {
  const { claims, filters, providers } = usePage().props;
  const [selectedClaim, setSelectedClaim] = useState(null);
  const [isModalOpen, setIsModalOpen] = useState(false);

  const openVettingModal = (claim) => {
    // Fetch full claim details
    axios.get(route('insurance.claims.show', claim.id))
      .then(response => {
        setSelectedClaim(response.data);
        setIsModalOpen(true);
      });
  };

  return (
    <div className="p-6">
      <div className="flex justify-between items-center mb-6">
        <h1 className="text-2xl font-bold">Insurance Claims Vetting</h1>
      </div>

      {/* Filters */}
      <div className="grid grid-cols-4 gap-4 mb-6">
        <Select
          label="Status"
          value={filters.status}
          onChange={(value) => router.get(route('insurance.claims.vetting'), { ...filters, status: value })}
        >
          <option value="">All</option>
          <option value="draft">Draft</option>
          <option value="pending_vetting">Pending Vetting</option>
          <option value="vetted">Vetted</option>
          <option value="submitted">Submitted</option>
        </Select>

        <Select
          label="Insurance Provider"
          value={filters.provider_id}
          onChange={(value) => router.get(route('insurance.claims.vetting'), { ...filters, provider_id: value })}
        >
          <option value="">All Providers</option>
          {providers.map(provider => (
            <option key={provider.id} value={provider.id}>{provider.name}</option>
          ))}
        </Select>

        <Input
          type="date"
          label="From Date"
          value={filters.date_from}
          onChange={(e) => router.get(route('insurance.claims.vetting'), { ...filters, date_from: e.target.value })}
        />

        <Input
          type="text"
          label="Search"
          placeholder="Patient name, CCC, Member ID..."
          value={filters.search}
          onChange={(e) => router.get(route('insurance.claims.vetting'), { ...filters, search: e.target.value })}
        />
      </div>

      {/* Claims Table */}
      <div className="bg-white dark:bg-gray-800 rounded-lg shadow">
        <table className="w-full">
          <thead className="border-b dark:border-gray-700">
            <tr>
              <th className="px-4 py-3 text-left">CCC</th>
              <th className="px-4 py-3 text-left">Patient</th>
              <th className="px-4 py-3 text-left">Insurance</th>
              <th className="px-4 py-3 text-left">Visit Date</th>
              <th className="px-4 py-3 text-right">Amount</th>
              <th className="px-4 py-3 text-center">Actions</th>
            </tr>
          </thead>
          <tbody>
            {claims.data.map(claim => (
              <tr key={claim.id} className="border-b dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700">
                <td className="px-4 py-3">{claim.claim_check_code}</td>
                <td className="px-4 py-3">
                  {claim.patient_surname} {claim.patient_other_names}
                </td>
                <td className="px-4 py-3">
                  {claim.patient_insurance.insurance_plan.provider.name}
                  <div className="text-sm text-gray-500">
                    {claim.patient_insurance.insurance_plan.plan_name}
                  </div>
                </td>
                <td className="px-4 py-3">{claim.date_of_attendance}</td>
                <td className="px-4 py-3 text-right">GHS {claim.total_claim_amount}</td>
                <td className="px-4 py-3 text-center">
                  <Button
                    size="sm"
                    onClick={() => openVettingModal(claim)}
                  >
                    Review
                  </Button>
                </td>
              </tr>
            ))}
          </tbody>
        </table>

        {/* Pagination */}
        <div className="px-4 py-3 border-t dark:border-gray-700">
          {/* Pagination component */}
        </div>
      </div>

      {/* Vetting Modal */}
      <ClaimVettingModal
        claim={selectedClaim}
        isOpen={isModalOpen}
        onClose={() => setIsModalOpen(false)}
      />
    </div>
  );
}
```

### Claim Vetting Modal

```tsx
// resources/js/components/Insurance/ClaimVettingModal.tsx
import { useState } from 'react';
import { useForm } from '@inertiajs/react';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter } from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Badge } from '@/components/ui/badge';

export default function ClaimVettingModal({ claim, isOpen, onClose }) {
  const [activeTab, setActiveTab] = useState('prescriptions');
  const { post } = useForm();

  if (!claim) return null;

  const approveItem = (itemId) => {
    post(route('insurance.claims.items.approve', itemId), {
      preserveScroll: true,
      onSuccess: () => {
        // Refresh claim data
      }
    });
  };

  const rejectItem = (itemId) => {
    const reason = prompt('Enter rejection reason:');
    if (!reason) return;

    post(route('insurance.claims.items.reject', itemId), {
      data: { rejection_reason: reason },
      preserveScroll: true,
    });
  };

  const approveClaim = () => {
    post(route('insurance.claims.approve', claim.id), {
      onSuccess: () => {
        onClose();
      }
    });
  };

  return (
    <Dialog open={isOpen} onOpenChange={onClose}>
      <DialogContent className="max-w-6xl max-h-[90vh] overflow-y-auto">
        <DialogHeader>
          <DialogTitle>
            Vet Claim - {claim.patient_surname} {claim.patient_other_names} ({claim.claim_check_code})
          </DialogTitle>
        </DialogHeader>

        {/* Patient & Visit Details */}
        <div className="grid grid-cols-2 gap-4 mb-6">
          <div>
            <label className="text-sm font-medium text-gray-500">Patient</label>
            <div>{claim.patient_surname} {claim.patient_other_names}</div>
          </div>
          <div>
            <label className="text-sm font-medium text-gray-500">Membership ID</label>
            <div>{claim.membership_id}</div>
          </div>
          <div>
            <label className="text-sm font-medium text-gray-500">DOB</label>
            <div>{claim.patient_dob}</div>
          </div>
          <div>
            <label className="text-sm font-medium text-gray-500">Gender</label>
            <div className="capitalize">{claim.patient_gender}</div>
          </div>
          <div>
            <label className="text-sm font-medium text-gray-500">Insurance</label>
            <div>
              {claim.patient_insurance.insurance_plan.provider.name} - {claim.patient_insurance.insurance_plan.plan_name}
            </div>
          </div>
          <div>
            <label className="text-sm font-medium text-gray-500">Type of Service</label>
            <div className="capitalize">{claim.type_of_service}</div>
          </div>
          <div>
            <label className="text-sm font-medium text-gray-500">Visit Date</label>
            <div>{claim.date_of_attendance}</div>
          </div>
          <div>
            <label className="text-sm font-medium text-gray-500">Discharge Date</label>
            <div>{claim.date_of_discharge || 'N/A'}</div>
          </div>
          <div>
            <label className="text-sm font-medium text-gray-500">Claim Check Code</label>
            <div className="font-mono">{claim.claim_check_code}</div>
          </div>
          <div>
            <label className="text-sm font-medium text-gray-500">Diagnosis</label>
            <div>{claim.primary_diagnosis_description} ({claim.primary_diagnosis_code})</div>
          </div>
        </div>

        {/* Tabs */}
        <Tabs value={activeTab} onValueChange={setActiveTab}>
          <TabsList>
            <TabsTrigger value="prescriptions">
              Prescriptions ({claim.prescription_items?.length || 0})
            </TabsTrigger>
            <TabsTrigger value="investigations">
              Investigations ({claim.investigation_items?.length || 0})
            </TabsTrigger>
            <TabsTrigger value="procedures">
              Procedures ({claim.procedure_items?.length || 0})
            </TabsTrigger>
          </TabsList>

          <TabsContent value="prescriptions" className="mt-4">
            <ClaimItemsTable
              items={claim.prescription_items}
              onApprove={approveItem}
              onReject={rejectItem}
            />
          </TabsContent>

          <TabsContent value="investigations" className="mt-4">
            <ClaimItemsTable
              items={claim.investigation_items}
              onApprove={approveItem}
              onReject={rejectItem}
            />
          </TabsContent>

          <TabsContent value="procedures" className="mt-4">
            <ClaimItemsTable
              items={claim.procedure_items}
              onApprove={approveItem}
              onReject={rejectItem}
            />
          </TabsContent>
        </Tabs>

        <DialogFooter className="mt-6 pt-4 border-t">
          <div className="flex w-full justify-between items-center">
            <div className="text-lg font-semibold">
              Total Claim Amount: GHS {claim.total_claim_amount}
            </div>
            <div className="flex gap-2">
              <Button variant="outline" onClick={onClose}>
                Close
              </Button>
              <Button variant="default" onClick={approveClaim}>
                Approve & Submit Claim
              </Button>
            </div>
          </div>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}

function ClaimItemsTable({ items, onApprove, onReject }) {
  if (!items || items.length === 0) {
    return <div className="text-center py-8 text-gray-500">No items</div>;
  }

  return (
    <table className="w-full">
      <thead className="border-b">
        <tr>
          <th className="px-3 py-2 text-left">Date</th>
          <th className="px-3 py-2 text-left">Code</th>
          <th className="px-3 py-2 text-left">Description</th>
          <th className="px-3 py-2 text-center">Qty</th>
          <th className="px-3 py-2 text-right">Tariff</th>
          <th className="px-3 py-2 text-right">Subtotal</th>
          <th className="px-3 py-2 text-center">Status</th>
          <th className="px-3 py-2 text-center">Actions</th>
        </tr>
      </thead>
      <tbody>
        {items.map(item => (
          <tr key={item.id} className="border-b hover:bg-gray-50">
            <td className="px-3 py-2">{item.item_date}</td>
            <td className="px-3 py-2 font-mono text-sm">{item.code}</td>
            <td className="px-3 py-2">{item.description}</td>
            <td className="px-3 py-2 text-center">{item.quantity}</td>
            <td className="px-3 py-2 text-right">{item.unit_tariff}</td>
            <td className="px-3 py-2 text-right font-medium">{item.subtotal}</td>
            <td className="px-3 py-2 text-center">
              {item.is_approved ? (
                <Badge variant="success">Approved</Badge>
              ) : (
                <Badge variant="warning">Pending</Badge>
              )}
            </td>
            <td className="px-3 py-2 text-center">
              <div className="flex gap-1 justify-center">
                <Button
                  size="sm"
                  variant="ghost"
                  className="text-green-600 hover:bg-green-50"
                  onClick={() => onApprove(item.id)}
                >
                  ✓
                </Button>
                <Button
                  size="sm"
                  variant="ghost"
                  className="text-red-600 hover:bg-red-50"
                  onClick={() => onReject(item.id)}
                >
                  ✗
                </Button>
              </div>
            </td>
          </tr>
        ))}
      </tbody>
    </table>
  );
}
```

---

## Testing Strategy

### Unit Tests
- [ ] InsuranceCoverageService coverage calculation
- [ ] InsuranceClaimService claim creation
- [ ] Coverage rule matching logic
- [ ] Tariff lookup logic

### Feature Tests
- [ ] Patient insurance enrollment
- [ ] Check-in with manual CCC entry
- [ ] Charge creation with insurance split
- [ ] Claim item auto-creation
- [ ] Claim vetting workflow
- [ ] Claim approval
- [ ] Claim submission

### Example Test
```php
// tests/Feature/Insurance/ClaimCreationTest.php
it('creates insurance claim on check-in with valid CCC', function () {
    $patient = Patient::factory()
        ->has(PatientInsurance::factory()->state(['status' => 'active']))
        ->create();

    $response = $this->post(route('checkins.store'), [
        'patient_id' => $patient->id,
        'department_id' => 1,
        'claim_check_code' => '71834',
    ]);

    $response->assertSuccessful();

    expect(PatientCheckin::first()->claim_check_code)->toBe('71834');
    expect(InsuranceClaim::first()->claim_check_code)->toBe('71834');
});

it('rejects duplicate claim check code', function () {
    PatientCheckin::factory()->create(['claim_check_code' => '71834']);

    $response = $this->post(route('checkins.store'), [
        'patient_id' => 1,
        'department_id' => 1,
        'claim_check_code' => '71834',
    ]);

    $response->assertInvalid(['claim_check_code']);
});

it('calculates insurance coverage correctly', function () {
    $plan = InsurancePlan::factory()->create();
    InsuranceCoverageRule::factory()->create([
        'insurance_plan_id' => $plan->id,
        'item_code' => 'ARTEMLUMTA1',
        'coverage_type' => 'percentage',
        'coverage_value' => 100,
    ]);

    $service = app(InsuranceCoverageService::class);
    $coverage = $service->calculateCoverage($plan, 'ARTEMLUMTA1', 'drug', 2.24, 1);

    expect($coverage['is_covered'])->toBeTrue();
    expect($coverage['insurance_pays'])->toBe(2.24);
    expect($coverage['patient_pays'])->toBe(0.0);
});
```

---

## Migration Examples

```php
// database/migrations/xxxx_create_insurance_providers_table.php
public function up(): void
{
    Schema::create('insurance_providers', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->string('code', 50)->unique();
        $table->string('contact_person')->nullable();
        $table->string('phone')->nullable();
        $table->string('email')->nullable();
        $table->text('address')->nullable();
        $table->enum('claim_submission_method', ['online', 'manual', 'api'])->default('manual');
        $table->integer('payment_terms_days')->default(30);
        $table->boolean('is_active')->default(true);
        $table->text('notes')->nullable();
        $table->timestamps();
    });
}

// database/migrations/xxxx_create_insurance_claims_table.php
public function up(): void
{
    Schema::create('insurance_claims', function (Blueprint $table) {
        $table->id();
        $table->string('claim_check_code', 50)->unique();
        $table->string('folder_id', 50)->nullable();
        $table->foreignId('patient_id')->constrained();
        $table->foreignId('patient_insurance_id')->constrained();
        $table->foreignId('patient_checkin_id')->nullable()->constrained();

        // Denormalized patient details
        $table->string('patient_surname');
        $table->string('patient_other_names');
        $table->date('patient_dob');
        $table->enum('patient_gender', ['male', 'female']);
        $table->string('membership_id');

        // Visit details
        $table->date('date_of_attendance');
        $table->date('date_of_discharge')->nullable();
        $table->enum('type_of_service', ['inpatient', 'outpatient']);
        $table->enum('type_of_attendance', ['emergency', 'acute', 'routine'])->default('routine');
        $table->string('specialty_attended')->nullable();
        $table->string('attending_prescriber')->nullable();

        // Diagnosis
        $table->string('primary_diagnosis_code', 20)->nullable();
        $table->string('primary_diagnosis_description')->nullable();

        // Financial
        $table->decimal('total_claim_amount', 12, 2)->default(0);
        $table->decimal('approved_amount', 12, 2)->default(0);
        $table->decimal('insurance_covered_amount', 12, 2)->default(0);

        // Status
        $table->enum('status', ['draft', 'pending_vetting', 'vetted', 'submitted', 'approved', 'rejected', 'paid'])->default('draft');
        $table->foreignId('vetted_by')->nullable()->constrained('users');
        $table->timestamp('vetted_at')->nullable();

        $table->timestamps();

        $table->index('claim_check_code');
        $table->index('status');
        $table->index('date_of_attendance');
    });
}
```

---

## Routes

```php
// routes/insurance.php
use App\Http\Controllers\Insurance\ClaimVettingController;
use App\Http\Controllers\Insurance\ClaimSubmissionController;

Route::middleware(['auth'])->prefix('insurance')->name('insurance.')->group(function () {
    // Claims vetting
    Route::get('/claims/vetting', [ClaimVettingController::class, 'index'])->name('claims.vetting');
    Route::get('/claims/{claim}', [ClaimVettingController::class, 'show'])->name('claims.show');
    Route::post('/claims/{claim}/approve', [ClaimVettingController::class, 'approve'])->name('claims.approve');
    Route::post('/claims/items/{item}/approve', [ClaimVettingController::class, 'approveItem'])->name('claims.items.approve');
    Route::post('/claims/items/{item}/reject', [ClaimVettingController::class, 'rejectItem'])->name('claims.items.reject');

    // Claims submission
    Route::get('/claims/submission', [ClaimSubmissionController::class, 'index'])->name('claims.submission');
    Route::post('/claims/submit', [ClaimSubmissionController::class, 'submit'])->name('claims.submit');
});
```

---

## Summary

### What Gets Built:
1. **7 new database tables** for insurance management
2. **Modified check-in flow** with manual CCC entry
3. **Automatic charge splitting** between insurance and patient
4. **Claims vetting UI** with modal-based review
5. **Claims submission** with batch processing
6. **Coverage rules engine** for determining what's covered
7. **Reports and analytics** for insurance tracking

### Key Features:
- ✅ Manual CCC entry at check-in (validated for uniqueness)
- ✅ Auto-link all visit services to insurance claim
- ✅ Calculate insurance vs patient copay automatically
- ✅ Modal-based claims vetting (not separate pages)
- ✅ List-first approach (see all insured patients)
- ✅ Track claim lifecycle: draft → vetted → submitted → paid
- ✅ Support multiple insurance providers and plans
- ✅ Flexible coverage rules (percentage, fixed, full, excluded)
- ✅ Insurance-specific tariffs (can differ from standard prices)

### Timeline:
**10-12 weeks** from start to full deployment

---

## Next Steps

1. Review and approve this implementation plan
2. Set up development environment
3. Begin Phase 1: Database migrations and models
4. Create sample test data
5. Implement check-in enhancement with CCC entry
6. Build coverage calculation logic
7. Develop claims vetting UI
8. Test end-to-end workflow
9. Deploy to production

---

**Document Version:** 1.0
**Last Updated:** 2025-10-27
**Author:** HMS Development Team
