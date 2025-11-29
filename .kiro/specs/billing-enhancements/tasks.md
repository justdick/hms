# Implementation Plan

## Phase 1: Database & Backend Foundation

- [x] 1. Create database migrations and models
  - [x] 1.1 Create migration to add receipt_number and processed_by to charges table
    - Add `receipt_number` varchar nullable column
    - Add `processed_by` foreign key to users table
    - Add `owing` to status enum
    - _Requirements: 3.4, 13.3_
  - [x] 1.2 Create reconciliations table migration and model
    - Create migration with all fields from design
    - Create Reconciliation model with relationships
    - _Requirements: 6.5_
  - [x] 1.3 Create payment_audit_logs table migration and model
    - Create migration with all fields from design
    - Create PaymentAuditLog model with relationships
    - _Requirements: 7.4, 13.6_
  - [x] 1.4 Create billing_overrides table migration and model
    - Create migration with all fields from design
    - Create BillingOverride model with relationships
    - _Requirements: 13.4_
  - [x] 1.5 Add credit fields to patients table
    - Add `is_credit_eligible`, `credit_reason`, `credit_authorized_by`, `credit_authorized_at`
    - Update Patient model with relationships
    - _Requirements: 14.1, 14.3_
  - [x] 1.6 Write property tests for charge status transitions
    - **Property 2: Payment processes only selected charges**
    - **Validates: Requirements 1.3, 1.4**

- [x] 2. Create billing permissions
  - [x] 2.1 Add granular billing permissions to seeder
    - Add: billing.collect, billing.view-all, billing.override, billing.reconcile, billing.reports, billing.statements, billing.manage-credit, billing.void, billing.refund
    - _Requirements: 12.1-12.6_
  - [x] 2.2 Update BillingPolicy to use granular permissions
    - Replace role checks with permission checks
    - _Requirements: 12.6_
  - [x] 2.3 Write property test for permission-based access
    - **Property 14: Permission-based access enforcement**
    - **Validates: Requirements 12.4, 12.6**

- [x] 3. Checkpoint - Ensure all tests pass
  - Ensure all tests pass, ask the user if questions arise.

## Phase 2: Revenue Collector Enhancements

- [x] 4. Implement selective charge payment
  - [x] 4.1 Update PaymentController to handle selected charges array
    - Modify processPayment to accept charges array
    - Process only selected charges
    - _Requirements: 1.3, 1.4_
  - [x] 4.2 Create ChargeSelectionList component
    - Checkboxes for each charge, all selected by default
    - Calculate totals based on selection
    - _Requirements: 1.1, 1.2, 1.5_
  - [x] 4.3 Write property test for selected charges total calculation
    - **Property 1: Selected charges total calculation**
    - **Validates: Requirements 1.2, 1.5**

- [x] 5. Implement daily collections tracking
  - [x] 5.1 Create CollectionService
    - getCashierCollections method
    - getCollectionsByPaymentMethod method
    - _Requirements: 2.1, 2.2_
  - [x] 5.2 Add my-collections endpoint to PaymentController
    - Return cashier's collections for today
    - Include breakdown by payment method
    - _Requirements: 2.1, 2.2, 2.4_
  - [x] 5.3 Create MyCollectionsCard component
    - Display total collections prominently
    - Show breakdown by payment method
    - Show transaction count
    - _Requirements: 2.1, 2.2, 2.4_
  - [x] 5.4 Create MyCollectionsModal component
    - Detailed list of all transactions for the day
    - _Requirements: 2.5_
  - [x] 5.5 Write property test for cashier collections accuracy
    - **Property 3: Cashier collections accuracy**
    - **Property 4: Collections breakdown consistency**
    - **Validates: Requirements 2.1, 2.2, 2.4**

- [x] 6. Implement receipt generation and printing
  - [x] 6.1 Create receipt number generation service
    - Format: RCP-YYYYMMDD-NNNN
    - Ensure uniqueness
    - _Requirements: 3.4_
  - [x] 6.2 Create receipt endpoint in PaymentController
    - Return receipt data for a payment
    - _Requirements: 3.3_
  - [x] 6.3 Create PrintableReceipt component
    - Styled for 80mm thermal paper
    - Include all required fields
    - _Requirements: 3.2, 3.3_
  - [x] 6.4 Create ReceiptPreview component with print button
    - Show receipt preview
    - Trigger browser print dialog
    - _Requirements: 3.1, 3.2_
  - [x] 6.5 Add audit logging for receipt prints
    - Log print action to payment_audit_logs
    - _Requirements: 3.5_
  - [x] 6.6 Write property tests for receipt generation
    - **Property 5: Receipt number format and uniqueness**
    - **Property 6: Receipt data completeness**
    - **Validates: Requirements 3.3, 3.4**

- [x] 7. Implement cash change calculator
  - [x] 7.1 Create ChangeCalculator component
    - Amount tendered input (shown for cash payments)
    - Calculate and display change
    - Show warning for insufficient amount
    - _Requirements: 4.1, 4.2, 4.3, 4.4_
  - [x] 7.2 Write property test for change calculation
    - **Property 7: Change calculation accuracy**
    - **Validates: Requirements 4.2**

- [x] 8. Checkpoint - Ensure all tests pass
  - Ensure all tests pass, ask the user if questions arise.

## Phase 3: Service Override & Credit System

- [x] 9. Implement service override functionality
  - [x] 9.1 Create OverrideService
    - createOverride method
    - checkOverrideStatus method
    - _Requirements: 13.3, 13.4_
  - [x] 9.2 Add override endpoint to PaymentController
    - Require billing.override permission
    - Create override record
    - Mark charge as owing
    - _Requirements: 13.1, 13.2, 13.3_
  - [x] 9.3 Create ServiceOverrideModal component
    - Reason input required
    - Confirmation before creating
    - _Requirements: 13.2_
  - [x] 9.4 Update BillingService.canProceedWithService to check overrides
    - Check for active overrides
    - Allow service if override exists
    - _Requirements: 13.3_
  - [x] 9.5 Write property test for override creates owing record
    - **Property 15: Service override creates owing record**
    - **Validates: Requirements 13.3, 13.4, 13.6**

- [x] 10. Implement patient credit tags
  - [x] 10.1 Create CreditService
    - addCreditTag method
    - removeCreditTag method
    - getCreditPatients method
    - _Requirements: 14.4, 14.6_
  - [x] 10.2 Update BillingService.canProceedWithService to check credit tag
    - If patient is_credit_eligible, always return true
    - Auto-mark charges as owing
    - _Requirements: 14.1, 14.2_
  - [x] 10.3 Create PatientCreditBadge component
    - Display credit badge on patient profile
    - Show total owing amount
    - _Requirements: 14.3, 14.6_
  - [x] 10.4 Create ManageCreditModal component
    - Add/remove credit tag
    - Require reason
    - _Requirements: 14.4, 14.5_
  - [x] 10.5 Write property tests for credit tag system
    - **Property 16: Credit-tagged patient bypass**
    - **Property 17: Credit tag audit trail**
    - **Validates: Requirements 14.1, 14.2, 14.5**

- [x] 11. Checkpoint - Ensure all tests pass
  - Ensure all tests pass, ask the user if questions arise.

## Phase 4: Finance Officer Dashboard & Collections

- [x] 12. Implement finance officer dashboard
  - [x] 12.1 Create AccountsController
    - index method for dashboard
    - Require billing.view-all permission
    - _Requirements: 5.1_
  - [x] 12.2 Extend CollectionService for all cashiers
    - getAllCollections method
    - getCollectionsByCashier method
    - getCollectionsByDepartment method
    - _Requirements: 5.2, 5.3, 5.4_
  - [x] 12.3 Create Accounts/Index.tsx dashboard page
    - Total collections card
    - Collections by cashier table
    - Collections by payment method chart
    - Collections by department chart
    - Date range filter
    - _Requirements: 5.1, 5.2, 5.3, 5.4, 5.5_
  - [x] 12.4 Write property test for date range filtering
    - **Property 10: Date range filtering**
    - **Validates: Requirements 5.5**

- [x] 13. Implement cash reconciliation
  - [x] 13.1 Create ReconciliationService
    - getSystemTotal method
    - calculateVariance method
    - createReconciliation method
    - getReconciliationHistory method
    - _Requirements: 6.1, 6.3, 6.5, 6.6_
  - [x] 13.2 Create ReconciliationController
    - index method for list
    - store method for creating
    - Require billing.reconcile permission
    - _Requirements: 6.1, 6.5_
  - [x] 13.3 Create Reconciliation/Index.tsx page
    - List of reconciliations with status
    - Filter by date, cashier
    - _Requirements: 6.6_
  - [x] 13.4 Create CreateReconciliationModal component
    - Select cashier
    - Show system total
    - Denomination breakdown inputs
    - Calculate variance
    - Require reason if variance
    - _Requirements: 6.1, 6.2, 6.3, 6.4_
  - [x] 13.5 Write property tests for reconciliation
    - **Property 8: Reconciliation variance calculation**
    - **Property 9: Reconciliation validation**
    - **Validates: Requirements 6.3, 6.4**

- [x] 14. Checkpoint - Ensure all tests pass
  - Ensure all tests pass, ask the user if questions arise.

## Phase 5: Payment History & Audit Trail

- [x] 15. Implement payment history
  - [x] 15.1 Create HistoryController
    - index method with filters
    - show method for detail
    - Require billing.view-all permission
    - _Requirements: 7.1, 7.2_
  - [x] 15.2 Create History/Index.tsx page
    - Filterable table (date, cashier, patient, method, amount)
    - Search by receipt number, patient name/number
    - Pagination
    - _Requirements: 7.1, 7.5_
  - [x] 15.3 Create DetailSlideOver component
    - Full payment details
    - Audit trail display
    - _Requirements: 7.2, 7.4_
  - [x] 15.4 Write property test for audit trail completeness
    - **Property 13: Audit trail completeness**
    - **Validates: Requirements 3.5, 7.4, 8.5**

- [x] 16. Implement void and refund functionality
  - [x] 16.1 Add void endpoint to PaymentController
    - Require billing.void permission
    - Create reversal record
    - Maintain original record
    - _Requirements: 7.3_
  - [x] 16.2 Add refund endpoint to PaymentController
    - Require billing.refund permission
    - Create reversal record
    - _Requirements: 7.3_
  - [x] 16.3 Create VoidConfirmationModal component
    - Require reason
    - Show warning
    - _Requirements: 7.3_
  - [x] 16.4 Write property test for void/refund records
    - **Property: Void maintains original and creates reversal**
    - **Validates: Requirements 7.3**

- [x] 17. Checkpoint - Ensure all tests pass
  - Ensure all tests pass, ask the user if questions arise.

## Phase 6: Reports & PDF Generation

- [x] 18. Implement PDF statement generation
  - [x] 18.1 Create PdfService
    - generateStatement method
    - Use Laravel DomPDF or similar
    - _Requirements: 8.1, 8.2_
  - [x] 18.2 Create StatementController
    - generate method
    - Require billing.statements permission
    - _Requirements: 8.1, 8.3_
  - [x] 18.3 Create GenerateStatementModal component
    - Patient selection
    - Date range selection
    - Download/print options
    - _Requirements: 8.3, 8.4_
  - [x] 18.4 Create statement PDF template
    - Hospital letterhead
    - Patient details
    - Charges table
    - Payments table
    - Balance summary
    - _Requirements: 8.1, 8.2_
  - [x] 18.5 Write property test for statement data completeness
    - **Property: Statement contains all required sections**
    - **Validates: Requirements 8.2**

- [x] 19. Implement outstanding balance report
  - [x] 19.1 Create ReportService
    - getOutstandingBalances method with aging
    - Export methods
    - _Requirements: 9.1, 9.2_
  - [x] 19.2 Create Reports/Outstanding.tsx page
    - Table with aging columns
    - Filters (department, insurance, amount)
    - Export buttons (PDF, Excel)
    - _Requirements: 9.1, 9.2, 9.3, 9.4, 9.5_
  - [x] 19.3 Write property test for aging categorization
    - **Property 11: Outstanding balance aging categorization**
    - **Validates: Requirements 9.2**

- [x] 20. Implement revenue reports
  - [x] 20.1 Extend ReportService for revenue
    - getRevenueReport method with grouping
    - Period comparison
    - _Requirements: 10.1, 10.3_
  - [x] 20.2 Create Reports/Revenue.tsx page
    - Grouping selector
    - Date range picker
    - Summary cards
    - Chart visualization
    - Export buttons
    - _Requirements: 10.1, 10.2, 10.3, 10.4, 10.5_
  - [x] 20.3 Write property test for revenue calculations
    - **Property: Revenue totals match sum of grouped values**
    - **Validates: Requirements 10.2**

- [x] 21. Implement credit patients list
  - [x] 21.1 Add credit-patients endpoint to AccountsController
    - List patients with credit tags
    - Show total owing per patient
    - _Requirements: 14.6_
  - [x] 21.2 Create CreditPatients/Index.tsx page
    - Table of credit patients
    - Total owing column
    - Manage credit button
    - _Requirements: 14.6_

- [x] 22. Checkpoint - Ensure all tests pass
  - Ensure all tests pass, ask the user if questions arise.

## Phase 7: UI Polish & Integration

- [x] 23. Enhance billing page UI
  - [x] 23.1 Update Billing/Payments/Index.tsx with new components
    - Add MyCollectionsCard
    - Integrate ChargeSelectionList
    - Add ChangeCalculator for cash
    - Add receipt print button
    - Add override button (if permission)
    - Show credit badge for eligible patients
    - _Requirements: 11.1-11.9_
  - [x] 23.2 Create PaymentModal component
    - Focused payment workflow
    - Show selected charges summary
    - Payment method selection
    - Change calculator for cash
    - Success state with print option
    - _Requirements: 11.7_
  - [x] 23.3 Add navigation for accounts section
    - Show only if user has relevant permissions
    - _Requirements: 12.5_

- [x] 24. Add routes and navigation
  - [x] 24.1 Add accounts routes to billing.php
    - All new routes with permission middleware
    - _Requirements: 12.1-12.4_
  - [x] 24.2 Update sidebar navigation
    - Add Accounts submenu under Billing
    - Permission-based visibility
    - _Requirements: 12.5_

- [x] 25. Final Checkpoint - Ensure all tests pass
  - Ensure all tests pass, ask the user if questions arise.
