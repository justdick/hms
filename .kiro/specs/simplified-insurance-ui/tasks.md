# Implementation Plan

- [x]   1. Database and backend foundation
    - Add `require_explicit_approval_for_new_items` column to insurance_plans table
    - Create CoveragePresetService with preset definitions
    - Add quickUpdate method to InsuranceCoverageRuleController for inline edits
    - _Requirements: 1.1, 5.1, 3.1_

- [x]   2. Coverage preset system

- [x] 2.1 Implement CoveragePresetService
    - Create service class with getPresets() method
    - Define NHIS Standard, Corporate Premium, Basic, and Custom presets
    - Add API endpoint to fetch presets
    - _Requirements: 5.1, 5.2, 5.3_

- [x] 2.2 Create CoveragePresetSelector component
    - Build preset card UI with preview
    - Implement preset selection logic
    - Handle custom preset option
    - _Requirements: 5.1, 5.2, 5.3_

- [x]   3. Plan setup wizard

- [x] 3.1 Create PlanSetupWizard component
    - Build 3-step wizard UI (Plan Details, Coverage, Review)
    - Implement step navigation
    - Add form validation
    - _Requirements: 1.1, 1.2, 1.3_

- [x] 3.2 Integrate preset selector in wizard
    - Add preset selection in step 2
    - Pre-fill coverage inputs from selected preset
    - Allow modification of preset values
    - _Requirements: 1.2, 1.4, 5.3_

- [x] 3.3 Implement wizard submission
    - Create plan and all default rules in transaction
    - Handle success/error states
    - Redirect to coverage dashboard
    - _Requirements: 1.5_

- [x]   4. Coverage dashboard

- [x] 4.1 Create CoverageDashboard component
    - Build category card grid layout
    - Implement color coding logic (green/yellow/red/gray)
    - Add category icons
    - Display coverage percentages and exception counts
    - _Requirements: 2.1, 2.2, 2.5_

- [x] 4.2 Implement category card interactions
    - Add click to expand functionality
    - Show default rule and exceptions on expand
    - Add hover tooltips with summary
    - _Requirements: 2.3, 2.5_

- [x] 4.3 Create backend endpoint for dashboard data
    - Fetch plan with coverage rules grouped by category
    - Calculate exception counts
    - Return formatted data for dashboard
    - _Requirements: 2.1, 2.2_

- [x]   5. Inline editing

- [x] 5.1 Create InlinePercentageEdit component
    - Build inline edit UI (click to edit)
    - Implement input validation (0-100 range)
    - Add success/error animations
    - Handle optimistic updates with rollback
    - _Requirements: 3.1, 3.2, 3.3, 3.4, 3.5_

- [x] 5.2 Add quickUpdate backend endpoint
    - Create route and controller method
    - Validate percentage input
    - Update single field
    - Return updated rule
    - _Requirements: 3.1, 3.2, 3.5_

- [ ]   6. Simplified exception management

- [x] 6.1 Create AddExceptionModal component
    - Build simplified 3-field modal UI
    - Implement item search with results display
    - Add coverage type selector (Percentage/Fixed/Full/None)
    - Show coverage preview with calculations
    - _Requirements: 4.1, 4.2, 4.3, 4.5, 7.1, 7.2, 7.3, 7.4, 7.5_

- [x] 6.2 Implement exception list display
    - Show exceptions in category detail view
    - Display comparison with default rule
    - Add edit and delete actions
    - Implement search and filters
    - _Requirements: 8.1, 8.2, 8.3, 8.4, 8.5_

- [x] 6.3 Add duplicate exception prevention
    - Check for existing exceptions before save
    - Show clear error message
    - Indicate existing exceptions in search results
    - _Requirements: 4.4, 12.3_

- [x]   7. Recent items monitoring

- [x] 7.1 Create RecentItemsPanel component
    - Build recent items list UI
    - Highlight expensive items with warning icon
    - Show coverage status for each item
    - Add "Add Exception" quick action
    - _Requirements: 15.1, 15.2, 15.3, 15.4_

- [x] 7.2 Implement getRecentItems backend method
    - Query items created in last 30 days across all categories
    - Calculate coverage status (default/exception/not_covered)
    - Flag expensive items above threshold
    - Return formatted data
    - _Requirements: 15.1, 15.2_

- [x]   8. Notification system for new items

- [x] 8.1 Create item observers
    - Register observers for Drug, LabService, and other models
    - Detect when new items are created
    - Find plans with default coverage for that category
    - _Requirements: 14.1, 14.2_

- [x] 8.2 Implement NewItemAddedNotification
    - Create notification class
    - Include item details and default coverage info
    - Add quick actions (Add Exception / Keep Default)
    - Send to insurance administrators
    - _Requirements: 14.2, 14.3_

- [x] 8.3 Add explicit approval setting
    - Implement UI toggle for "Require explicit approval for new items"
    - Update notification logic to respect this setting
    - Handle items when approval is required
    - _Requirements: 14.4, 14.5_

- [x]   9. Bulk import functionality

- [x] 9.1 Create Excel template generator
    - Build template with headers and example rows
    - Add instructions sheet
    - Implement downloadTemplate endpoint
    - _Requirements: 6.1, 6.2_

- [x] 9.2 Create BulkImportModal component
    - Build upload interface with drag & drop
    - Implement preview table with validation
    - Show errors highlighted in red
    - Display import summary
    - _Requirements: 6.3, 6.4, 6.5_

- [x] 9.3 Implement import validation and processing
    - Create preview endpoint to validate rows
    - Check item existence
    - Validate coverage percentages
    - Create import endpoint to save valid exceptions
    - _Requirements: 6.1, 6.3, 6.4, 6.5, 6.6_

- [x]   10. Contextual help and validation

- [x] 10.1 Add tooltips and help text
    - Implement tooltip component
    - Add helpful messages throughout UI
    - Include examples where appropriate
    - _Requirements: 9.1, 9.2, 9.3_

- [x] 10.2 Implement validation and warnings
    - Add client-side validation for all inputs
    - Show warnings for unusual configurations
    - Display clear error messages
    - _Requirements: 9.4, 12.1, 12.2, 12.4, 12.5_

- [x] 10.3 Add success feedback
    - Show success messages after actions
    - Include next steps in success messages
    - Implement animations for feedback
    - _Requirements: 9.5_

- [x]   11. Responsive design and accessibility

- [x] 11.1 Implement responsive layouts
    - Make dashboard cards stack on mobile
    - Adapt modals for smaller screens
    - Use card layout for mobile exception lists
    - _Requirements: 10.1, 10.2, 10.3, 10.4, 10.5_

- [x] 11.2 Add accessibility features
    - Ensure keyboard navigation works
    - Add ARIA labels for screen readers
    - Implement visible focus indicators
    - Make error messages accessible
    - _Requirements: Accessibility section_

- [x]   12. Audit trail and history

- [x] 12.1 Implement change history tracking
    - Log all coverage rule changes
    - Record user, timestamp, old and new values
    - Create history view component
    - _Requirements: 11.1, 11.2, 11.3_

- [x] 12.2 Add history export
    - Implement export functionality
    - Include change history in exports
    - _Requirements: 11.4, 11.5_

- [x]   13. Quick actions and shortcuts

- [x] 13.1 Create QuickActionsMenu component
    - Build quick actions dropdown
    - Add common tasks (Add Exception, Copy from Plan, etc.)
    - _Requirements: 13.1, 13.4_

- [x] 13.2 Implement keyboard shortcuts
    - Add "N" for new exception
    - Add "E" for edit mode
    - Show shortcut hints in tooltips
    - _Requirements: 13.2, 13.3, 13.5_

- [x]   14. Testing and quality assurance

- [x] 14.1 Write unit tests
    - Test CoveragePresetService
    - Test color calculation logic
    - Test validation functions
    - _Requirements: All_

- [x] 14.2 Write feature tests
    - Test plan setup wizard flow
    - Test coverage dashboard display
    - Test inline editing
    - Test exception management
    - Test bulk import
    - _Requirements: All_

- [x] 14.3 Write browser tests
    - Test complete user workflows
    - Test responsive behavior
    - Test accessibility
    - Verify no JavaScript errors
    - _Requirements: All_

- [-] 15. Documentation and deployment

- [x] 15.1 Create user documentation
    - Write setup guide
    - Document preset usage
    - Explain exception management
    - Create bulk import guide
    - _Requirements: Documentation section_

- [x] 15.2 Implement feature flag
    - Add feature flag for new UI
    - Keep old UI accessible during rollout
    - Create toggle for switching between UIs
    - _Requirements: 16.1, 16.2, Migration Strategy_

- [x] 15.3 Deploy and monitor
    - Deploy to staging
    - Test with pilot users
    - Gather feedback
    - Deploy to production
    - Monitor for issues
    - _Requirements: Migration Strategy_
