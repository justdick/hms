# Role-Based Dashboard - Implementation Tasks

## Phase 1: Foundation

### Task 1.1: Create Dashboard Service Infrastructure
**File**: `app/Services/Dashboard/DashboardService.php`

Create the main orchestrator service that:
- Determines visible widgets based on user's granular permissions (not just roles)
- Only fetches metrics for widgets the user can see
- Filters quick actions by permission
- Returns structured data for frontend

**Key Method**: `getVisibleWidgets(User $user): array`
- Checks each widget's required permission(s)
- Returns only widgets user has permission to see
- Example: User with `checkins.view-dept` sees checkin widgets, but user without it doesn't

**Acceptance Criteria**:
- [ ] `getVisibleWidgets()` correctly checks permissions for each widget
- [ ] Metrics only calculated for visible widgets (performance)
- [ ] Quick actions filtered by permission (hidden, not disabled)
- [ ] User with role but missing permission doesn't see related widget

---

### Task 1.2: Create Base Dashboard Components
**Files**: 
- `resources/js/components/Dashboard/MetricCard.tsx`
- `resources/js/components/Dashboard/QuickActions.tsx`
- `resources/js/components/Dashboard/DashboardLayout.tsx`

Create reusable dashboard components:
- MetricCard: Displays a single metric with icon, value, optional trend
- QuickActions: Grid of action buttons
- DashboardLayout: Common wrapper with consistent spacing

**Acceptance Criteria**:
- [ ] MetricCard displays title, value, icon
- [ ] MetricCard supports color variants (default, success, warning, danger)
- [ ] QuickActions renders action buttons with icons
- [ ] Components follow existing Shadcn/ui patterns

---

### Task 1.3: Update DashboardController
**File**: `app/Http/Controllers/DashboardController.php`

Update controller to:
- Inject DashboardService
- Return role-based metrics and lists
- Support Inertia deferred props for heavy data

**Acceptance Criteria**:
- [ ] Controller uses DashboardService
- [ ] Returns appropriate data structure
- [ ] Deferred props for list data

---

## Phase 2: Role-Specific Dashboards

### Task 2.1: Receptionist Dashboard
**Files**:
- `app/Services/Dashboard/ReceptionistDashboard.php`
- `resources/js/components/Dashboard/widgets/receptionist/CheckinMetrics.tsx`
- `resources/js/components/Dashboard/widgets/receptionist/RecentCheckins.tsx`

Implement:
- Today's check-ins count
- Waiting patients count
- Awaiting consultation count
- Recent check-ins list (last 10)
- Quick actions: New Check-in, Register Patient

**Acceptance Criteria**:
- [ ] Metrics accurately reflect today's data
- [ ] Recent check-ins shows patient name, time, status
- [ ] Quick actions link to correct pages

---

### Task 2.2: Nurse Dashboard
**Files**:
- `app/Services/Dashboard/NurseDashboard.php`
- `resources/js/components/Dashboard/widgets/nurse/NurseMetrics.tsx`
- `resources/js/components/Dashboard/widgets/nurse/VitalsQueue.tsx`
- `resources/js/components/Dashboard/widgets/nurse/MedicationSchedule.tsx`

Implement:
- Patients awaiting vitals (department-filtered)
- Pending medication administrations
- Active ward admissions
- Vitals queue list
- Upcoming medication schedule

**Acceptance Criteria**:
- [ ] Metrics filtered by assigned departments
- [ ] Medication schedule shows next 2 hours
- [ ] Vitals queue shows patient name, department, wait time

---

### Task 2.3: Doctor Dashboard
**Files**:
- `app/Services/Dashboard/DoctorDashboard.php`
- `resources/js/components/Dashboard/widgets/doctor/DoctorMetrics.tsx`
- `resources/js/components/Dashboard/widgets/doctor/ConsultationQueue.tsx`
- `resources/js/components/Dashboard/widgets/doctor/PendingLabResults.tsx`

Implement:
- Consultation queue count (department-filtered)
- Active consultations count
- Pending lab results count
- Consultation queue list
- Lab results awaiting review

**Acceptance Criteria**:
- [ ] Queue filtered by doctor's departments
- [ ] Active consultations shows own in-progress
- [ ] Lab results shows unreviewed completed tests

---

### Task 2.4: Pharmacist Dashboard
**Files**:
- `app/Services/Dashboard/PharmacistDashboard.php`
- `resources/js/components/Dashboard/widgets/pharmacist/PharmacyMetrics.tsx`
- `resources/js/components/Dashboard/widgets/pharmacist/PrescriptionQueue.tsx`
- `resources/js/components/Dashboard/widgets/pharmacist/LowStockAlerts.tsx`

Implement:
- Pending prescriptions count
- Ready for dispensing count
- Low stock alerts count
- Prescription queue list
- Low stock items list

**Acceptance Criteria**:
- [ ] Prescription queue shows patient, drug, quantity
- [ ] Low stock shows drug name, current stock, reorder level
- [ ] Quick actions link to pharmacy pages

---

### Task 2.5: Cashier Dashboard
**Files**:
- `app/Services/Dashboard/CashierDashboard.php`
- `resources/js/components/Dashboard/widgets/cashier/CashierMetrics.tsx`
- `resources/js/components/Dashboard/widgets/cashier/RecentPayments.tsx`

Implement:
- Pending payments total
- Today's collections (own)
- Transactions processed today
- Recent payments list

**Acceptance Criteria**:
- [ ] Collections filtered to current user
- [ ] Amounts formatted as currency
- [ ] Recent payments shows patient, amount, method

---

### Task 2.6: Finance Officer Dashboard
**Files**:
- `app/Services/Dashboard/FinanceDashboard.php`
- `resources/js/components/Dashboard/widgets/finance/FinanceMetrics.tsx`
- `resources/js/components/Dashboard/widgets/finance/RevenueSummary.tsx`

Implement:
- Today's total revenue
- Outstanding receivables
- Pending insurance claims amount
- Revenue by payment method breakdown

**Acceptance Criteria**:
- [ ] Revenue includes all payment methods
- [ ] Receivables shows total unpaid charges
- [ ] Breakdown shows cash, mobile money, insurance, etc.

---

### Task 2.7: Insurance Officer Dashboard
**Files**:
- `app/Services/Dashboard/InsuranceDashboard.php`
- `resources/js/components/Dashboard/widgets/insurance/InsuranceMetrics.tsx`
- `resources/js/components/Dashboard/widgets/insurance/ClaimsQueue.tsx`

Implement:
- Claims pending vetting count
- Claims submitted count
- Claims approved this month
- Claims vetting queue list
- Recent batch submissions

**Acceptance Criteria**:
- [ ] Vetting queue shows claim details
- [ ] Batch list shows batch number, status, count
- [ ] Quick actions link to claims pages

---

### Task 2.8: Admin Dashboard
**Files**:
- `app/Services/Dashboard/AdminDashboard.php`
- `resources/js/components/Dashboard/widgets/admin/AdminMetrics.tsx`
- `resources/js/components/Dashboard/widgets/admin/SystemOverview.tsx`

Implement:
- Total patients today
- Total revenue today
- Active users count
- Department activity summary
- System health indicators

**Acceptance Criteria**:
- [ ] Shows system-wide metrics (no filtering)
- [ ] Department summary shows check-ins per department
- [ ] Quick access to admin functions

---

## Phase 3: Main Dashboard Page

### Task 3.1: Create Dashboard Index Page
**File**: `resources/js/pages/Dashboard/Index.tsx`

Create the main dashboard page that:
- Receives role-based data from controller
- Renders appropriate widgets based on user roles
- Handles multi-role users
- Implements polling for real-time updates

**Acceptance Criteria**:
- [ ] Correctly renders role-specific widgets
- [ ] Multi-role users see combined view
- [ ] Polling updates metrics every 30 seconds
- [ ] Loading states for deferred data

---

### Task 3.2: Implement Polling
**File**: `resources/js/pages/Dashboard/Index.tsx`

Add Inertia polling for real-time updates:
- Poll metrics every 30 seconds
- Show refresh indicator
- Handle errors gracefully

**Acceptance Criteria**:
- [ ] Metrics refresh without full page reload
- [ ] Visual indicator during refresh
- [ ] Continues working after network errors

---

## Phase 4: Polish & Optimization

### Task 4.1: Add Caching
**Files**: Role-specific dashboard services

Implement caching for expensive queries:
- Cache per-user metrics (60 second TTL)
- Cache system-wide aggregates (5 minute TTL)
- Invalidate on relevant model events

**Acceptance Criteria**:
- [ ] Repeated requests use cached data
- [ ] Cache invalidates on data changes
- [ ] Dashboard still loads if cache fails

---

### Task 4.2: Performance Optimization
**Files**: All dashboard services

Optimize queries:
- Add missing indexes
- Use eager loading
- Combine related queries

**Acceptance Criteria**:
- [ ] Dashboard loads in under 2 seconds
- [ ] No N+1 queries
- [ ] Query count reasonable (< 20)

---

### Task 4.3: Responsive Design
**File**: `resources/js/pages/Dashboard/Index.tsx`

Ensure dashboard works on all screen sizes:
- Cards stack on mobile
- Tables scroll horizontally
- Quick actions remain accessible

**Acceptance Criteria**:
- [ ] Usable on tablet (768px)
- [ ] Usable on mobile (375px)
- [ ] No horizontal overflow

---

### Task 4.4: Write Tests
**Files**: `tests/Feature/DashboardTest.php`

Write feature tests for:
- Permission-based widget visibility
- Metrics are accurate
- Department filtering works
- Quick actions respect permissions

**Acceptance Criteria**:
- [ ] Test user with role sees expected widgets
- [ ] Test user with role but missing permission doesn't see widget
- [ ] Test multi-role user sees combined widgets
- [ ] Test department filtering for scoped permissions
- [ ] Test quick actions hidden when permission missing
- [ ] All tests pass

---

## Implementation Order

1. **Phase 1**: Foundation (Tasks 1.1-1.3)
2. **Phase 2**: Start with most-used roles
   - Task 2.1: Receptionist (high traffic)
   - Task 2.3: Doctor (core workflow)
   - Task 2.4: Pharmacist (high traffic)
   - Task 2.2: Nurse
   - Task 2.5: Cashier
   - Task 2.6: Finance
   - Task 2.7: Insurance
   - Task 2.8: Admin
3. **Phase 3**: Main page integration
4. **Phase 4**: Polish and optimization

---

## Dependencies

- Existing Shadcn/ui components (Card, Button, etc.)
- Spatie Permission package (role detection)
- Existing models and relationships
- Wayfinder for route generation
