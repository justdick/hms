# Hospital Management System - Integrated Billing Specification

## Overview

This document outlines the comprehensive billing system for the Hospital Management System (HMS) that integrates automatic charge capture at every service point with configurable payment enforcement mechanisms.

## Core Philosophy: "Service-First Billing"

Every service interaction automatically generates billing entries with real-time payment enforcement, ensuring financial controls without compromising medical care quality.

---

## 1. Automatic Billing Integration Points

### 1.1 Patient Check-in Billing

**Trigger:** Patient checks in to specific department/clinic

**Examples:**
- Check-in to "Cardiology Clinic"
- Check-in to "General Medicine"
- Check-in to "Pediatrics Department"
- Check-in to "Emergency Department"
- Check-in to "Orthopedics Clinic"

**Auto-charges Created:**
- Department Consultation Fee (based on department)
- Specialist Fee (if specialist department)
- Emergency Fee (if emergency department)
- Equipment Usage Fee (if specialized equipment department)
- Administrative Fee (if applicable)

**Billing Status:** PENDING (charges created but not immediately enforced)

### 1.2 Consultation Billing - Department-Based

**Trigger:** When doctor attempts to start consultation

**Payment Enforcement (Configurable per Department):**

#### Strict Enforcement
- Doctor CANNOT start consultation until fees paid
- Consultation button disabled/blocked
- Payment required before proceeding
- Emergency override available

#### Flexible Enforcement
- Doctor can start consultation
- Payment reminder shown
- Billing continues in background
- Payment can be processed later

#### No Enforcement
- Doctor can always start consultation
- No payment blocking
- Billing for record keeping only
- Payment processed separately

**Department-Specific Configuration:**
```yaml
cardiology:
  consultation_fee: $75
  payment_required_before_consultation: true
  emergency_override: true
  equipment_fee: $25

general_medicine:
  consultation_fee: $30
  payment_required_before_consultation: false
  emergency_override: true
  equipment_fee: $0

emergency:
  consultation_fee: $50
  payment_required_before_consultation: false
  emergency_override: always
  trauma_surcharge: $30
```

### 1.3 Laboratory Billing - Two-Phase System

#### Phase 1: Doctor Orders Tests (No Payment Required)
- ✅ Doctor can order any tests needed
- ✅ Tests appear in lab queue
- ✅ Billing entries created but not enforced
- ✅ Lab can see orders exist

#### Phase 2: Lab Processing (Payment Enforced - Configurable)

**Lab Staff Interface - Before Payment (if enforcement enabled):**
```
Patient List View:
- "John Doe - Lab Order #123 - Payment Required: $75"
- "Jane Smith - Lab Order #124 - Payment Required: $45"

Cannot See:
- Specific test names (CBC, Blood Sugar, etc.)
- Test parameters
- Sample requirements
- Expected results

Cannot Do:
- Start sample processing
- Enter test results
- Generate reports
- Print labels
```

**Lab Staff Interface - After Payment:**
```
Patient List View:
- "John Doe - Complete Blood Count, Lipid Panel"
- "Jane Smith - Blood Sugar, HbA1c"

Can See:
- Full test details
- Test parameters and ranges
- Sample requirements
- Processing instructions

Can Do:
- Process samples
- Enter results
- Generate reports
- Print test labels
```

### 1.4 Pharmacy Billing

**Trigger:** Medications prescribed/dispensed

**Auto-charges:**
- Medication Costs (by quantity)
- Dispensing Fees
- Special Handling Charges

**Enforcement:**
- Cannot dispense if payment required and unpaid
- Can have prescription holds for payment
- Emergency medications can override

### 1.5 Ward Billing - Two-Phase System

#### Phase 1: Doctor Orders Admission (No Payment Required)
- ✅ Doctor can admit patient to ward
- ✅ Admission order created
- ✅ Ward staff can see admission order
- ✅ Billing entries created but not enforced
- ✅ Patient shows as "Admitted - Awaiting Bed Assignment"

#### Phase 2: Nurse Assigns Bed (Payment Enforced - Configurable)
- ❌ Nurse CANNOT assign bed without payment (if required)
- ❌ Patient CANNOT occupy bed without payment clearance
- ✅ Emergency cases can override payment requirement
- ✅ Payment plans can satisfy bed assignment requirement
- ✅ Advance payments can pre-authorize bed assignment

---

## 2. Dynamic Ward Billing Configuration

### 2.1 Flexible Charge Types

```yaml
Ward Billing Types:
  one_time:
    - Admission Fee
    - Discharge Processing
    - Room Setup
    - Special Equipment Installation

  daily:
    - Room Charge
    - Nursing Care
    - Meals
    - Utilities
    - Medical Equipment Rental

  hourly:
    - ICU Monitoring
    - Ventilator Usage
    - Specialized Nursing
    - Emergency Care

  percentage:
    - Service Charge (% of total bill)
    - Insurance Deductible (% of covered amount)
    - Late Payment Penalty (% of outstanding)

  quantity_based:
    - Meals Consumed
    - Procedures Performed
    - Medications Administered
    - Diagnostic Tests

  event_triggered:
    - Emergency Response
    - Code Blue Response
    - Transfer Between Wards
    - Visitor Accommodation
```

### 2.2 Dynamic Billing Form Builder

**Ward Billing Configuration:**
- Service Name & Code
- Billing Type (one-time, daily, hourly, %, quantity, event)
- Base Rate/Amount
- Calculation Rules
- Effective Date Range
- Applicable Ward Types
- Patient Category Rules (insurance, private, emergency)
- Auto-trigger Conditions
- Payment Requirements (immediate, deferred, plan)
- Integration Points (which modules can trigger)

---

## 3. Payment Enforcement & Service Blocking

### 3.1 Service Blocking Rules

```yaml
Payment Enforcement Levels:
  strict:
    - No service without payment
    - Immediate payment required
    - Cannot proceed to next step

  moderate:
    - Can defer payment for limited time
    - Can proceed with payment plan
    - Blocks non-essential services

  flexible:
    - Payment reminders only
    - Can accumulate charges
    - Monthly billing cycles

  emergency_override:
    - All services available regardless of payment
    - Charges still accumulate
    - Payment required before discharge
```

### 3.2 Module-Specific Blocking

**Check-in Module:**
- Block: Consultation scheduling if registration fee unpaid
- Allow: Emergency check-in always
- Defer: Non-urgent appointments

**Consultation Module:**
- Block: Starting consultation if department fees unpaid (configurable)
- Allow: Emergency consultations
- Defer: Follow-up appointments

**Laboratory Module:**
- Block: Test processing if payment required
- Allow: Emergency tests
- Hide: Test details until payment (configurable)

**Pharmacy Module:**
- Block: Dispensing if payment required
- Allow: Emergency medications
- Defer: Maintenance medications

**Ward Module:**
- Block: Bed assignment if payment required
- Allow: Emergency admissions
- Defer: Elective procedures

---

## 4. System Configuration

### 4.1 Global Billing Settings

```yaml
System Configuration:
  payment_enforcement:
    global_policy: strict|moderate|flexible|emergency_only
    emergency_override: always|conditional|never
    payment_plans: enabled|disabled
    grace_periods: 0-30 days

  service_policies:
    require_payment_before_service: by_service_type
    allow_partial_payments: enabled|disabled
    auto_payment_plans: enabled|disabled
    payment_reminders: enabled|disabled

  billing_cycles:
    real_time: immediate charge creation
    daily_batch: end of day billing
    weekly_batch: weekly billing runs
    monthly_batch: monthly billing cycles
```

### 4.2 Service-Level Configuration

```yaml
Per Service Configuration:
  service_id: "consultation_cardiology"
  billing_rules:
    charge_timing: on_checkin|before_service|during_service|after_service
    payment_required: mandatory|optional|deferred
    payment_timing: immediate|within_24h|end_of_visit|monthly
    emergency_override: allowed|not_allowed
    partial_payment: allowed|not_allowed
    payment_plans: available|not_available
    grace_period: 0-30 days
    late_fees: enabled|disabled
    service_blocking: enabled|disabled
```

---

## 5. User Interface Integration

### 5.1 Universal Patient Billing Status Widget

**Displayed everywhere patient info appears:**
- Current Outstanding Balance: $XXX.XX
- Payment Status: Good Standing|Payment Due|Overdue
- Available Credit: $XXX.XX
- Next Payment Due: Date
- Blocked Services: List of blocked services
- Payment Plan Status: On Track|Behind|Completed

### 5.2 Service Point Integration

**Each Module Shows:**
- Service Cost: $XXX.XX
- Payment Status: Required|Deferred|Paid
- Patient Balance: Current outstanding
- Action Buttons:
  - Proceed (if payment ok)
  - Process Payment
  - Setup Payment Plan
  - Emergency Override
- Payment History: Recent payments

---

## 6. Workflow Examples

### 6.1 Cardiology Department Visit

1. **Patient Check-in:** "I have chest pain"
2. **Reception:** Checks patient into "Cardiology Department"
3. **System:** Creates "Cardiology Consultation: $75" (PENDING)
4. **Patient:** Waits in cardiology waiting area
5. **Doctor:** Sees patient in queue, tries to start consultation
6. **System:** "Payment required: $75 for cardiology consultation"
7. **Options:**
   - Process payment → Allow consultation
   - Emergency override → Allow consultation, payment later
   - Defer → Patient returns to reception for payment

### 6.2 Lab Test with Payment Enforcement

1. **Doctor:** Orders "Complete Blood Count, Lipid Panel"
2. **System:** Creates lab billing entries
3. **Lab Technician:** Sees "Patient ABC - Lab Order - Payment Required: $65"
4. **Lab Cannot See:** What tests were actually ordered
5. **Payment Processed:** $65 for lab tests
6. **Lab Now Sees:** "Patient ABC - Complete Blood Count, Lipid Panel"
7. **Lab:** Can now process samples and enter results

### 6.3 Ward Admission Process

1. **Doctor:** Admits patient to "General Ward"
2. **System:** Creates daily ward charges (pending)
3. **Nurse:** Sees "John Doe - Admitted - Payment Required: $200/day"
4. **Options:**
   - Payment processed → Can assign bed
   - Emergency override → Can assign bed, payment later
   - Payment plan → Can assign bed with payment schedule

---

## 7. Technical Implementation

### 7.1 Billing Events System

```php
// Event-driven automatic billing
Event: PatientCheckedIn
Event: ConsultationStarted
Event: LabTestOrdered
Event: LabTestProcessed
Event: MedicationDispensed
Event: WardAdmission
Event: BedAssigned
Event: ServiceProvided
```

### 7.2 Payment Enforcement Middleware

```php
// Middleware for service access control
CheckPaymentStatus::class
BlockUnpaidServices::class
EnforcePaymentPolicy::class
AllowEmergencyOverride::class
```

### 7.3 Configuration Models

```php
BillingPolicy          // Global and service-specific policies
ServiceChargeRule      // How each service should be charged
PaymentEnforcement     // Which services require payment when
BillingConfiguration   // System-wide billing settings
WardBillingTemplate    // Dynamic ward billing configurations
DepartmentBilling      // Department-specific billing rules
```

---

## 8. Emergency Care Provisions

### 8.1 Emergency Override Rules

- **Always Available:** Emergency department services
- **Conditional:** Based on patient condition and triage level
- **Staff Controlled:** Authorized staff can override payment requirements
- **Auditable:** All overrides logged for billing review
- **Reversible:** Can be applied retroactively for true emergencies

### 8.2 Emergency Workflow

1. **Emergency Patient Arrives**
2. **Immediate Triage:** No payment checking
3. **Emergency Override:** Automatically applied for critical cases
4. **Service Delivery:** All necessary services provided
5. **Billing Creation:** Charges still created for later processing
6. **Discharge Planning:** Payment arrangements made before discharge

---

## 9. Reporting & Analytics

### 9.1 Financial Reports

- Daily/Monthly/Yearly revenue reports
- Revenue by department/service type
- Payment method analysis
- Outstanding amounts and aging
- Collection rates and trends
- Service utilization and profitability

### 9.2 Operational Reports

- Blocked services due to payment
- Emergency override usage
- Payment plan performance
- Staff payment processing activity
- Service delivery vs billing correlation

---

## 10. Integration Requirements

### 10.1 Existing System Integration

- **Patient Management:** Check-in processes
- **Consultation Module:** Doctor workflows
- **Laboratory Module:** Test ordering and processing
- **Pharmacy Module:** Medication dispensing
- **Ward Management:** Admission and bed assignment
- **User Management:** Staff permissions and roles

### 10.2 External System Integration

- **Payment Gateways:** Card processing
- **Insurance Systems:** Coverage verification and claims
- **Accounting Systems:** Financial data export
- **Government Systems:** Regulatory reporting
- **Bank Systems:** Payment reconciliation

---

This specification provides a comprehensive framework for implementing an integrated billing system that ensures financial controls while maintaining the quality and accessibility of medical care.