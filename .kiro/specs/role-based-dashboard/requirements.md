# Role-Based Dashboard - Requirements

## Overview
Create a professional, role-based dashboard that provides each user with relevant metrics, quick actions, and insights based on their role in the HMS system.

## User Roles & Dashboard Views

### 1. Receptionist Dashboard
**Primary Focus**: Patient flow and check-in management

**Metrics (Top Cards)**:
- Today's check-ins count
- Patients waiting (checked_in status)
- Patients with vitals taken (awaiting consultation)

**Main Content**:
- Recent check-ins list (last 10)
- Quick patient search
- Quick action: New Check-in button

**Quick Actions**:
- Register new patient
- Check-in existing patient

---

### 2. Nurse Dashboard
**Primary Focus**: Patient care, vitals, and medication administration

**Metrics (Top Cards)**:
- Patients awaiting vitals (in assigned departments)
- Pending medication administrations (due within 1 hour)
- Active ward admissions (in assigned wards)

**Main Content**:
- Vitals queue (patients needing vitals)
- Upcoming medication schedule (next 2 hours)
- Minor procedures queue (if in Minor Procedures dept)

**Quick Actions**:
- Record vitals
- View medication schedule
- View ward patients

---

### 3. Doctor Dashboard
**Primary Focus**: Consultations and patient care

**Metrics (Top Cards)**:
- Patients awaiting consultation (in assigned departments)
- Active consultations (in_progress)
- Pending lab results (for their patients)

**Main Content**:
- Consultation queue (patients ready for consultation)
- Recent consultations (last 5 completed today)
- Lab results awaiting review

**Quick Actions**:
- Start consultation
- View ward rounds
- Admit patient

---

### 4. Pharmacist Dashboard
**Primary Focus**: Dispensing and inventory management

**Metrics (Top Cards)**:
- Pending prescriptions (awaiting review)
- Ready for dispensing (reviewed, awaiting pickup)
- Low stock alerts count

**Main Content**:
- Prescription queue (pending review)
- Low stock items list (below reorder level)
- Recent dispensing activity

**Quick Actions**:
- Review prescriptions
- Process dispensing
- View inventory

---

### 5. Lab Technician Dashboard
**Primary Focus**: Lab orders and sample processing

**Metrics (Top Cards)**:
- Pending lab orders
- Samples collected (awaiting processing)
- Completed today

**Main Content**:
- Lab orders queue (by priority)
- Recent results entered

**Quick Actions**:
- Process lab order
- Enter results

---

### 6. Cashier Dashboard
**Primary Focus**: Payments and billing

**Metrics (Top Cards)**:
- Pending payments (unpaid charges)
- Today's collections (amount)
- Transactions processed today

**Main Content**:
- Recent payments received
- Patients with outstanding balances

**Quick Actions**:
- Process payment
- View patient bill

---

### 7. Finance Officer Dashboard
**Primary Focus**: Financial overview and reconciliation

**Metrics (Top Cards)**:
- Today's total revenue
- Outstanding receivables
- Insurance claims pending

**Main Content**:
- Revenue summary (by payment method)
- Recent large transactions
- Pending reconciliations

**Quick Actions**:
- View reports
- Cash reconciliation
- Generate statements

---

### 8. Insurance Officer Dashboard
**Primary Focus**: Claims management and vetting

**Metrics (Top Cards)**:
- Claims pending vetting
- Claims submitted (awaiting response)
- Claims approved this month

**Main Content**:
- Claims vetting queue
- Recent batch submissions
- Claims requiring attention (rejected/issues)

**Quick Actions**:
- Vet claims
- Create batch
- View reports

---

### 9. Admin Dashboard
**Primary Focus**: System overview and management

**Metrics (Top Cards)**:
- Total patients today
- Total revenue today
- Active users online
- System alerts

**Main Content**:
- Department activity summary
- Recent system events
- Quick stats by module

**Quick Actions**:
- Manage users
- View all reports
- System settings

---

## Functional Requirements

### FR-1: Permission-Based Widget Visibility
- Widgets are shown/hidden based on granular permissions, NOT just roles
- A user with a role but missing specific permissions won't see related widgets
- This allows admins to customize dashboards by adjusting permissions
- Example: A nurse without `administer medications` permission won't see medication schedule

### FR-1.1: Role as Starting Point
- User's role(s) determine the default dashboard layout
- Permissions then filter which widgets are actually visible
- Users with multiple roles see combined widgets (deduplicated)

### FR-2: Real-time Updates
- Dashboard metrics should refresh periodically (every 30-60 seconds)
- Use Inertia polling for live updates
- Visual indicator when data is refreshing

### FR-3: Department Filtering
- Metrics filtered by user's assigned departments
- Nurses/Doctors only see their department's data
- Admin sees all departments with optional filter

### FR-4: Quick Actions
- One-click access to common tasks
- Actions HIDDEN (not just disabled) if user lacks permission
- Only show actions the user can actually perform
- Server-side filtering ensures no unauthorized actions leak to frontend

### FR-5: Responsive Design
- Dashboard works on desktop and tablet
- Cards stack appropriately on smaller screens
- Critical metrics always visible

### FR-6: Performance
- Dashboard loads within 2 seconds
- Efficient queries with proper indexing
- Consider caching for expensive aggregations

---

## Non-Functional Requirements

### NFR-1: Accessibility
- All metrics have proper ARIA labels
- Keyboard navigation support
- Sufficient color contrast

### NFR-2: Consistency
- Use existing Shadcn/ui components
- Follow established HMS design patterns
- Consistent card layouts across roles

### NFR-3: Extensibility
- Widget-based architecture for easy additions
- New roles can be added without major refactoring

---

## Acceptance Criteria

### AC-1: Permission-Based View
- [ ] Widgets shown based on user's actual permissions, not just role
- [ ] User with role but missing permission doesn't see related widget
- [ ] Quick actions only shown if user has required permission
- [ ] Metrics are accurate and permission-appropriate

### AC-2: Data Accuracy
- [ ] All counts match actual database records
- [ ] Department filtering works correctly
- [ ] Date filtering (today) is accurate

### AC-3: Performance
- [ ] Dashboard loads in under 2 seconds
- [ ] No N+1 query issues
- [ ] Polling doesn't degrade performance

### AC-4: User Experience
- [ ] Clean, professional appearance
- [ ] Intuitive navigation to detailed views
- [ ] Loading states for async data

### AC-5: Multi-Role Support
- [ ] Users with multiple roles see combined view
- [ ] No duplicate widgets
- [ ] Logical grouping of mixed-role content
