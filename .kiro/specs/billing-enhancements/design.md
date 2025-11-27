# Design Document: Billing Module Enhancements

## Overview

This design document outlines the architecture and implementation approach for enhancing the HMS billing module. The enhancements create a role-based billing system with two distinct interfaces: a streamlined payment collection interface for Revenue Collectors (cashiers) and a comprehensive financial management interface for Finance Officers (accountants).

The system will support selective charge payments, thermal receipt printing via browser, daily collection tracking per cashier, cash reconciliation workflows, and comprehensive financial reporting with PDF/Excel exports.

## Architecture

### High-Level Architecture

```
┌─────────────────────────────────────────────────────────────────┐
│                        Frontend (React/Inertia)                  │
├─────────────────────────────────────────────────────────────────┤
│  Revenue Collector UI          │    Finance Officer UI           │
│  ├── Payment Collection        │    ├── Dashboard                │
│  ├── My Collections            │    ├── Reconciliation           │
│  ├── Receipt Printing          │    ├── Payment History          │
│  └── Change Calculator         │    ├── Reports                  │
│                                │    └── PDF Statements           │
├─────────────────────────────────────────────────────────────────┤
│                     Controllers (Laravel)                        │
│  PaymentController │ CollectionController │ ReportController     │
│  ReconciliationController │ StatementController                  │
├─────────────────────────────────────────────────────────────────┤
│                      Services Layer                              │
│  BillingService │ CollectionService │ ReportService              │
│  ReconciliationService │ PdfService                              │
├─────────────────────────────────────────────────────────────────┤
│                      Models & Database                           │
│  Payment │ Charge │ Reconciliation │ AuditLog │ Receipt          │
└─────────────────────────────────────────────────────────────────┘
```

### Route Structure

```
/billing
├── /payments                    # Revenue Collector (existing, enhanced)
│   ├── GET  /                   # Payment collection page
│   ├── POST /process            # Process payment
│   ├── GET  /my-collections     # Cashier's daily collections
│   ├── GET  /receipt/{id}       # Receipt view for printing
│   └── POST /override           # Create service override (billing.override permission)
│
└── /accounts                    # Finance Officer (new)
    ├── GET  /                   # Dashboard
    ├── GET  /collections        # All collections view
    ├── GET  /reconciliation     # Reconciliation list
    ├── POST /reconciliation     # Create reconciliation
    ├── GET  /history            # Payment history
    ├── GET  /statements/{patient} # Patient statement
    ├── GET  /reports/outstanding  # Outstanding balances (includes owing)
    ├── GET  /reports/revenue      # Revenue reports
    └── GET  /credit-patients      # List of credit-tagged patients
```

### Permissions Structure

```
billing.collect          # Process payments, view own collections
billing.view-all         # View all billing data
billing.override         # Create service overrides for patients
billing.reconcile        # Perform cash reconciliation
billing.reports          # Access financial reports
billing.statements       # Generate patient statements
billing.manage-credit    # Add/remove patient credit tags
billing.void             # Void payments
billing.refund           # Process refunds
billing.configure        # Manage billing configuration
```

## Components and Interfaces

### Frontend Components

#### Revenue Collector Interface

```
pages/Billing/Payments/
├── Index.tsx                    # Enhanced payment collection
├── components/
│   ├── ChargeSelectionList.tsx  # Checkboxes for charge selection
│   ├── PaymentModal.tsx         # Payment processing modal
│   ├── MyCollectionsCard.tsx    # Daily collections summary
│   ├── MyCollectionsModal.tsx   # Detailed transactions list
│   ├── ChangeCalculator.tsx     # Cash change calculator
│   ├── ReceiptPreview.tsx       # Thermal receipt preview
│   ├── PrintableReceipt.tsx     # Print-optimized receipt
│   ├── ServiceOverrideModal.tsx # Override service for credit
│   └── PatientCreditBadge.tsx   # Shows credit-eligible status
```

#### Finance Officer Interface

```
pages/Billing/Accounts/
├── Index.tsx                    # Dashboard
├── Collections.tsx              # All cashiers' collections
├── Reconciliation/
│   ├── Index.tsx                # Reconciliation list
│   └── CreateModal.tsx          # Reconciliation form modal
├── History/
│   ├── Index.tsx                # Payment history
│   └── DetailSlideOver.tsx      # Payment detail panel
├── Statements/
│   └── GenerateModal.tsx        # Statement generation modal
├── Reports/
│   ├── Outstanding.tsx          # Outstanding balances (includes owing)
│   └── Revenue.tsx              # Revenue reports
└── CreditPatients/
    ├── Index.tsx                # List of credit-tagged patients
    └── ManageCreditModal.tsx    # Add/remove credit tag modal
```

### Backend Services

#### CollectionService

```php
interface CollectionService {
    // Get cashier's collections for a date
    public function getCashierCollections(User $cashier, Carbon $date): Collection;
    
    // Get collections breakdown by payment method
    public function getCollectionsByPaymentMethod(User $cashier, Carbon $date): array;
    
    // Get all cashiers' collections for dashboard
    public function getAllCollections(Carbon $startDate, Carbon $endDate): Collection;
    
    // Get collections grouped by cashier
    public function getCollectionsByCashier(Carbon $date): Collection;
    
    // Get collections grouped by department
    public function getCollectionsByDepartment(Carbon $startDate, Carbon $endDate): Collection;
}
```

#### ReconciliationService

```php
interface ReconciliationService {
    // Get system total for cashier
    public function getSystemTotal(User $cashier, Carbon $date): float;
    
    // Calculate variance
    public function calculateVariance(float $systemTotal, float $physicalCount): float;
    
    // Create reconciliation record
    public function createReconciliation(array $data): Reconciliation;
    
    // Get reconciliation history
    public function getReconciliationHistory(array $filters): Collection;
}
```

#### ReportService

```php
interface ReportService {
    // Outstanding balances with aging
    public function getOutstandingBalances(array $filters): Collection;
    
    // Revenue report with grouping
    public function getRevenueReport(Carbon $start, Carbon $end, string $groupBy): array;
    
    // Export to Excel
    public function exportToExcel(string $reportType, array $data): string;
    
    // Export to PDF
    public function exportToPdf(string $reportType, array $data): string;
}
```

#### PdfService

```php
interface PdfService {
    // Generate patient statement PDF
    public function generateStatement(Patient $patient, Carbon $start, Carbon $end): string;
    
    // Generate report PDF
    public function generateReport(string $type, array $data): string;
}
```

## Data Models

### New Models

#### Payment (Enhanced)

```php
// Existing charges table enhanced with:
Schema::table('charges', function (Blueprint $table) {
    $table->string('receipt_number')->nullable()->after('paid_at');
    $table->unsignedBigInteger('processed_by')->nullable()->after('receipt_number');
    $table->foreign('processed_by')->references('id')->on('users');
});
```

#### Reconciliation

```php
Schema::create('reconciliations', function (Blueprint $table) {
    $table->id();
    $table->unsignedBigInteger('cashier_id');
    $table->unsignedBigInteger('finance_officer_id');
    $table->date('reconciliation_date');
    $table->decimal('system_total', 12, 2);
    $table->decimal('physical_count', 12, 2);
    $table->decimal('variance', 12, 2);
    $table->text('variance_reason')->nullable();
    $table->json('denomination_breakdown')->nullable();
    $table->enum('status', ['balanced', 'variance', 'pending'])->default('pending');
    $table->timestamps();
    
    $table->foreign('cashier_id')->references('id')->on('users');
    $table->foreign('finance_officer_id')->references('id')->on('users');
    $table->unique(['cashier_id', 'reconciliation_date']);
});
```

#### PaymentAuditLog

```php
Schema::create('payment_audit_logs', function (Blueprint $table) {
    $table->id();
    $table->unsignedBigInteger('charge_id')->nullable();
    $table->unsignedBigInteger('patient_id')->nullable();
    $table->unsignedBigInteger('user_id');
    $table->string('action'); // payment, void, refund, receipt_printed, statement_generated, override, credit_tag_added, credit_tag_removed
    $table->json('old_values')->nullable();
    $table->json('new_values')->nullable();
    $table->text('reason')->nullable();
    $table->string('ip_address')->nullable();
    $table->timestamps();
    
    $table->foreign('charge_id')->references('id')->on('charges');
    $table->foreign('patient_id')->references('id')->on('patients');
    $table->foreign('user_id')->references('id')->on('users');
});
```

#### ServiceOverride (Enhanced)

```php
// Enhance existing service_access_overrides table or create new
Schema::create('billing_overrides', function (Blueprint $table) {
    $table->id();
    $table->unsignedBigInteger('patient_checkin_id');
    $table->unsignedBigInteger('charge_id')->nullable();
    $table->unsignedBigInteger('authorized_by');
    $table->string('service_type');
    $table->text('reason');
    $table->enum('status', ['active', 'used', 'expired'])->default('active');
    $table->timestamp('authorized_at');
    $table->timestamp('expires_at')->nullable();
    $table->timestamps();
    
    $table->foreign('patient_checkin_id')->references('id')->on('patient_checkins');
    $table->foreign('charge_id')->references('id')->on('charges');
    $table->foreign('authorized_by')->references('id')->on('users');
});
```

#### Patient Credit Tag

```php
// Add to patients table
Schema::table('patients', function (Blueprint $table) {
    $table->boolean('is_credit_eligible')->default(false)->after('status');
    $table->text('credit_reason')->nullable()->after('is_credit_eligible');
    $table->unsignedBigInteger('credit_authorized_by')->nullable()->after('credit_reason');
    $table->timestamp('credit_authorized_at')->nullable()->after('credit_authorized_by');
    
    $table->foreign('credit_authorized_by')->references('id')->on('users');
});
```

#### Charge Status Enhancement

```php
// Update charge status enum to include 'owing'
// Status: pending, paid, partial, waived, cancelled, owing
```

### Receipt Number Generation

```php
// Format: RCP-YYYYMMDD-NNNN
// Example: RCP-20251127-0042

public function generateReceiptNumber(): string
{
    $date = now()->format('Ymd');
    $prefix = "RCP-{$date}-";
    
    $lastReceipt = Charge::whereDate('paid_at', today())
        ->whereNotNull('receipt_number')
        ->orderByDesc('receipt_number')
        ->first();
    
    if ($lastReceipt) {
        $lastNumber = (int) substr($lastReceipt->receipt_number, -4);
        $nextNumber = $lastNumber + 1;
    } else {
        $nextNumber = 1;
    }
    
    return $prefix . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
}
```

## Correctness Properties

*A property is a characteristic or behavior that should hold true across all valid executions of a system-essentially, a formal statement about what the system should do. Properties serve as the bridge between human-readable specifications and machine-verifiable correctness guarantees.*

### Property 1: Selected charges total calculation
*For any* set of charges with checkboxes, the displayed total amount SHALL equal the sum of amounts for only the checked charges.
**Validates: Requirements 1.2, 1.5**

### Property 2: Payment processes only selected charges
*For any* payment submission with a subset of charges selected, only those selected charges SHALL have their status changed to paid.
**Validates: Requirements 1.3, 1.4**

### Property 3: Cashier collections accuracy
*For any* cashier on any date, the displayed collection total SHALL equal the sum of all payments processed by that cashier on that date.
**Validates: Requirements 2.1, 2.4**

### Property 4: Collections breakdown consistency
*For any* cashier's daily collections, the sum of amounts grouped by payment method SHALL equal the total collections amount.
**Validates: Requirements 2.2, 5.3**

### Property 5: Receipt number format and uniqueness
*For any* generated receipt number, it SHALL match the pattern RCP-YYYYMMDD-NNNN and be unique within the system.
**Validates: Requirements 3.4**

### Property 6: Receipt data completeness
*For any* generated receipt, the data structure SHALL contain all required fields: hospital name, date, time, receipt number, patient name, amount, payment method, and cashier name.
**Validates: Requirements 3.3**

### Property 7: Change calculation accuracy
*For any* cash payment where tendered amount exceeds due amount, the calculated change SHALL equal tendered minus due.
**Validates: Requirements 4.2**

### Property 8: Reconciliation variance calculation
*For any* reconciliation, the variance SHALL equal physical count minus system total.
**Validates: Requirements 6.3**

### Property 9: Reconciliation validation
*For any* reconciliation with non-zero variance, the system SHALL require a variance reason before saving.
**Validates: Requirements 6.4**

### Property 10: Date range filtering
*For any* date range filter applied to collections or reports, all returned records SHALL have dates within the specified range inclusive.
**Validates: Requirements 5.5, 8.3**

### Property 11: Outstanding balance aging categorization
*For any* outstanding charge, it SHALL be categorized into exactly one aging bucket based on days since charge date.
**Validates: Requirements 9.2**

### Property 12: Role-based access enforcement
*For any* user with revenue collector role accessing finance officer routes, the system SHALL return a 403 forbidden response.
**Validates: Requirements 12.3, 12.5**

### Property 13: Audit trail completeness
*For any* payment action (payment, void, refund, receipt print), an audit log entry SHALL be created with the action type, user, and timestamp.
**Validates: Requirements 3.5, 7.4, 8.5**

### Property 14: Permission-based access enforcement
*For any* user without the specific required permission accessing a protected route, the system SHALL return a 403 forbidden response regardless of their role.
**Validates: Requirements 12.4, 12.6**

### Property 15: Service override creates owing record
*For any* approved service override, the charge SHALL be marked as 'owing' status and an audit record SHALL be created with the authorizing user and reason.
**Validates: Requirements 13.3, 13.4, 13.6**

### Property 16: Credit-tagged patient bypass
*For any* patient with an active credit tag, service blocking checks SHALL return true (allowed) regardless of pending payment amounts.
**Validates: Requirements 14.1, 14.2**

### Property 17: Credit tag audit trail
*For any* credit tag addition or removal, an audit record SHALL be created with the user, action, reason, and timestamp.
**Validates: Requirements 14.5**

## Error Handling

### Payment Processing Errors

| Error | Handling |
|-------|----------|
| No charges selected | Display validation error, prevent submission |
| Charge already paid | Display error, refresh charge list |
| Insufficient amount (non-cash) | Display validation error |
| Database transaction failure | Rollback, display error, log details |

### Reconciliation Errors

| Error | Handling |
|-------|----------|
| Duplicate reconciliation for date | Display error, show existing reconciliation |
| Missing variance reason | Display validation error |
| Invalid denomination values | Display validation error |

### Report Generation Errors

| Error | Handling |
|-------|----------|
| No data for date range | Display empty state message |
| PDF generation failure | Display error, offer retry |
| Export timeout | Display error, suggest smaller date range |

## Testing Strategy

### Unit Testing

Unit tests will cover:
- Receipt number generation logic
- Change calculation
- Variance calculation
- Aging categorization logic
- Collection aggregation functions

### Property-Based Testing

Property-based tests will use Pest with the `pestphp/pest-plugin-faker` for generating test data. Each correctness property will have a corresponding property-based test that verifies the property holds across many randomly generated inputs.

**Testing Framework:** Pest v4 with faker plugin

**Minimum iterations:** 100 per property test

**Test file location:** `tests/Feature/Billing/`

### Integration Testing

Integration tests will cover:
- Payment processing workflow end-to-end
- Reconciliation creation and retrieval
- Report generation with various filters
- PDF statement generation
- Role-based access control

### Browser Testing

Browser tests using Pest v4 browser testing will cover:
- Charge selection UI interaction
- Payment modal workflow
- Receipt printing flow
- Reconciliation form submission
- Report filtering and export

## UI/UX Design Specifications

### Color Scheme

| Element | Color | Usage |
|---------|-------|-------|
| Primary | Blue-600 | Action buttons, links |
| Success | Green-600 | Paid status, positive amounts |
| Warning | Orange-600 | Pending, patient owes |
| Danger | Red-600 | Errors, variances |
| Neutral | Gray shades | Backgrounds, borders |

### Modal Usage

| Action | Component | Reason |
|--------|-----------|--------|
| Process Payment | Modal | Focused workflow, prevent accidental navigation |
| View Transaction Details | SlideOver | Quick view without losing context |
| Reconciliation Form | Modal | Multi-step form requiring focus |
| Generate Statement | Modal | Date selection and confirmation |
| Confirm Void/Refund | Confirmation Modal | Destructive action protection |

### Card Layout

- **My Collections Card**: Prominent position, top-right of payment page
- **Stats Cards**: Grid of 4 cards showing key metrics
- **Patient Billing Card**: Expandable card showing charges with checkboxes

### Table Design

- Sortable columns with visual indicators
- Pagination with page size options (10, 25, 50)
- Row hover states
- Action buttons in last column
- Responsive: horizontal scroll on mobile

### Receipt Design (Thermal 80mm)

```
================================
      HOSPITAL NAME
      Address Line 1
      Phone: XXX-XXX-XXXX
================================
Receipt: RCP-20251127-0042
Date: Nov 27, 2025 10:30 AM
--------------------------------
Patient: John Doe
Patient #: PAT2025000123
--------------------------------
Amount Paid: GHS 150.00
Payment Method: Cash
--------------------------------
Cashier: Jane Smith
--------------------------------
      Thank you!
================================
```
