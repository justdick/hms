# Requirements Document

## Introduction

This document defines the requirements for enhancing the Hospital Management System's billing module to provide a professional, role-based billing experience. The enhancements separate functionality between Revenue Collectors (cashiers) who collect payments at the counter, and Finance Officers (accountants) who reconcile, audit, and report on financial transactions. The system will support selective charge payments, thermal receipt printing, daily collection tracking, cash reconciliation, and comprehensive financial reporting.

## Glossary

- **Revenue Collector**: A cashier responsible for collecting payments from patients at the billing counter
- **Finance Officer**: An accountant responsible for reconciliation, auditing, and financial reporting
- **Charge**: A billable item for a service rendered to a patient (consultation, lab test, medication, etc.)
- **Partial Payment**: Payment for selected charges only, leaving other charges pending
- **Collection**: The total amount of money received by a cashier during their shift
- **Reconciliation**: The process of comparing system-recorded collections against physical cash counted
- **Thermal Receipt**: A simple receipt printed on thermal paper showing payment confirmation
- **Statement**: A detailed PDF document showing complete payment history for a patient

## Requirements

### Requirement 1: Selective Charge Payment

**User Story:** As a revenue collector, I want to select specific charges for a patient to pay, so that patients can pay for some services now and others later.

#### Acceptance Criteria

1. WHEN a patient's charges are displayed THEN the Billing_System SHALL show a checkbox next to each pending charge with all charges selected by default
2. WHEN a revenue collector unchecks a charge THEN the Billing_System SHALL recalculate the total amount due excluding the unchecked charges
3. WHEN a revenue collector submits a payment THEN the Billing_System SHALL process payment only for the selected charges
4. WHEN selected charges are paid THEN the Billing_System SHALL mark those specific charges as paid while leaving unselected charges as pending
5. WHEN displaying the payment summary THEN the Billing_System SHALL show the count of selected charges, total selected amount, and remaining unpaid amount

### Requirement 2: Revenue Collector Daily Collections

**User Story:** As a revenue collector, I want to see my collections for the current day, so that I can track my work and prepare for end-of-day cash counting.

#### Acceptance Criteria

1. WHEN a revenue collector views the billing page THEN the Billing_System SHALL display a prominent summary card showing their total collections for the current day
2. WHEN displaying daily collections THEN the Billing_System SHALL show breakdown by payment method (Cash, Mobile Money, Card, Bank Transfer)
3. WHEN a new payment is processed THEN the Billing_System SHALL immediately update the daily collections summary without page refresh
4. WHEN displaying collections THEN the Billing_System SHALL show the number of transactions processed today
5. WHEN a revenue collector clicks on their collections summary THEN the Billing_System SHALL display a detailed list of all their transactions for the day

### Requirement 3: Thermal Receipt Printing

**User Story:** As a revenue collector, I want to print a receipt after processing a payment, so that patients have proof of their payment.

#### Acceptance Criteria

1. WHEN a payment is successfully processed THEN the Billing_System SHALL display a print receipt button
2. WHEN the print receipt button is clicked THEN the Billing_System SHALL open a print dialog with a receipt formatted for thermal paper (80mm width)
3. WHEN generating a receipt THEN the Billing_System SHALL include: hospital name, date and time, receipt number, patient name, amount paid, payment method, and cashier name
4. WHEN generating a receipt number THEN the Billing_System SHALL use the format RCP-YYYYMMDD-NNNN where NNNN is a sequential number for the day
5. WHEN the receipt is printed THEN the Billing_System SHALL record the print action in the payment audit trail

### Requirement 4: Cash Change Calculator

**User Story:** As a revenue collector, I want to see the change to give back when a patient pays with cash, so that I can quickly and accurately return the correct amount.

#### Acceptance Criteria

1. WHEN cash is selected as the payment method THEN the Billing_System SHALL display an amount tendered input field
2. WHEN the amount tendered exceeds the amount due THEN the Billing_System SHALL calculate and prominently display the change to return
3. WHEN the amount tendered is less than the amount due THEN the Billing_System SHALL display a warning indicating insufficient payment
4. WHEN displaying change THEN the Billing_System SHALL show the calculation breakdown (Tendered - Due = Change)

### Requirement 5: Finance Officer Dashboard

**User Story:** As a finance officer, I want to view a comprehensive dashboard of all billing activities, so that I can monitor hospital revenue and cashier performance.

#### Acceptance Criteria

1. WHEN a finance officer accesses the accounts section THEN the Billing_System SHALL display a dashboard with today's total collections across all cashiers
2. WHEN displaying the dashboard THEN the Billing_System SHALL show collections grouped by cashier with individual totals
3. WHEN displaying the dashboard THEN the Billing_System SHALL show collections breakdown by payment method
4. WHEN displaying the dashboard THEN the Billing_System SHALL show collections breakdown by department
5. WHEN a date range is selected THEN the Billing_System SHALL filter all dashboard metrics to the selected period

### Requirement 6: Cash Reconciliation

**User Story:** As a finance officer, I want to reconcile each cashier's collections against their physical cash count, so that I can identify and investigate discrepancies.

#### Acceptance Criteria

1. WHEN a finance officer initiates reconciliation for a cashier THEN the Billing_System SHALL display the system-recorded total for that cashier's shift
2. WHEN performing reconciliation THEN the Billing_System SHALL provide input fields for the physical cash count by denomination
3. WHEN physical count is entered THEN the Billing_System SHALL calculate and display any variance between system total and physical count
4. WHEN a variance exists THEN the Billing_System SHALL require a reason to be entered before saving the reconciliation
5. WHEN reconciliation is saved THEN the Billing_System SHALL record the reconciliation with timestamp, finance officer, cashier, amounts, variance, and reason
6. WHEN viewing reconciliation history THEN the Billing_System SHALL display all past reconciliations with their status and any variances

### Requirement 7: Payment History and Audit Trail

**User Story:** As a finance officer, I want to view complete payment history with audit details, so that I can investigate transactions and maintain accountability.

#### Acceptance Criteria

1. WHEN viewing payment history THEN the Billing_System SHALL display all payments with filters for date range, cashier, patient, payment method, and amount range
2. WHEN displaying a payment record THEN the Billing_System SHALL show: patient name, amount, payment method, cashier, timestamp, receipt number, and charges paid
3. WHEN a payment is voided or refunded THEN the Billing_System SHALL maintain the original record and create a linked reversal record
4. WHEN viewing a payment THEN the Billing_System SHALL show the complete audit trail including any modifications, voids, or refunds
5. WHEN searching payments THEN the Billing_System SHALL support search by receipt number, patient name, or patient number

### Requirement 8: PDF Statement Generation

**User Story:** As a finance officer, I want to generate detailed PDF statements for patients, so that I can provide official documentation of their payment history when requested.

#### Acceptance Criteria

1. WHEN a finance officer requests a patient statement THEN the Billing_System SHALL generate a PDF document with hospital letterhead
2. WHEN generating a statement THEN the Billing_System SHALL include: patient details, all charges with dates and descriptions, all payments with dates and methods, and current balance
3. WHEN generating a statement THEN the Billing_System SHALL allow selection of date range for the statement period
4. WHEN the PDF is generated THEN the Billing_System SHALL provide options to download or print the document
5. WHEN a statement is generated THEN the Billing_System SHALL record the generation in the audit trail

### Requirement 9: Outstanding Balance Reports

**User Story:** As a finance officer, I want to view reports of outstanding patient balances, so that I can identify debtors and manage collections.

#### Acceptance Criteria

1. WHEN viewing outstanding balances THEN the Billing_System SHALL display a list of patients with unpaid charges sorted by amount descending
2. WHEN displaying outstanding balances THEN the Billing_System SHALL show aging categories (Current, 30 days, 60 days, 90+ days)
3. WHEN viewing the report THEN the Billing_System SHALL allow filtering by department, insurance status, and minimum amount
4. WHEN viewing a patient's outstanding balance THEN the Billing_System SHALL show breakdown by service type
5. WHEN the report is generated THEN the Billing_System SHALL provide export options for PDF and Excel formats

### Requirement 10: Revenue Reports

**User Story:** As a finance officer, I want to generate revenue reports by various dimensions, so that I can analyze hospital financial performance.

#### Acceptance Criteria

1. WHEN generating a revenue report THEN the Billing_System SHALL allow selection of grouping by: date, department, service type, payment method, or cashier
2. WHEN displaying revenue data THEN the Billing_System SHALL show totals, averages, and transaction counts for each group
3. WHEN a date range is selected THEN the Billing_System SHALL compare current period to previous period with percentage change
4. WHEN viewing revenue trends THEN the Billing_System SHALL display a chart visualization of daily or monthly revenue
5. WHEN the report is generated THEN the Billing_System SHALL provide export options for PDF and Excel formats

### Requirement 11: Professional UI/UX Design

**User Story:** As a user, I want a clean, intuitive, and professional interface, so that I can work efficiently and the system reflects the hospital's professional standards.

#### Acceptance Criteria

1. WHEN displaying the billing interface THEN the Billing_System SHALL use consistent spacing, typography, and color scheme aligned with the hospital brand
2. WHEN displaying monetary values THEN the Billing_System SHALL format them consistently with currency symbol and two decimal places
3. WHEN performing actions THEN the Billing_System SHALL provide immediate visual feedback through loading states, success messages, and error indicators
4. WHEN displaying data tables THEN the Billing_System SHALL support sorting, filtering, and pagination with smooth transitions
5. WHEN the interface is used on different screen sizes THEN the Billing_System SHALL adapt responsively while maintaining usability
6. WHEN displaying important information THEN the Billing_System SHALL use appropriate visual hierarchy with cards, badges, and color coding
7. WHEN performing focused actions like payment processing or reconciliation THEN the Billing_System SHALL use modal dialogs to maintain context and prevent navigation away
8. WHEN displaying transaction details or receipt preview THEN the Billing_System SHALL use slide-over panels for quick viewing without losing page context
9. WHEN confirming destructive or important actions THEN the Billing_System SHALL display a confirmation modal with clear action buttons

### Requirement 12: Granular Permission-Based Access Control

**User Story:** As a system administrator, I want billing features restricted by granular permissions assigned to roles, so that sensitive financial operations are only accessible to authorized personnel.

#### Acceptance Criteria

1. WHEN a user with billing.collect permission accesses the billing module THEN the Billing_System SHALL display the payment collection interface
2. WHEN a user with billing.reconcile permission accesses the accounts section THEN the Billing_System SHALL display the reconciliation features
3. WHEN a user with billing.reports permission accesses reports THEN the Billing_System SHALL display the reporting features
4. WHEN a user without the required permission accesses a billing route THEN the Billing_System SHALL deny access and display an appropriate message
5. WHEN displaying navigation THEN the Billing_System SHALL show only menu items the user has permission to access based on their assigned permissions
6. WHEN checking access THEN the Billing_System SHALL verify permissions not roles directly

### Requirement 13: Service Override for Credit Patients

**User Story:** As an authorized user, I want to allow patients to receive services without immediate payment, so that patients in urgent need or with credit arrangements can be served while recording the amount owed.

#### Acceptance Criteria

1. WHEN a user with billing.override permission processes a service for an unpaid patient THEN the Billing_System SHALL display an override option
2. WHEN an override is requested THEN the Billing_System SHALL require a reason to be entered
3. WHEN an override is approved THEN the Billing_System SHALL allow the service to proceed and mark the charge as owing
4. WHEN an override is created THEN the Billing_System SHALL record the override with authorizing user, reason, timestamp, and patient
5. WHEN viewing a patient's billing THEN the Billing_System SHALL display any active overrides and owing amounts
6. WHEN an override is used THEN the Billing_System SHALL create an audit trail entry

### Requirement 14: Patient Credit Tags (VIP/Credit Account)

**User Story:** As a billing administrator, I want to tag certain patients as credit-eligible, so that they can receive services without payment blocking at any visit without needing individual overrides.

#### Acceptance Criteria

1. WHEN a patient is tagged as credit-eligible THEN the Billing_System SHALL allow all services without payment blocking for that patient
2. WHEN a credit-tagged patient receives services THEN the Billing_System SHALL automatically record charges as owing without requiring override
3. WHEN viewing a credit-tagged patient THEN the Billing_System SHALL display a visible credit badge on their profile
4. WHEN a user with billing.manage-credit permission accesses patient settings THEN the Billing_System SHALL allow adding or removing credit tags
5. WHEN a credit tag is added or removed THEN the Billing_System SHALL record the change with user, reason, and timestamp
6. WHEN displaying patients with credit tags THEN the Billing_System SHALL show their total owing amount
