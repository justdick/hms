# Role-Based Dashboard - Technical Design

## Architecture Overview

### Component Structure

```
resources/js/
├── pages/
│   └── Dashboard/
│       └── Index.tsx                    # Main dashboard (role router)
├── components/
│   └── Dashboard/
│       ├── widgets/                     # Role-specific widget components
│       │   ├── MetricCard.tsx           # Reusable metric card
│       │   ├── QuickActions.tsx         # Quick action buttons
│       │   ├── DataTable.tsx            # Mini data table for lists
│       │   │
│       │   ├── receptionist/
│       │   │   ├── CheckinMetrics.tsx
│       │   │   └── RecentCheckins.tsx
│       │   │
│       │   ├── nurse/
│       │   │   ├── NurseMetrics.tsx
│       │   │   ├── VitalsQueue.tsx
│       │   │   └── MedicationSchedule.tsx
│       │   │
│       │   ├── doctor/
│       │   │   ├── DoctorMetrics.tsx
│       │   │   ├── ConsultationQueue.tsx
│       │   │   └── PendingLabResults.tsx
│       │   │
│       │   ├── pharmacist/
│       │   │   ├── PharmacyMetrics.tsx
│       │   │   ├── PrescriptionQueue.tsx
│       │   │   └── LowStockAlerts.tsx
│       │   │
│       │   ├── cashier/
│       │   │   ├── CashierMetrics.tsx
│       │   │   └── RecentPayments.tsx
│       │   │
│       │   ├── finance/
│       │   │   ├── FinanceMetrics.tsx
│       │   │   └── RevenueSummary.tsx
│       │   │
│       │   ├── insurance/
│       │   │   ├── InsuranceMetrics.tsx
│       │   │   └── ClaimsQueue.tsx
│       │   │
│       │   └── admin/
│       │       ├── AdminMetrics.tsx
│       │       └── SystemOverview.tsx
│       │
│       └── DashboardLayout.tsx          # Common layout wrapper
```

### Backend Structure

```
app/
├── Http/
│   └── Controllers/
│       └── DashboardController.php      # Main dashboard controller
├── Services/
│   └── Dashboard/
│       ├── DashboardService.php         # Orchestrates role-based data
│       ├── ReceptionistDashboard.php    # Receptionist metrics
│       ├── NurseDashboard.php           # Nurse metrics
│       ├── DoctorDashboard.php          # Doctor metrics
│       ├── PharmacistDashboard.php      # Pharmacist metrics
│       ├── CashierDashboard.php         # Cashier metrics
│       ├── FinanceDashboard.php         # Finance metrics
│       ├── InsuranceDashboard.php       # Insurance metrics
│       └── AdminDashboard.php           # Admin metrics
```

---

## Database Queries

### Receptionist Metrics
```php
// Today's check-ins
PatientCheckin::whereDate('checked_in_at', today())->count();

// Waiting patients
PatientCheckin::whereDate('checked_in_at', today())
    ->where('status', 'checked_in')
    ->count();

// Vitals taken, awaiting consultation
PatientCheckin::whereDate('checked_in_at', today())
    ->whereIn('status', ['vitals_taken', 'awaiting_consultation'])
    ->count();
```

### Nurse Metrics
```php
// Patients awaiting vitals (in assigned departments)
PatientCheckin::whereDate('checked_in_at', today())
    ->where('status', 'checked_in')
    ->whereIn('department_id', $user->departments->pluck('id'))
    ->count();

// Pending medication administrations
MedicationAdministration::where('status', 'pending')
    ->where('scheduled_time', '<=', now()->addHour())
    ->whereHas('prescription.patientAdmission', function ($q) use ($user) {
        $q->whereIn('ward_id', $user->assignedWardIds());
    })
    ->count();
```

### Doctor Metrics
```php
// Awaiting consultation
PatientCheckin::whereDate('checked_in_at', today())
    ->whereIn('status', ['vitals_taken', 'awaiting_consultation'])
    ->whereIn('department_id', $user->departments->pluck('id'))
    ->count();

// Active consultations
Consultation::where('doctor_id', $user->id)
    ->where('status', 'in_progress')
    ->count();

// Pending lab results
LabOrder::where('status', 'completed')
    ->whereNull('reviewed_at')
    ->whereHas('consultation', function ($q) use ($user) {
        $q->where('doctor_id', $user->id);
    })
    ->count();
```

### Pharmacist Metrics
```php
// Pending prescriptions
Prescription::where('status', 'pending')
    ->whereNull('reviewed_at')
    ->count();

// Ready for dispensing
Prescription::where('status', 'pending')
    ->whereNotNull('reviewed_at')
    ->count();

// Low stock alerts
Drug::whereColumn('quantity_in_stock', '<=', 'reorder_level')
    ->where('is_active', true)
    ->count();
```

### Cashier Metrics
```php
// Pending payments
Charge::where('status', 'pending')
    ->sum('amount');

// Today's collections
Payment::whereDate('created_at', today())
    ->where('processed_by', $user->id)
    ->sum('amount');

// Transactions today
Payment::whereDate('created_at', today())
    ->where('processed_by', $user->id)
    ->count();
```

### Finance Metrics
```php
// Today's total revenue
Payment::whereDate('created_at', today())
    ->sum('amount');

// Outstanding receivables
Charge::where('status', 'pending')
    ->sum('amount');

// Pending insurance claims
InsuranceClaim::whereIn('status', ['pending_vetting', 'vetted'])
    ->sum('claim_amount');
```

### Insurance Metrics
```php
// Claims pending vetting
InsuranceClaim::where('status', 'pending_vetting')->count();

// Claims submitted
InsuranceClaim::where('status', 'submitted')->count();

// Claims approved this month
InsuranceClaim::where('status', 'approved')
    ->whereMonth('updated_at', now()->month)
    ->count();
```

### Admin Metrics
```php
// Total patients today
PatientCheckin::whereDate('checked_in_at', today())->count();

// Total revenue today
Payment::whereDate('created_at', today())->sum('amount');

// Active users (logged in within last 15 minutes)
User::where('last_activity_at', '>=', now()->subMinutes(15))->count();
```

---

## API Response Structure

### DashboardController Response
```php
public function index(Request $request)
{
    $user = $request->user();
    $dashboardService = new DashboardService($user);
    
    return Inertia::render('Dashboard/Index', [
        'userRoles' => $user->getRoleNames(),
        'metrics' => $dashboardService->getMetrics(),
        'lists' => $dashboardService->getLists(),
        'quickActions' => $dashboardService->getQuickActions(),
    ]);
}
```

### Metrics Structure
```typescript
interface DashboardMetrics {
  // Receptionist
  todayCheckins?: number;
  waitingPatients?: number;
  awaitingConsultation?: number;
  
  // Nurse
  awaitingVitals?: number;
  pendingMedications?: number;
  activeAdmissions?: number;
  
  // Doctor
  consultationQueue?: number;
  activeConsultations?: number;
  pendingLabResults?: number;
  
  // Pharmacist
  pendingPrescriptions?: number;
  readyForDispensing?: number;
  lowStockCount?: number;
  
  // Cashier
  pendingPayments?: number;
  todayCollections?: number;
  transactionsToday?: number;
  
  // Finance
  todayRevenue?: number;
  outstandingReceivables?: number;
  pendingInsuranceClaims?: number;
  
  // Insurance
  claimsPendingVetting?: number;
  claimsSubmitted?: number;
  claimsApprovedMonth?: number;
  
  // Admin
  totalPatientsToday?: number;
  totalRevenueToday?: number;
  activeUsers?: number;
}
```

---

## Component Design

### MetricCard Component
```tsx
interface MetricCardProps {
  title: string;
  value: number | string;
  icon: LucideIcon;
  trend?: {
    value: number;
    direction: 'up' | 'down';
  };
  href?: string;
  color?: 'default' | 'success' | 'warning' | 'danger';
}
```

### QuickActions Component
```tsx
interface QuickAction {
  label: string;
  href: string;
  icon: LucideIcon;
  permission?: string;
}
```

### Dashboard Layout
```tsx
<DashboardLayout>
  {/* Top Metrics Row */}
  <div className="grid gap-4 md:grid-cols-3">
    <MetricCard ... />
    <MetricCard ... />
    <MetricCard ... />
  </div>
  
  {/* Main Content */}
  <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
    {/* Primary list/table */}
    <div className="md:col-span-2">
      <Card>...</Card>
    </div>
    
    {/* Quick Actions / Secondary */}
    <div>
      <QuickActions actions={...} />
    </div>
  </div>
</DashboardLayout>
```

---

## Polling Strategy

### Inertia Polling
```tsx
// Poll every 30 seconds for metrics
const { metrics } = usePage<DashboardProps>().props;

useEffect(() => {
  const interval = setInterval(() => {
    router.reload({ only: ['metrics'] });
  }, 30000);
  
  return () => clearInterval(interval);
}, []);
```

### Deferred Props for Heavy Data
```php
return Inertia::render('Dashboard/Index', [
  'metrics' => $dashboardService->getMetrics(),
  'lists' => Inertia::defer(fn () => $dashboardService->getLists()),
]);
```

---

## Caching Strategy

### Cache Keys
```php
// Per-user metrics cache (short TTL)
"dashboard:metrics:{user_id}" => 60 seconds

// System-wide aggregates (longer TTL)
"dashboard:system:today_revenue" => 300 seconds
"dashboard:system:active_users" => 60 seconds
```

### Cache Invalidation
- Clear user cache on relevant model events
- Clear system cache on payment/checkin creation

---

## Security Considerations

### Permission-Based Widget Visibility

Widgets and metrics are shown/hidden based on granular permissions, NOT just roles.
This allows admins to customize what each user sees even within the same role.

```php
// DashboardService determines visible widgets by checking permissions
public function getVisibleWidgets(User $user): array
{
    $widgets = [];
    
    // Check-in widgets - only if user can view check-ins
    if ($user->can('checkins.view-all') || $user->can('checkins.view-dept')) {
        $widgets[] = 'checkin_metrics';
        $widgets[] = 'recent_checkins';
    }
    
    // Vitals widgets - only if user can view/create vitals
    if ($user->can('vitals.view-all') || $user->can('vitals.view-dept')) {
        $widgets[] = 'vitals_queue';
    }
    
    // Consultation widgets - only if user can view consultations
    if ($user->can('consultations.view-all') || $user->can('consultations.view-dept')) {
        $widgets[] = 'consultation_queue';
    }
    
    // Prescription widgets - only if user can view prescriptions
    if ($user->can('prescriptions.view') || $user->can('dispensing.view')) {
        $widgets[] = 'prescription_queue';
    }
    
    // Dispensing widgets - only if user can process dispensing
    if ($user->can('dispensing.process') || $user->can('dispensing.review')) {
        $widgets[] = 'dispensing_metrics';
    }
    
    // Inventory widgets - only if user can view inventory
    if ($user->can('inventory.view')) {
        $widgets[] = 'low_stock_alerts';
    }
    
    // Billing widgets - only if user can view/collect billing
    if ($user->can('billing.view-all') || $user->can('billing.collect')) {
        $widgets[] = 'billing_metrics';
        $widgets[] = 'recent_payments';
    }
    
    // Finance widgets - only if user has finance permissions
    if ($user->can('billing.reports') || $user->can('billing.reconcile')) {
        $widgets[] = 'revenue_summary';
        $widgets[] = 'finance_metrics';
    }
    
    // Insurance widgets - only if user can view/vet claims
    if ($user->can('insurance.view') || $user->can('insurance.vet-claims')) {
        $widgets[] = 'claims_metrics';
        $widgets[] = 'claims_queue';
    }
    
    // Lab widgets - only if user can view lab orders
    if ($user->can('lab-orders.view-all') || $user->can('lab-orders.view-dept')) {
        $widgets[] = 'lab_queue';
        $widgets[] = 'pending_lab_results';
    }
    
    // Ward/Admission widgets - only if user can view admissions
    if ($user->can('admissions.view')) {
        $widgets[] = 'ward_metrics';
        $widgets[] = 'active_admissions';
    }
    
    // Medication administration - only if user can view/administer
    if ($user->can('view medication administrations') || $user->can('administer medications')) {
        $widgets[] = 'medication_schedule';
    }
    
    // Admin widgets - only if user has system admin
    if ($user->can('system.admin')) {
        $widgets[] = 'system_overview';
        $widgets[] = 'admin_metrics';
    }
    
    return $widgets;
}
```

### Permission Mapping for Widgets

| Widget | Required Permission(s) |
|--------|----------------------|
| checkin_metrics | `checkins.view-all` OR `checkins.view-dept` |
| recent_checkins | `checkins.view-all` OR `checkins.view-dept` |
| vitals_queue | `vitals.view-all` OR `vitals.view-dept` |
| consultation_queue | `consultations.view-all` OR `consultations.view-dept` |
| prescription_queue | `prescriptions.view` OR `dispensing.view` |
| dispensing_metrics | `dispensing.process` OR `dispensing.review` |
| low_stock_alerts | `inventory.view` |
| billing_metrics | `billing.view-all` OR `billing.collect` |
| recent_payments | `billing.view-all` OR `billing.collect` |
| revenue_summary | `billing.reports` OR `billing.reconcile` |
| finance_metrics | `billing.reports` |
| claims_metrics | `insurance.view` OR `insurance.vet-claims` |
| claims_queue | `insurance.vet-claims` |
| lab_queue | `lab-orders.view-all` OR `lab-orders.view-dept` |
| pending_lab_results | `lab-orders.view-all` OR `lab-orders.view-dept` |
| ward_metrics | `admissions.view` |
| active_admissions | `admissions.view` |
| medication_schedule | `view medication administrations` |
| system_overview | `system.admin` |
| admin_metrics | `system.admin` |

### Quick Actions Permission Mapping

| Quick Action | Required Permission |
|--------------|-------------------|
| Register Patient | `patients.create` |
| Check-in Patient | `checkins.create` |
| Record Vitals | `vitals.create` |
| Start Consultation | `consultations.create` |
| View Ward Patients | `admissions.view` |
| Admit Patient | `admissions.create` |
| Review Prescriptions | `dispensing.review` |
| Process Dispensing | `dispensing.process` |
| View Inventory | `inventory.view` |
| Process Payment | `billing.collect` |
| View Reports | `billing.reports` |
| Cash Reconciliation | `billing.reconcile` |
| Vet Claims | `insurance.vet-claims` |
| Create Batch | `insurance.manage-batches` |
| Manage Users | `users.view-all` |
| System Settings | `system.settings` |

### Frontend Permission Handling

```typescript
// Pass user permissions to frontend
interface DashboardProps {
  userRoles: string[];
  userPermissions: string[];  // All user's permissions
  visibleWidgets: string[];   // Pre-computed visible widgets
  metrics: DashboardMetrics;
  lists: DashboardLists;
  quickActions: QuickAction[];
}

// Quick action component checks permission
function QuickActions({ actions }: { actions: QuickAction[] }) {
  return (
    <div className="grid gap-2">
      {actions.map((action) => (
        <Button
          key={action.href}
          variant="outline"
          asChild
          // Action is only included if user has permission (filtered server-side)
        >
          <Link href={action.href}>
            <action.icon className="mr-2 h-4 w-4" />
            {action.label}
          </Link>
        </Button>
      ))}
    </div>
  );
}
```

### Data Scoping
```php
// Always scope queries to user's access level
$checkins = PatientCheckin::accessibleTo($user)->...

// Metrics only calculated for visible widgets
public function getMetrics(): array
{
    $metrics = [];
    $visibleWidgets = $this->getVisibleWidgets($this->user);
    
    if (in_array('checkin_metrics', $visibleWidgets)) {
        $metrics = array_merge($metrics, $this->getCheckinMetrics());
    }
    
    if (in_array('consultation_queue', $visibleWidgets)) {
        $metrics = array_merge($metrics, $this->getConsultationMetrics());
    }
    
    // ... etc
    
    return $metrics;
}
```

### Example: Nurse Without Medication Permission

A nurse who has been removed from medication administration permission:
- Will NOT see: medication_schedule widget, pending medications count
- Will still see: vitals_queue, ward_metrics (if they have those permissions)

### Example: Doctor Without Lab Order Permission

A doctor who cannot order labs:
- Will NOT see: pending_lab_results widget, lab results count
- Will still see: consultation_queue, active consultations

---

## Correctness Properties

### CP-1: Role Detection
- Dashboard correctly identifies user's role(s)
- Multi-role users see combined relevant widgets
- Unknown roles default to minimal dashboard

### CP-2: Metric Accuracy
- All counts match actual database state
- Department filtering correctly applied
- Date boundaries (today) are timezone-aware

### CP-3: Permission Enforcement
- Quick actions respect user permissions
- Unauthorized actions are hidden or disabled
- No data leakage across departments

### CP-4: Performance
- Dashboard loads within 2 seconds
- Queries use proper indexes
- N+1 queries prevented with eager loading

### CP-5: Real-time Updates
- Polling updates metrics without full page reload
- Stale data indicator if refresh fails
- Graceful degradation on network issues
