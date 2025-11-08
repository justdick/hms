# Implementation Plan

- [x] 1. Create Overview Tab summary card components









  - Create reusable summary card components that display key patient information
  - Each card should be clickable and navigate to the detailed tab
  - Include visual indicators for alerts and status
  - _Requirements: 1.1, 1.2, 1.3, 1.4, 1.5, 3.1, 3.2, 3.3, 3.4, 3.5, 4.5_

- [x] 1.1 Create DiagnosisSummaryCard component



  - Implement card showing most recent diagnosis from consultation or ward rounds
  - Display diagnosis name, ICD code, and diagnosing physician
  - Show "No diagnosis recorded" state when no diagnosis exists
  - Make card clickable to navigate to Ward Rounds tab
  - _Requirements: 1.2, 4.1_

- [x] 1.2 Create PrescriptionsSummaryCard component



  - Implement card displaying active prescriptions (up to 5 most recent)
  - Show medication name, dosage, and frequency for each prescription
  - Display total count of active prescriptions
  - Add badge showing pending medication count
  - Make card clickable to navigate to Medication Administration tab
  - _Requirements: 1.3, 3.2, 4.2_


- [x] 1.3 Create VitalsSummaryCard component


  - Implement card showing most recent vital signs with values
  - Display time since last recording
  - Add visual indicator for overdue vitals (>4 hours)
  - Show vitals schedule status if configured
  - Make card clickable to navigate to Vital Signs tab
  - _Requirements: 1.4, 3.1, 4.3_


- [x] 1.4 Create LabsSummaryCard component


  - Implement card showing count of pending, in-progress, and completed labs
  - Display most recent lab result if available
  - Highlight urgent lab orders
  - Show "No labs ordered" state when no labs exist
  - Make card clickable to navigate to Labs tab
  - _Requirements: 1.5, 3.3, 4.4_
-

- [ ] 2. Create OverviewTab component






  - Implement main Overview tab component that aggregates all summary cards
  - Use grid layout (2x2 on desktop, stacked on mobile)
  - Implement navigation handler to switch between tabs
  - Add computed values for latest diagnosis and admission day number
  - _Requirements: 1.1, 1.2, 1.3, 1.4, 1.5, 3.5_
- [x] 3. Create Labs Tab components




- [ ] 3. Create Labs Tab components

  - Create dedicated tab for viewing laboratory orders and results
  - Implement conditional rendering based on presence of lab orders
  - Add filtering and sorting capabilities
  - Display results with visual indicators for abnormal values
  - _Requirements: 2.1, 2.2, 2.3, 2.4, 2.5, 5.1, 5.2, 5.3, 5.4, 5.5_

- [x] 3.1 Create LabOrdersTable component


  - Implement table displaying all lab orders with columns: Test Name, Status, Priority, Ordered Date, Ordered By
  - Add status filter tabs (All, Pending, In Progress, Completed)
  - Implement sortable columns
  - Create expandable rows for detailed information
  - Add color-coded status badges
  - _Requirements: 2.3, 2.5, 5.1, 5.3, 5.4, 5.5_

- [x] 3.2 Create LabResultsDisplay component


  - Implement formatted display of lab result values
  - Show reference ranges when available
  - Add visual indicators for abnormal values (high/low)
  - Display result notes and interpretation
  - _Requirements: 2.4, 5.1, 5.2_

- [x] 3.3 Create LabsTab component


  - Implement main Labs tab component integrating LabOrdersTable and LabResultsDisplay
  - Add sub-navigation for status filtering
  - Implement computed values for grouping lab orders by status
  - Handle empty state when no labs exist
  - _Requirements: 2.1, 2.2, 2.3, 2.4, 2.5_

- [ ] 4. Integrate new tabs into WardPatientShow page





  - Add Overview and Labs tabs to the existing tab structure
  - Update tab navigation to include new tabs
  - Implement conditional rendering for Labs tab
  - Set Overview as the default tab
  - Wire up navigation between tabs
  - _Requirements: 1.1, 2.1, 2.2, 4.1, 4.2, 4.3, 4.4_

- [x] 4.1 Update WardPatientShow component with new tabs


  - Import new OverviewTab and LabsTab components
  - Add state management for active tab
  - Implement tab navigation handler
  - Add computed values for lab orders and latest diagnosis
  - Reorder tabs: Overview, Vitals, Medications, History, Labs (conditional), Notes, Rounds
  - Set defaultValue="overview" for Tabs component
  - _Requirements: 1.1, 2.1, 2.2_


- [x] 4.2 Implement conditional Labs tab rendering

  - Add logic to check if lab orders exist
  - Conditionally render Labs TabsTrigger based on lab orders presence
  - Conditionally render Labs TabsContent based on lab orders presence
  - _Requirements: 2.1, 2.2_

- [x] 4.3 Wire up navigation from overview cards to detailed tabs


  - Implement onNavigateToTab handler in WardPatientShow
  - Pass handler to OverviewTab component
  - Connect click handlers on summary cards to tab navigation
  - Test navigation flow from each summary card
  - _Requirements: 4.1, 4.2, 4.3, 4.4, 4.5_

- [x] 5. Add tests for new components




  - Write feature tests for tab navigation and data display
  - Write browser tests for responsive layout and interactions
  - _Requirements: All_

- [x] 5.1 Write feature tests for Overview tab



  - Test OverviewTab renders with various data states
  - Test navigation from summary cards to detailed tabs
  - Test visual indicators for alerts
  - Test default tab is Overview on page load
  - _Requirements: 1.1, 1.2, 1.3, 1.4, 1.5, 3.1, 3.2, 3.3, 4.1, 4.2, 4.3, 4.4_

- [x] 5.2 Write feature tests for Labs tab


  - Test Labs tab visibility based on lab orders presence
  - Test lab order status filtering
  - Test lab results display with normal and abnormal values
  - Test expandable lab order rows
  - _Requirements: 2.1, 2.2, 2.3, 2.4, 2.5, 5.1, 5.2, 5.3, 5.4, 5.5_

- [x] 5.3 Write browser tests for responsive layout


  - Test responsive layout on mobile, tablet, and desktop viewports
  - Test tab navigation and state persistence
  - Test clickable cards and navigation flow
  - Test dark mode compatibility
  - _Requirements: All_
