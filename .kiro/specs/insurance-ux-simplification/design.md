# Design Document

## Overview

This design document outlines the technical approach for simplifying the Insurance Management System UX. The simplification consolidates duplicate interfaces, flattens navigation hierarchies, removes low-value features, and streamlines common workflows while maintaining all essential functionality and backward compatibility.

### Design Principles

1. **Consolidation over Duplication** - Merge overlapping interfaces into unified experiences
2. **Progressive Disclosure** - Show essential information first, details on demand
3. **Minimal Navigation** - Reduce clicks required for common tasks
4. **Backward Compatibility** - No database schema changes or API modifications
5. **Performance First** - Lazy loading and caching for optimal speed
6. **Accessibility** - Maintain WCAG 2.1 Level AA compliance throughout

### High-Level Architecture

The simplification follows a component-based architecture using React with Inertia.js. The design maintains the existing Laravel backend API structure while reorganizing and consolidating frontend components.

```
┌─────────────────────────────────────────────────────────┐
│                    Insurance Module                      │
├─────────────────────────────────────────────────────────┤
│  Providers  │  Plans  │  Coverage  │  Claims  │ Analytics│
└─────────────────────────────────────────────────────────┘
       │           │          │           │           │
       │           │          │           │           │
       ▼           ▼          ▼           ▼           ▼
   Provider    Plans     Coverage    Claims      Analytics
    Pages      Pages    Management   Pages       Dashboard
                │          Page        │
                │            │         │
                ▼            ▼         ▼
           Quick Actions  Unified   Slide-over
           on List       Interface  Vetting Panel
```

## Architecture

### Phase 1: Quick Wins Architecture

#### 1.1 Consolidated Reports Dashboard

**Current State:**
- 6 separate report pages, each requiring navigation
- Reports Index page acts as a landing page with links

**New Design:**
- Single Analytics Dashboard page with 6 interactive widgets
- Each widget displays key metrics in collapsed state
- Click widget to expand inline for detailed view
- Shared date range filter affects all widgets

**Component Structure:**
```
AnalyticsDashboard.tsx
├── DateRangeFilter (shared across all widgets)
├── ClaimsSummaryWidget (expandable)
├── RevenueAnalysisWidget (expandable)
├── OutstandingClaimsWidget (expandable)
├── VettingPerformanceWidget (expandable)
├── UtilizationWidget (expandable)
└── RejectionAnalysisWidget (expandable)
```

**Files to Modify:**
- `resources/js/Pages/Admin/Insurance/Reports/Index.tsx` - Transform into Analytics Dashboard
- `routes/insurance.php` - Keep report API endpoints, remove separate page routes

**Files to Delete:**
- `resources/js/Pages/Admin/Insurance/Reports/ClaimsSummary.tsx`
- `resources/js/Pages/Admin/Insurance/Reports/RevenueAnalysis.tsx`
- `resources/js/Pages/Admin/Insurance/Reports/OutstandingClaims.tsx`
- `resources/js/Pages/Admin/Insurance/Reports/VettingPerformance.tsx`
- `resources/js/Pages/Admin/Insurance/Reports/UtilizationReport.tsx`
- `resources/js/Pages/Admin/Insurance/Reports/RejectionAnalysis.tsx`

#### 1.2 Merged Coverage Management

**Current State:**
- Coverage Dashboard (card-based visual interface)
- Coverage Rules Page (table-based management interface)
- Separate Tariffs page
- Users confused about which to use

**New Design:**
- Single Coverage Management Interface combining all three
- Visual category cards as primary interface (keep existing)
- Expanded cards show unified table with coverage rules, exceptions, AND tariffs
- Tariff pricing integrated into exception creation workflow

**Component Structure:**
```
CoverageManagement.tsx (renamed from CoverageDashboard.tsx)
├── CategoryCard (6 instances)
│   ├── CategoryHeader (icon, name, coverage %)
│   ├── InlinePercentageEdit (existing component)
│   ├── ExceptionCount Badge
│   └── ExpandedContent (when clicked)
│       ├── CoverageRulesTable (unified view)
│       │   ├── Default rule row
│       │   ├── Exception rows (with tariff column)
│       │   └── Inline edit capabilities
│       ├── AddExceptionButton
│       └── BulkImportButton
├── GlobalSearch (searches across all categories)
├── BulkImportButton (page-level, not per-card)
└── ExportButton
```

**Files to Modify:**
- `resources/js/Pages/Admin/Insurance/Plans/CoverageDashboard.tsx` → Rename to `CoverageManagement.tsx`
- `resources/js/components/Insurance/AddExceptionModal.tsx` - Add tariff price field
- `resources/js/components/Insurance/ExceptionList.tsx` - Add tariff column

**Files to Delete:**
- `resources/js/Pages/Admin/Insurance/CoverageRules/Index.tsx`
- `resources/js/Pages/Admin/Insurance/CoverageRules/Create.tsx`
- `resources/js/Pages/Admin/Insurance/CoverageRules/Edit.tsx`
- `resources/js/Pages/Admin/Insurance/Tariffs/Index.tsx`
- `resources/js/Pages/Admin/Insurance/Tariffs/Create.tsx`
- `resources/js/Pages/Admin/Insurance/Tariffs/Edit.tsx`

#### 1.3 Removed Low-Value Features

**Components to Delete:**
- `resources/js/components/Insurance/RecentItemsPanel.tsx`
- `resources/js/components/Insurance/KeyboardShortcutsHelp.tsx`
- `resources/js/components/Insurance/QuickActionsMenu.tsx`

**Components to Keep:**
- `resources/js/components/Insurance/AddExceptionModal.tsx` ✓
- `resources/js/components/Insurance/BulkImportModal.tsx` ✓
- `resources/js/components/Insurance/ExceptionList.tsx` ✓
- `resources/js/components/Insurance/InlinePercentageEdit.tsx` ✓
- `resources/js/components/Insurance/SuccessMessage.tsx` ✓

**Code Cleanup:**
- Remove Recent Items API call from `CoverageManagement.tsx`
- Remove keyboard shortcut hooks from `CoverageManagement.tsx`
- Replace QuickActionsMenu with direct action buttons

### Phase 2: Workflow Optimization Architecture

#### 2.1 Flattened Navigation Hierarchy

**Current Flow:**
```
Plans List → Plan Details → Coverage Dashboard → Expand Category → Add Exception
(5 clicks minimum)
```

**New Flow:**
```
Plans List → [Manage Coverage] → Coverage Management → Add Exception
(3 clicks)
```

**Implementation:**
Add action buttons directly to Plans List table:

```tsx
// In Plans/Index.tsx
<TableCell>
  <div className="flex gap-2">
    <Link href={`/admin/insurance/plans/${plan.id}/coverage`}>
      <Button size="sm" variant="outline">
        <Settings className="mr-2 h-4 w-4" />
        Manage Coverage
      </Button>
    </Link>
    <Link href={`/admin/insurance/claims?plan_id=${plan.id}`}>
      <Button size="sm" variant="outline">
        <FileText className="mr-2 h-4 w-4" />
        View Claims
      </Button>
    </Link>
    <Link href={`/admin/insurance/plans/${plan.id}/edit`}>
      <Button size="sm" variant="ghost">
        <Edit className="h-4 w-4" />
      </Button>
    </Link>
  </div>
</TableCell>
```

**Files to Modify:**
- `resources/js/Pages/Admin/Insurance/Plans/Index.tsx` - Add action buttons
- `routes/insurance.php` - Update coverage route to go directly to management

**Optional:**
- Keep `Plans/Show.tsx` for detailed plan information view
- Make it accessible via plan name link, not required for common workflows

#### 2.2 Streamlined Claims Vetting Workflow

**Current Flow:**
```
Claims List → Click Claim → Navigate to Show Page → Vet → Back to List
(Context switching, slow)
```

**New Flow:**
```
Claims List → Click Review → Slide-over Panel Opens → Vet → Panel Closes
(No navigation, fast)
```

**Component Structure:**
```
Claims/Index.tsx
├── ClaimsList (existing table)
├── ClaimsVettingPanel (NEW - slide-over)
│   ├── ClaimHeader (patient, insurance, dates)
│   ├── ClaimItemsTable (services, drugs, labs)
│   ├── DiagnosisSection
│   ├── FinancialSummary
│   └── VettingActions
│       ├── ApproveButton
│       ├── RejectButton (with reason textarea)
│       └── CloseButton
└── FilterPanel (simplified)
```

**Implementation Details:**
- Use shadcn/ui `Sheet` component for slide-over panel
- Load claim details via API when panel opens
- Submit vetting action via API, refresh list on success
- Panel slides from right side, overlays list
- Keyboard shortcuts: Escape to close, Ctrl+Enter to approve

**Files to Create:**
- `resources/js/components/Insurance/ClaimsVettingPanel.tsx`

**Files to Modify:**
- `resources/js/Pages/Admin/Insurance/Claims/Index.tsx` - Add vetting panel
- `app/Http/Controllers/Admin/InsuranceClaimController.php` - Ensure `show` returns JSON for API

**Files to Keep (Optional):**
- `resources/js/Pages/Admin/Insurance/Claims/Show.tsx` - Keep for detailed view/printing

#### 2.3 Integrated Tariff Management

**Current State:**
- Separate Tariffs page with its own CRUD interface
- Disconnected from coverage rules

**New Design:**
- Tariff pricing integrated into exception creation
- Tariff column in exceptions table
- No separate tariffs page

**AddExceptionModal Enhancement:**
```tsx
<FormField>
  <Label>Pricing</Label>
  <RadioGroup value={pricingType} onValueChange={setPricingType}>
    <RadioGroupItem value="standard">Use Standard Price</RadioGroupItem>
    <RadioGroupItem value="custom">Set Custom Tariff</RadioGroupItem>
  </RadioGroup>
  
  {pricingType === 'custom' && (
    <Input
      type="number"
      placeholder="Custom tariff price"
      value={tariffPrice}
      onChange={(e) => setTariffPrice(e.target.value)}
    />
  )}
</FormField>
```

**Database:**
- Use existing `insurance_tariffs` table
- Create tariff record when exception created with custom price
- Display tariff in exceptions table

**Files to Modify:**
- `resources/js/components/Insurance/AddExceptionModal.tsx`
- `resources/js/components/Insurance/ExceptionList.tsx`
- `app/Http/Controllers/Admin/InsuranceCoverageRuleController.php` - Handle tariff creation

### Phase 3: Polish & Refinement Architecture

#### 3.1 Simplified Coverage Dashboard UI

**Improvements:**
- Remove nested panels within expanded cards
- Move bulk import to page-level button
- Add global search across all categories
- Consistent visual indicators

**Layout:**
```
┌─────────────────────────────────────────────────────────┐
│  Coverage Management - VET Gold Plan                    │
│  [Global Search] [Bulk Import] [Export]                 │
├─────────────────────────────────────────────────────────┤
│  ┌──────────┐  ┌──────────┐  ┌──────────┐             │
│  │ Drugs    │  │ Labs     │  │ Consult  │             │
│  │ 80%      │  │ 90%      │  │ 70%      │             │
│  │ 3 except │  │ 1 except │  │ 0 except │             │
│  └──────────┘  └──────────┘  └──────────┘             │
│                                                         │
│  [Expanded Card - Drugs]                               │
│  ┌─────────────────────────────────────────────────┐  │
│  │ Item Code │ Description │ Coverage │ Tariff    │  │
│  ├───────────┼─────────────┼──────────┼───────────┤  │
│  │ DEFAULT   │ All drugs   │ 80%      │ Standard  │  │
│  │ PARA-500  │ Paracetamol │ 100%     │ GHS 2.00  │  │
│  │ AMOX-250  │ Amoxicillin │ 90%      │ Standard  │  │
│  └─────────────────────────────────────────────────┘  │
│  [Add Exception]                                       │
└─────────────────────────────────────────────────────────┘
```

#### 3.2 Smart Defaults for New Plans

**Implementation:**
When creating a new plan, automatically create 6 default coverage rules:

```php
// In InsurancePlanController@store
$plan = InsurancePlan::create($validatedData);

// Create default coverage rules
$categories = ['consultation', 'drug', 'lab', 'procedure', 'ward', 'nursing'];
foreach ($categories as $category) {
    InsuranceCoverageRule::create([
        'insurance_plan_id' => $plan->id,
        'coverage_category' => $category,
        'coverage_type' => 'percentage',
        'coverage_value' => 80.00, // Default 80%
        'patient_copay_percentage' => 20.00,
        'is_covered' => true,
        'is_active' => true,
    ]);
}
```

**Plan Creation Wizard Enhancement:**
- Add "Coverage Presets" step (existing)
- Pre-fill with 80% for all categories
- Allow user to adjust before creation
- Show success message: "Plan created with default 80% coverage for all categories"

**Files to Modify:**
- `app/Http/Controllers/Admin/InsurancePlanController.php`
- `resources/js/Pages/Admin/Insurance/Plans/CreateWithWizard.tsx`

## Components and Interfaces

### New Components

#### 1. ClaimsVettingPanel Component

**Purpose:** Slide-over panel for vetting claims without navigation

**Props:**
```typescript
interface ClaimsVettingPanelProps {
  claimId: number | null;
  isOpen: boolean;
  onClose: () => void;
  onVetSuccess: () => void;
}
```

**State Management:**
```typescript
const [claim, setClaim] = useState<InsuranceClaim | null>(null);
const [loading, setLoading] = useState(false);
const [vettingAction, setVettingAction] = useState<'approve' | 'reject' | null>(null);
const [rejectionReason, setRejectionReason] = useState('');
```

**API Integration:**
```typescript
// Load claim details
useEffect(() => {
  if (claimId && isOpen) {
    axios.get(`/admin/insurance/claims/${claimId}`)
      .then(response => setClaim(response.data))
      .catch(error => console.error(error));
  }
}, [claimId, isOpen]);

// Submit vetting
const handleVet = () => {
  router.post(`/admin/insurance/claims/${claimId}/vet`, {
    action: vettingAction,
    rejection_reason: rejectionReason,
  }, {
    onSuccess: () => {
      onVetSuccess();
      onClose();
    }
  });
};
```

#### 2. AnalyticsWidget Component

**Purpose:** Reusable widget for analytics dashboard

**Props:**
```typescript
interface AnalyticsWidgetProps {
  title: string;
  icon: React.ElementType;
  color: string;
  endpoint: string;
  dateRange: { from: string; to: string };
  renderSummary: (data: any) => React.ReactNode;
  renderDetails: (data: any) => React.ReactNode;
}
```

**Behavior:**
- Collapsed by default, shows summary
- Click to expand inline, shows details
- Lazy load details data on expansion
- Skeleton loading state

### Modified Components

#### 1. CoverageManagement (formerly CoverageDashboard)

**Changes:**
- Remove RecentItemsPanel integration
- Remove KeyboardShortcutsHelp
- Remove QuickActionsMenu
- Add global search functionality
- Move bulk import button to page level
- Add tariff column to exceptions table

#### 2. AddExceptionModal

**Changes:**
- Add tariff pricing section
- Radio group: "Use Standard Price" vs "Set Custom Tariff"
- Conditional tariff price input
- Update API call to include tariff data

#### 3. Plans/Index

**Changes:**
- Add action buttons column
- "Manage Coverage" button (primary action)
- "View Claims" button (secondary action)
- "Edit" button (tertiary action)
- Responsive: collapse to dropdown on mobile

## Data Models

### No Schema Changes Required

All changes use existing database tables:
- `insurance_providers`
- `insurance_plans`
- `insurance_coverage_rules`
- `insurance_tariffs`
- `insurance_claims`
- `insurance_claim_items`

### API Response Enhancements

#### Claims API - Add JSON Response

```php
// InsuranceClaimController@show
public function show(InsuranceClaim $claim)
{
    $claim->load([
        'patientInsurance.plan.provider',
        'claimItems',
        'vettedByUser',
        'submittedByUser',
    ]);

    // Support both Inertia and JSON responses
    if (request()->wantsJson()) {
        return response()->json(['claim' => $claim]);
    }

    return Inertia::render('Admin/Insurance/Claims/Show', [
        'claim' => $claim,
    ]);
}
```

#### Analytics API - Widget Data

```php
// InsuranceReportController - Add widget-specific methods
public function getClaimsSummaryWidget(Request $request)
{
    $dateRange = $this->getDateRange($request);
    
    return response()->json([
        'total_claims' => $this->getTotalClaims($dateRange),
        'total_amount' => $this->getTotalAmount($dateRange),
        'by_status' => $this->getClaimsByStatus($dateRange),
        'by_provider' => $this->getClaimsByProvider($dateRange),
    ]);
}
```

## Error Handling

### Client-Side Error Handling

**API Call Failures:**
```typescript
try {
  const response = await axios.get(endpoint);
  setData(response.data);
} catch (error) {
  if (error.response?.status === 404) {
    toast.error('Claim not found');
  } else if (error.response?.status === 403) {
    toast.error('You do not have permission to view this claim');
  } else {
    toast.error('Failed to load claim details. Please try again.');
  }
  console.error('API Error:', error);
}
```

**Form Validation:**
```typescript
const handleSubmit = () => {
  if (vettingAction === 'reject' && !rejectionReason.trim()) {
    toast.error('Please provide a rejection reason');
    return;
  }
  
  // Proceed with submission
  submitVetting();
};
```

### Server-Side Error Handling

**Maintain Existing Validation:**
```php
// No changes to existing validation rules
// All existing error responses remain the same
```

**Transaction Safety:**
```php
DB::transaction(function () use ($plan, $coverageData) {
    // Create plan
    $plan = InsurancePlan::create($planData);
    
    // Create default coverage rules
    foreach ($categories as $category) {
        InsuranceCoverageRule::create([...]);
    }
    
    // If any step fails, entire transaction rolls back
});
```

## Testing Strategy

### Unit Tests

**Coverage Management:**
- Test category card rendering
- Test inline percentage editing
- Test exception creation with tariff
- Test global search functionality

**Claims Vetting:**
- Test panel open/close
- Test claim data loading
- Test vetting action submission
- Test validation (rejection reason required)

**Analytics Dashboard:**
- Test widget rendering
- Test widget expansion
- Test date range filtering
- Test data loading states

### Feature Tests

**End-to-End Workflows:**
```php
// Test: Create plan with smart defaults
public function test_creating_plan_generates_default_coverage_rules()
{
    $response = $this->post('/admin/insurance/plans', $planData);
    
    $plan = InsurancePlan::latest()->first();
    $this->assertCount(6, $plan->coverageRules);
    $this->assertEquals(80, $plan->coverageRules->first()->coverage_value);
}

// Test: Vet claim via API
public function test_vetting_claim_updates_status()
{
    $claim = InsuranceClaim::factory()->create(['status' => 'pending_vetting']);
    
    $response = $this->post("/admin/insurance/claims/{$claim->id}/vet", [
        'action' => 'approve',
    ]);
    
    $this->assertEquals('vetted', $claim->fresh()->status);
}

// Test: Add exception with tariff
public function test_adding_exception_with_custom_tariff()
{
    $response = $this->post('/admin/insurance/coverage-rules', [
        'insurance_plan_id' => $plan->id,
        'coverage_category' => 'drug',
        'item_code' => 'PARA-500',
        'coverage_value' => 100,
        'tariff_price' => 2.50,
    ]);
    
    $this->assertDatabaseHas('insurance_tariffs', [
        'item_code' => 'PARA-500',
        'insurance_tariff' => 2.50,
    ]);
}
```

### Browser Tests (Pest v4)

**Interactive Workflows:**
```php
it('can vet a claim using the slide-over panel', function () {
    $claim = InsuranceClaim::factory()->create(['status' => 'pending_vetting']);
    
    $this->actingAs(User::factory()->create());
    
    $page = visit('/admin/insurance/claims');
    
    $page->assertSee($claim->claim_check_code)
        ->click('Review') // Opens slide-over
        ->assertSee('Claim Details')
        ->assertSee($claim->patient_full_name)
        ->click('Approve')
        ->assertSee('Claim approved successfully')
        ->assertDontSee('Claim Details'); // Panel closed
    
    expect($claim->fresh()->status)->toBe('vetted');
});

it('can manage coverage from plans list', function () {
    $plan = InsurancePlan::factory()->create();
    
    $page = visit('/admin/insurance/plans');
    
    $page->assertSee($plan->plan_name)
        ->click('Manage Coverage')
        ->assertUrlIs("/admin/insurance/plans/{$plan->id}/coverage")
        ->assertSee('Coverage Management')
        ->assertSee('Drugs')
        ->assertSee('Labs');
});
```

### Accessibility Tests

**Keyboard Navigation:**
```php
it('supports keyboard navigation in vetting panel', function () {
    $page = visit('/admin/insurance/claims');
    
    $page->click('Review')
        ->press('Tab') // Focus approve button
        ->press('Tab') // Focus reject button
        ->press('Escape') // Close panel
        ->assertDontSee('Claim Details');
});
```

**Screen Reader Compatibility:**
```php
it('provides proper ARIA labels', function () {
    $page = visit('/admin/insurance/plans/1/coverage');
    
    $page->assertAttribute('[role="button"]', 'aria-expanded', 'false')
        ->click('[aria-label="Drugs category"]')
        ->assertAttribute('[role="button"]', 'aria-expanded', 'true');
});
```

## Performance Considerations

### Lazy Loading

**Analytics Widgets:**
- Load summary data immediately
- Lazy load detail data on expansion
- Cache expanded data for 5 minutes

**Claims Vetting Panel:**
- Load claim details only when panel opens
- Prefetch next claim in list for faster navigation

### Caching Strategy

**Client-Side:**
```typescript
// Cache coverage rules per plan
const coverageCache = new Map<number, CoverageRule[]>();

const loadCoverageRules = async (planId: number) => {
  if (coverageCache.has(planId)) {
    return coverageCache.get(planId);
  }
  
  const rules = await fetchCoverageRules(planId);
  coverageCache.set(planId, rules);
  return rules;
};
```

**Server-Side:**
```php
// Cache report data
Cache::remember("insurance.reports.claims_summary.{$dateRange}", 300, function () {
    return $this->generateClaimsSummary($dateRange);
});
```

### Bundle Size Optimization

**Code Splitting:**
- Lazy load Analytics Dashboard components
- Lazy load Claims Vetting Panel
- Lazy load Bulk Import Modal

**Tree Shaking:**
- Remove unused Insurance components (RecentItemsPanel, KeyboardShortcutsHelp, QuickActionsMenu)
- Reduces bundle size by ~15KB

## Migration Path

### Phase 1 Deployment

1. Deploy new Analytics Dashboard
2. Update navigation to point to new dashboard
3. Keep old report pages accessible via direct URL (for 1 week)
4. Remove old report pages after confirmation

### Phase 2 Deployment

1. Deploy Coverage Management changes
2. Update all links to point to new interface
3. Remove old Coverage Rules and Tariffs pages
4. Deploy Claims Vetting Panel
5. Update Claims list to use panel

### Phase 3 Deployment

1. Deploy smart defaults for new plans
2. Deploy UI polish changes
3. Performance optimizations

### Rollback Plan

**If issues arise:**
1. Revert to previous commit (feature branch not merged)
2. All old pages still exist in git history
3. No database changes means instant rollback
4. No data loss risk

## Accessibility Compliance

### WCAG 2.1 Level AA Requirements

**Keyboard Navigation:**
- All interactive elements accessible via Tab
- Escape key closes modals and panels
- Enter/Space activates buttons
- Arrow keys navigate within widgets

**Screen Reader Support:**
- Proper ARIA labels on all interactive elements
- Role attributes for custom components
- Live regions for dynamic content updates
- Descriptive alt text for icons

**Color Contrast:**
- Maintain 4.5:1 contrast ratio for text
- 3:1 contrast ratio for UI components
- Don't rely solely on color for information

**Focus Management:**
- Visible focus indicators
- Focus trapped in modals/panels
- Focus returned to trigger element on close

## Security Considerations

### No New Security Risks

- All changes are UI-only
- Existing authentication/authorization maintained
- No new API endpoints (reuse existing)
- No changes to data validation rules

### Maintained Security Features

- CSRF protection on all forms
- Authorization checks on all routes
- Input validation on all submissions
- XSS protection via React escaping

## Documentation Updates

### User Documentation

- Update user guide with new navigation paths
- Add screenshots of new interfaces
- Document new workflows (vetting panel, quick actions)
- Remove documentation for deleted features

### Developer Documentation

- Update component documentation
- Document new component props/interfaces
- Update API documentation (if endpoints change)
- Add migration guide for custom implementations
