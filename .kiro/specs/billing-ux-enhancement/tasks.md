# Implementation Plan

- [x] 1. Set up database schema and permissions






- [x] 1.1 Create migration for service_access_overrides table

  - Add all required columns (patient_checkin_id, service_type, service_code, reason, authorized_by, authorized_at, expires_at, is_active)
  - Add foreign key constraints
  - Add indexes for performance (checkin_service, expires_active)
  - _Requirements: 7.3, 7.4, 10.3_

- [x] 1.2 Create migration for bill_adjustments table


  - Add all required columns (charge_id, adjustment_type, original_amount, adjustment_amount, final_amount, reason, adjusted_by, adjusted_at)
  - Add foreign key constraints
  - Add indexes for performance (charge_id, adjusted_by)
  - _Requirements: 3.3, 3.5_

- [x] 1.3 Create migration to enhance charges table


  - Add is_waived, waived_by, waived_at, waived_reason columns
  - Add adjustment_amount, original_amount columns
  - Add indexes for new columns
  - _Requirements: 3.1, 3.3, 8.2_

- [x] 1.4 Create permission seeder for new billing permissions


  - Add billing.waive-charges permission
  - Add billing.adjust-charges permission
  - Add billing.emergency-override permission
  - Add billing.cancel-charges permission
  - Add billing.view-audit-trail permission
  - Assign all permissions to admin role by default
  - _Requirements: 10.1, 10.2, 10.3, 10.4, 10.5_

- [x] 2. Create backend models and relationships






- [x] 2.1 Create ServiceAccessOverride model

  - Define fillable fields and casts
  - Add patientCheckin() and authorizedBy() relationships
  - Implement isExpired() and getRemainingDuration() methods
  - Add active() and forService() query scopes
  - _Requirements: 7.3, 7.4, 7.5_


- [x] 2.2 Create BillAdjustment model

  - Define fillable fields and casts
  - Add charge() and adjustedBy() relationships
  - Implement getAdjustmentPercentage() and isWaiver() methods
  - Add forCharge() and byUser() query scopes
  - _Requirements: 3.3, 3.5_


- [x] 2.3 Enhance Charge model with adjustment methods

  - Add adjustments() relationship
  - Implement isWaived(), hasAdjustment(), getEffectiveAmount() methods
  - Add waived() and adjusted() query scopes
  - _Requirements: 3.1, 3.5, 8.2_




- [x] 3. Implement backend services


- [x] 3.1 Create BillAdjustmentService


  - Implement calculateAdjustedAmount() for percentage and fixed discounts
  - Implement validateAdjustment() to check if adjustment is valid
  - Add helper methods for adjustment calculations
  - _Requirements: 3.4, 3.5_

- [x] 3.2 Create OverrideAuditService


  - Implement methods to log override activations
  - Implement methods to retrieve override history
  - Add methods to check active overrides
  - _Requirements: 4.1, 4.2, 4.3_

- [x] 3.3 Enhance BillingService with override checking


  - Update canProceedWithService() to check for active overrides
  - Add method to get active overrides for a patient
  - Update service blocking logic to respect overrides
  - _Requirements: 7.3, 7.4, 7.5_



- [x] 4. Create backend controllers



- [x] 4.1 Create BillAdjustmentController


  - Implement waive() method with authorization and validation
  - Implement adjust() method for percentage and fixed discounts
  - Add database transaction handling
  - Add audit logging for all adjustments
  - _Requirements: 3.1, 3.2, 3.3, 3.4, 3.5_


- [x] 4.2 Create ServiceOverrideController

  - Implement activate() method with authorization and validation
  - Implement deactivate() method for early termination
  - Implement index() method to get active overrides
  - Add audit logging for all override operations
  - _Requirements: 7.1, 7.2, 7.3, 7.4, 7.5_


- [x] 4.3 Enhance PaymentController with quick pay

  - Implement quickPayAll() method for one-click payment
  - Update processPayment() to handle inline form submission
  - Enhance getBillingStatus() to include override information
  - Update show() method to pass override data to frontend
  - _Requirements: 2.1, 2.2, 2.3, 2.4, 2.5_

- [x] 4.4 Update PatientController with billing summary


  - Add getBillingSummary() private method
  - Update show() method to include billing summary data
  - Add permission check for billing summary visibility
  - _Requirements: 11.1, 11.2, 11.3, 11.4, 11.5_

- [x] 5. Implement authorization policies





- [x] 5.1 Enhance BillingPolicy with new methods


  - Implement waive() method checking billing.waive-charges permission
  - Implement adjust() method checking billing.adjust-charges permission
  - Implement overrideService() method checking billing.emergency-override permission
  - Implement cancel() method checking billing.cancel-charges permission
  - Add status checks to prevent operations on non-pending charges
  - _Requirements: 10.1, 10.2, 10.3, 10.4_





- [x] 6. Add web routes (Inertia-compatible)


- [x] 6.1 Add bill adjustment routes to routes/billing.php

  - POST /billing/charges/{charge}/waive - returns redirect with success message
  - POST /billing/charges/{charge}/adjust - returns redirect with success message
  - Add middleware for authentication and policy authorization
  - Routes return Inertia redirects, not JSON responses
  - _Requirements: 3.1, 3.2, 3.4_


- [x] 6.2 Add service override routes to routes/billing.php

  - POST /billing/checkin/{checkin}/override - returns redirect with success message
  - POST /billing/overrides/{override}/deactivate - returns redirect with success message
  - GET /billing/checkin/{checkin}/overrides - returns JSON for real-time status checks (exception case)
  - Add middleware for authentication and policy authorization
  - Most routes return Inertia redirects, not JSON responses
  - _Requirements: 7.1, 7.2, 7.3_


- [x] 6.3 Add quick pay routes to routes/billing.php

  - POST /billing/charges/quick-pay-all - returns redirect with success message
  - Enhance existing POST /billing/charges/{charge}/quick-pay if needed
  - Add middleware for authentication and policy authorization
  - Routes return Inertia redirects, not JSON responses
  - _Requirements: 2.1, 2.2_


- [x] 6.4 Update patient search route (exception: returns JSON)

  - GET /billing/patients/search - returns JSON for autocomplete functionality
  - This is the only route that returns JSON (for real-time search)
  - All other routes use Inertia redirects
  - _Requirements: 1.2_

- [x] 7. Create frontend components














- [x] 7.1 Create PatientSearchBar component


  - Implement debounced search input
  - Add loading indicator
  - Style with Tailwind CSS
  - _Requirements: 1.2_

- [x] 7.2 Create PatientSearchResults component


  - Display search results with patient info
  - Show pending charges summary
  - Add Quick Pay All button
  - Add expand/collapse functionality
  - _Requirements: 1.2, 2.1_


- [x] 7.3 Create PatientBillingDetails component






  - Display patient information card
  - Show service access status with visual indicators
  - Group charges by visit
  - Display insurance breakdown
  - Show override history section
  - _Requirements: 1.3, 5.1, 5.2, 7.1_

- [x] 7.4 Create ChargesList component




  - Display charges with checkboxes for selection
  - Show insurance coverage breakdown per charge
  - Add Quick Pay, Waive, Adjust buttons per charge (permission-based)
  - Implement real-time total calculation
  - _Requirements: 5.1, 5.2, 5.3, 9.1, 9.2_

- [x] 7.5 Create InlinePaymentForm component


  - Add payment method selector
  - Add amount input with validation
  - Add notes textarea
  - Implement form submission with Inertia
  - Show processing state
  - _Requirements: 1.4, 6.1, 6.2_

- [x] 7.6 Create QuickPayButton component


  - Implement one-click payment with confirmation
  - Show minimal modal for payment method selection
  - Handle optimistic UI updates
  - Display success/error messages
  - _Requirements: 2.1, 2.2, 2.3, 2.4_


- [x] 7.7 Create BillWaiverModal component

  - Add reason textarea with validation (min 10 chars)
  - Show original amount and impact
  - Add confirmation checkbox
  - Implement form submission
  - _Requirements: 3.1, 3.2, 3.3_

- [x] 7.8 Create BillAdjustmentModal component




  - Add adjustment type selector (percentage/fixed)
  - Add discount value input
  - Add reason textarea with validation
  - Show preview of new amount
  - Implement form submission
  - _Requirements: 3.4, 3.5_

- [x] 7.9 Create ServiceAccessOverrideModal component


  - Add service type display (pre-selected)
  - Add reason textarea with validation (min 20 chars)
  - Show pending charges causing block
  - Display expiry information
  - Implement form submission
  - _Requirements: 7.1, 7.2, 7.3_

- [x] 7.10 Create OverrideHistorySection component


  - Display all overrides and adjustments chronologically
  - Show active overrides with countdown timer
  - Display authorizing user and reason
  - Add visual styling for different override types
  - _Requirements: 4.1, 4.2, 4.3, 4.5_

- [x] 7.11 Create BillingStatsCards component


  - Display pending charges count and amount
  - Display today's revenue
  - Display total outstanding
  - Display collection rate
  - _Requirements: 1.1_

- [x] 8. Refactor billing index page





- [x] 8.1 Merge Index.tsx and Show.tsx into unified interface


  - Combine search and details into single page
  - Implement expandable patient details
  - Add inline payment form
  - Remove navigation to separate show page
  - _Requirements: 1.1, 1.2, 1.3, 1.4_

- [x] 8.2 Implement state management for unified page

  - Add state for search query and results
  - Add state for selected/expanded patients
  - Add state for processing indicators
  - Implement optimistic UI updates
  - _Requirements: 1.2, 1.3, 1.4_

- [x] 8.3 Add permission-based UI rendering


  - Show/hide waive buttons based on billing.waive-charges
  - Show/hide adjust buttons based on billing.adjust-charges
  - Show/hide override buttons based on billing.emergency-override
  - Show/hide cancel buttons based on billing.cancel-charges
  - _Requirements: 10.1, 10.2, 10.3, 10.4_



- [x] 9. Add billing summary to patient profile


- [x] 9.1 Create BillingSummary component


  - Display total outstanding balance
  - Show insurance covered amount
  - Show patient copay amount
  - Display recent payment history
  - Add Process Payment button (permission-based)
  - _Requirements: 11.1, 11.2, 11.3, 11.4, 11.5_

- [x] 9.2 Integrate BillingSummary into patient profile page


  - Add billing summary section to Patients/Show.tsx
  - Pass billing data from backend
  - Handle permission checks for display
  - _Requirements: 11.1, 11.3, 11.4_

- [x] 10. Update service pages with block messages





- [x] 10.1 Update laboratory pages with service block display


  - Add service blocked alert to Lab/Show.tsx
  - Display pending charges and amounts
  - Show "direct to billing desk" message
  - Display active override status if present
  - Remove any existing override buttons from lab pages
  - _Requirements: 12.1, 12.2, 12.3, 12.4, 12.5_

- [x] 10.2 Update pharmacy pages with service block display


  - Add service blocked alert to Pharmacy/Dispensing pages
  - Display pending charges and amounts
  - Show "direct to billing desk" message
  - Display active override status if present
  - Remove any existing override buttons from pharmacy pages
  - _Requirements: 12.1, 12.2, 12.3, 12.4, 12.5_

- [x] 10.3 Update consultation pages with service block display


  - Add service blocked banner to Consultation/Show.tsx
  - Display pending charges and amounts
  - Show "direct to billing desk" message
  - Display active override status if present
  - Remove any existing override buttons from consultation pages
  - _Requirements: 12.1, 12.2, 12.3, 12.4, 12.5_

- [x] 10.4 Update ward pages with service block display





  - Add service blocked alert to Ward pages
  - Display pending charges and amounts
  - Show "direct to billing desk" message
  - Display active override status if present
  - Remove any existing override buttons from ward pages
  - _Requirements: 12.1, 12.2, 12.3, 12.4, 12.5_

- [ ] 11. Write backend tests





- [ ]* 11.1 Write unit tests for BillAdjustmentService
  - Test calculateAdjustedAmount() for percentage discounts
  - Test calculateAdjustedAmount() for fixed discounts
  - Test edge cases (zero amount, negative values)
  - _Requirements: 3.4, 3.5_

- [ ]* 11.2 Write unit tests for models
  - Test ServiceAccessOverride isExpired() and getRemainingDuration()
  - Test BillAdjustment getAdjustmentPercentage()
  - Test Charge isWaived() and getEffectiveAmount()
  - _Requirements: 3.1, 3.5, 7.4_

- [ ]* 11.3 Write feature tests for bill waiver
  - Test authorized user can waive charges
  - Test unauthorized user cannot waive charges
  - Test waiver creates audit record
  - Test cannot waive already paid charges
  - _Requirements: 3.1, 3.2, 3.3, 10.1_

- [ ]* 11.4 Write feature tests for bill adjustment
  - Test authorized user can apply percentage discount
  - Test authorized user can apply fixed discount
  - Test unauthorized user cannot adjust charges
  - Test adjustment creates audit record
  - Test cannot adjust already paid charges
  - _Requirements: 3.4, 3.5, 10.2_

- [ ]* 11.5 Write feature tests for service override
  - Test authorized user can activate override
  - Test override expires after configured duration
  - Test override allows blocked service to proceed
  - Test unauthorized user cannot activate override
  - Test override creates audit record
  - _Requirements: 7.1, 7.2, 7.3, 7.4, 7.5, 10.3_

- [ ]* 11.6 Write feature tests for quick pay
  - Test billing clerk can process quick payment
  - Test quick pay updates charge status
  - Test quick pay handles insurance copay correctly
  - Test quick pay creates payment log
  - _Requirements: 2.1, 2.2, 2.3, 2.4, 2.5_

- [ ]* 11.7 Write feature tests for partial payments
  - Test billing clerk can process partial payment
  - Test partial payment distributes proportionally
  - Test partial payment updates charge status to partial
  - Test multiple partial payments are tracked
  - _Requirements: 6.1, 6.2, 6.3, 6.5_

- [ ]* 11.8 Write browser tests for complete workflows
  - Test full payment workflow on single page
  - Test quick pay all functionality
  - Test bill waiver workflow
  - Test service override workflow
  - _Requirements: 1.1, 1.2, 1.3, 1.4, 2.1, 3.1, 7.1_

- [ ] 12. Run migrations and deploy
- [ ] 12.1 Run migrations in development environment
  - Test all migrations run successfully
  - Verify indexes are created
  - Check foreign key constraints
  - _Requirements: All_

- [ ] 12.2 Seed permissions and test authorization
  - Run permission seeder
  - Verify admin has all permissions
  - Test permission checks in policies
  - _Requirements: 10.1, 10.2, 10.3, 10.4, 10.5_

- [ ] 12.3 Test complete feature in development
  - Test all user flows
  - Verify permission enforcement
  - Check audit trail logging
  - Test service blocking and overrides
  - _Requirements: All_

- [ ] 12.4 Deploy to production
  - Run migrations
  - Deploy backend code
  - Deploy frontend code
  - Monitor for errors
  - _Requirements: All_
