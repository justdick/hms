# Role-Based Dashboard - Implementation Tasks

## Phase 1: Foundation

- [x] 1. Set up Dashboard Service Infrastructure






  - [x] 1.1 Create DashboardService orchestrator

    - Create `app/Services/Dashboard/DashboardService.php`
    - Implement `getVisibleWidgets(User $user): array` method that checks permissions
    - Implement `getMetrics()` that only calculates metrics for visible widgets
    - Implement `getQuickActions()` filtered by user permissions
    - _Requirements: FR-1, FR-1.1, FR-4_

  - [x] 1.2 Create base widget interface

    - Define contract for dashboard widget services
    - Each widget service returns metrics and list data
    - _Requirements: NFR-3_

- [x] 2. Create Base Dashboard Components





  - [x] 2.1 Create MetricCard component


    - Create `resources/js/components/Dashboard/MetricCard.tsx`
    - Props: title, value, icon, color variant, optional href
    - Support color variants: default, success, warning, danger
    - _Requirements: NFR-2_
  - [x] 2.2 Create QuickActions component


    - Create `resources/js/components/Dashboard/QuickActions.tsx`
    - Render grid of action buttons with icons
    - Actions pre-filtered server-side by permission
    - _Requirements: FR-4_
  - [x] 2.3 Create DashboardLayout component


    - Create `resources/js/components/Dashboard/DashboardLayout.tsx`
    - Common wrapper with consistent spacing
    - Grid layout for metrics and content areas
    - _Requirements: NFR-2_

- [x] 3. Update DashboardController





  - [x] 3.1 Inject DashboardService and return role-based data


    - Update `app/Http/Controllers/DashboardController.php`
    - Pass visibleWidgets, metrics, lists, quickActions to frontend
    - Use Inertia deferred props for heavy list data
    - _Requirements: FR-1, FR-6_

---

## Phase 2: Role-Specific Dashboards

- [x] 4. Implement Receptionist Dashboard






  - [x] 4.1 Create ReceptionistDashboard service

    - Create `app/Services/Dashboard/ReceptionistDashboard.php`
    - Metrics: today's check-ins, waiting patients, awaiting consultation
    - List: recent check-ins (last 10)
    - Required permission: `checkins.view-dept` or `checkins.view-all`
    - _Requirements: Receptionist Dashboard section_

  - [x] 4.2 Create receptionist widget components

    - Create `resources/js/components/Dashboard/widgets/CheckinMetrics.tsx`
    - Create `resources/js/components/Dashboard/widgets/RecentCheckins.tsx`
    - _Requirements: Receptionist Dashboard section_

- [x] 5. Implement Doctor Dashboard






  - [x] 5.1 Create DoctorDashboard service

    - Create `app/Services/Dashboard/DoctorDashboard.php`
    - Metrics: consultation queue, active consultations, pending lab results
    - List: consultation queue, lab results awaiting review
    - Filter by doctor's assigned departments
    - Required permissions: `consultations.view-dept`, `lab-orders.view-dept`
    - _Requirements: Doctor Dashboard section_


  - [x] 5.2 Create doctor widget components
    - Create `resources/js/components/Dashboard/widgets/ConsultationQueue.tsx`
    - Create `resources/js/components/Dashboard/widgets/PendingLabResults.tsx`
    - _Requirements: Doctor Dashboard section_

- [x] 6. Implement Pharmacist Dashboard





  - [x] 6.1 Create PharmacistDashboard service


    - Create `app/Services/Dashboard/PharmacistDashboard.php`
    - Metrics: pending prescriptions, ready for dispensing, low stock count
    - Lists: prescription queue, low stock items
    - Required permissions: `dispensing.view`, `inventory.view`
    - _Requirements: Pharmacist Dashboard section_

  - [x] 6.2 Create pharmacist widget components

    - Create `resources/js/components/Dashboard/widgets/PrescriptionQueue.tsx`
    - Create `resources/js/components/Dashboard/widgets/LowStockAlerts.tsx`
    - _Requirements: Pharmacist Dashboard section_

- [x] 7. Implement Nurse Dashboard






  - [x] 7.1 Create NurseDashboard service

    - Create `app/Services/Dashboard/NurseDashboard.php`
    - Metrics: awaiting vitals, pending medications, active admissions
    - Lists: vitals queue, medication schedule (next 2 hours)
    - Filter by assigned departments/wards
    - Required permissions: `vitals.view-dept`, `view medication administrations`
    - _Requirements: Nurse Dashboard section_

  - [x] 7.2 Create nurse widget components

    - Create `resources/js/components/Dashboard/widgets/VitalsQueue.tsx`
    - Create `resources/js/components/Dashboard/widgets/MedicationSchedule.tsx`
    - _Requirements: Nurse Dashboard section_

- [x] 8. Implement Cashier Dashboard





  - [x] 8.1 Create CashierDashboard service


    - Create `app/Services/Dashboard/CashierDashboard.php`
    - Metrics: pending payments, today's collections (own), transactions today
    - List: recent payments
    - Required permission: `billing.collect`
    - _Requirements: Cashier Dashboard section_
  - [x] 8.2 Create cashier widget components


    - Create `resources/js/components/Dashboard/widgets/CashierMetrics.tsx`
    - Create `resources/js/components/Dashboard/widgets/RecentPayments.tsx`
    - _Requirements: Cashier Dashboard section_

- [x] 9. Implement Finance Officer Dashboard





  - [x] 9.1 Create FinanceDashboard service


    - Create `app/Services/Dashboard/FinanceDashboard.php`
    - Metrics: today's revenue, outstanding receivables, pending insurance claims
    - List: revenue by payment method breakdown
    - Required permissions: `billing.reports`, `billing.reconcile`
    - _Requirements: Finance Officer Dashboard section_
  - [x] 9.2 Create finance widget components


    - Create `resources/js/components/Dashboard/widgets/FinanceMetrics.tsx`
    - Create `resources/js/components/Dashboard/widgets/RevenueSummary.tsx`
    - _Requirements: Finance Officer Dashboard section_

- [x] 10. Implement Insurance Officer Dashboard

  - [x] 10.1 Create InsuranceDashboard service
    - Create `app/Services/Dashboard/InsuranceDashboard.php`
    - Metrics: claims pending vetting, claims submitted, claims approved this month
    - Lists: claims vetting queue, recent batch submissions
    - Required permissions: `insurance.view`, `insurance.vet-claims`

    - _Requirements: Insurance Officer Dashboard section_

  - [x] 10.2 Create insurance widget components

    - Create `resources/js/components/Dashboard/widgets/ClaimsMetrics.tsx`
    - Create `resources/js/components/Dashboard/widgets/ClaimsQueue.tsx`
    - _Requirements: Insurance Officer Dashboard section_

- [x] 11. Implement Admin Dashboard





  - [x] 11.1 Create AdminDashboard service


    - Create `app/Services/Dashboard/AdminDashboard.php`
    - Metrics: total patients today, total revenue, active users
    - List: department activity summary
    - Required permission: `system.admin`
    - _Requirements: Admin Dashboard section_
  - [x] 11.2 Create admin widget components


    - Create `resources/js/components/Dashboard/widgets/AdminMetrics.tsx`
    - Create `resources/js/components/Dashboard/widgets/SystemOverview.tsx`
    - _Requirements: Admin Dashboard section_

---

## Phase 3: Main Dashboard Page

- [x] 12. Create Dashboard Index Page






  - [x] 12.1 Create main dashboard page


    - Create `resources/js/pages/Dashboard/Index.tsx`
    - Receive visibleWidgets, metrics, lists, quickActions from controller
    - Conditionally render widgets based on visibleWidgets array
    - Handle loading states for deferred data
    - _Requirements: FR-1, FR-5_

  - [x] 12.2 Implement polling for real-time updates

    - Poll metrics every 30 seconds using Inertia reload
    - Show subtle refresh indicator
    - Handle network errors gracefully
    - _Requirements: FR-2_

---

## Phase 4: Polish & Optimization

- [x] 13. Add Caching






  - [x] 13.1 Implement caching in dashboard services

    - Cache per-user metrics with 60 second TTL
    - Cache system-wide aggregates with 5 minute TTL
    - Graceful fallback if cache fails
    - _Requirements: FR-6_

- [x] 14. Performance Optimization






  - [x] 14.1 Optimize database queries

    - Add missing indexes for dashboard queries
    - Use eager loading to prevent N+1
    - Combine related queries where possible
    - Target: dashboard loads in under 2 seconds
    - _Requirements: FR-6_





- [x] 15. Responsive Design


  - [x] 15.1 Ensure responsive layout

    - Cards stack on mobile (< 768px)
    - Tables scroll horizontally on small screens
    - Quick actions remain accessible
    - Test on tablet (768px) and mobile (375px)
    - _Requirements: FR-5_

- [ ] 16. Write Tests
  - [ ] 16.1 Write feature tests for dashboard
    - Create `tests/Feature/DashboardTest.php`
    - Test: user with role sees expected widgets
    - Test: user with role but missing permission doesn't see widget
    - Test: multi-role user sees combined widgets
    - Test: department filtering for scoped permissions
    - Test: quick actions hidden when permission missing
    - _Requirements: AC-1, AC-2, AC-3_

---

## Implementation Order

1. **Phase 1**: Foundation (Tasks 1-3)
2. **Phase 2**: Role dashboards in order of traffic
   - Task 4: Receptionist (high traffic)
   - Task 5: Doctor (core workflow)
   - Task 6: Pharmacist (high traffic)
   - Task 7: Nurse
   - Task 8: Cashier
   - Task 9: Finance
   - Task 10: Insurance
   - Task 11: Admin
3. **Phase 3**: Main page integration (Task 12)
4. **Phase 4**: Polish (Tasks 13-16)

---

## Dependencies

- Existing Shadcn/ui components (Card, Button, etc.)
- Spatie Permission package (role detection)
- Existing models and relationships
- Wayfinder for route generation
