# Design Document

## Overview

This design document outlines the technical approach for enhancing the Hospital Management System's billing user experience. The enhancement consolidates the current two-page billing workflow into a single-page interface while adding comprehensive bill management capabilities including waivers, adjustments, and centralized service access overrides.

### Key Design Principles

1. **Single-Page Flow**: Eliminate page navigation by using expandable sections and inline forms
2. **Centralized Control**: All financial decisions (payments, waivers, overrides) occur on the billing page
3. **Permission-Based UI**: Show/hide features based on granular user permissions
4. **Real-Time Updates**: Use optimistic UI updates and Inertia partial reloads for instant feedback
5. **Audit Trail**: Log all financial operations with user, timestamp, and reason
6. **Backward Compatibility**: Maintain all existing features while improving UX

### Current System Analysis

**Existing Flow (6-7 clicks):**
1. Navigate to `/billing`
2. Search for patient
3. Click patient in results
4. Navigate to `/billing/checkin/{id}/billing`
5. Review charges
6. Select charges
7. Submit payment

**New Flow (2-3 clicks):**
1. Navigate to `/billing`
2. Search and expand patient details (inline)
3. Click Quick Pay or submit inline payment form


## Architecture

### Component Structure

```
resources/js/pages/Billing/Payments/
├── Index.tsx (Unified billing interface)
├── components/
│   ├── PatientSearchBar.tsx
│   ├── PatientSearchResults.tsx
│   ├── PatientBillingDetails.tsx (expandable)
│   ├── ChargesList.tsx
│   ├── InlinePaymentForm.tsx
│   ├── QuickPayButton.tsx
│   ├── BillWaiverModal.tsx
│   ├── BillAdjustmentModal.tsx
│   ├── ServiceAccessOverrideModal.tsx
│   ├── OverrideHistorySection.tsx
│   └── BillingStatsCards.tsx
```

### Backend Structure

```
app/
├── Http/Controllers/Billing/
│   ├── PaymentController.php (enhanced)
│   ├── BillAdjustmentController.php (new)
│   └── ServiceOverrideController.php (new)
├── Services/
│   ├── BillingService.php (enhanced)
│   ├── BillAdjustmentService.php (new)
│   └── OverrideAuditService.php (new)
├── Models/
│   ├── Charge.php (enhanced with adjustment fields)
│   ├── ServiceAccessOverride.php (new)
│   └── BillAdjustment.php (new)
└── Policies/
    └── BillingPolicy.php (enhanced with new permissions)
```

### Database Schema Changes

**New Tables:**

```sql
-- Service access overrides
CREATE TABLE service_access_overrides (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    patient_checkin_id BIGINT UNSIGNED NOT NULL,
    service_type VARCHAR(50) NOT NULL,
    service_code VARCHAR(50) NULL,
    reason TEXT NOT NULL,
    authorized_by BIGINT UNSIGNED NOT NULL,
    authorized_at TIMESTAMP NOT NULL,
    expires_at TIMESTAMP NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    FOREIGN KEY (patient_checkin_id) REFERENCES patient_checkins(id),
    FOREIGN KEY (authorized_by) REFERENCES users(id),
    INDEX idx_checkin_service (patient_checkin_id, service_type),
    INDEX idx_expires (expires_at, is_active)
);

-- Bill adjustments
CREATE TABLE bill_adjustments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    charge_id BIGINT UNSIGNED NOT NULL,
    adjustment_type ENUM('waiver', 'discount_percentage', 'discount_fixed') NOT NULL,
    original_amount DECIMAL(10, 2) NOT NULL,
    adjustment_amount DECIMAL(10, 2) NOT NULL,
    final_amount DECIMAL(10, 2) NOT NULL,
    reason TEXT NOT NULL,
    adjusted_by BIGINT UNSIGNED NOT NULL,
    adjusted_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    FOREIGN KEY (charge_id) REFERENCES charges(id),
    FOREIGN KEY (adjusted_by) REFERENCES users(id),
    INDEX idx_charge (charge_id),
    INDEX idx_adjusted_by (adjusted_by)
);
```

**Enhanced Charges Table:**

```sql
ALTER TABLE charges
ADD COLUMN is_waived BOOLEAN DEFAULT FALSE AFTER is_emergency_override,
ADD COLUMN waived_by BIGINT UNSIGNED NULL AFTER is_waived,
ADD COLUMN waived_at TIMESTAMP NULL AFTER waived_by,
ADD COLUMN waived_reason TEXT NULL AFTER waived_at,
ADD COLUMN adjustment_amount DECIMAL(10, 2) DEFAULT 0.00 AFTER waived_reason,
ADD COLUMN original_amount DECIMAL(10, 2) NULL AFTER adjustment_amount;
```


## Components and Interfaces

### Frontend Components

#### 1. Unified Billing Index Page

**File:** `resources/js/pages/Billing/Payments/Index.tsx`

**Purpose:** Single-page interface combining search, charge display, and payment processing

**State Management:**
```typescript
interface BillingIndexState {
    searchQuery: string;
    searchResults: PatientSearchResult[];
    selectedPatient: PatientSearchResult | null;
    expandedPatients: Set<number>;
    isSearching: boolean;
    processingPayment: boolean;
}
```

**Key Features:**
- Patient search with debounced API calls
- Expandable patient details (no navigation)
- Inline payment form
- Quick pay buttons
- Service access status display
- Override history section

#### 2. Patient Billing Details Component

**File:** `resources/js/pages/Billing/Payments/components/PatientBillingDetails.tsx`

**Props:**
```typescript
interface PatientBillingDetailsProps {
    patient: PatientSearchResult;
    isExpanded: boolean;
    onToggle: () => void;
    permissions: BillingPermissions;
}

interface BillingPermissions {
    canProcessPayment: boolean;
    canWaiveCharges: boolean;
    canAdjustCharges: boolean;
    canOverrideServices: boolean;
    canCancelCharges: boolean;
}
```

**Sections:**
- Patient information card
- Service access status with override buttons
- Visits grouped by date
- Charges list with checkboxes
- Insurance breakdown
- Inline payment form
- Override history

#### 3. Quick Pay Button Component

**File:** `resources/js/pages/Billing/Payments/components/QuickPayButton.tsx`

**Behavior:**
- Single click payment processing
- Auto-selects default payment method (cash)
- Pre-fills exact copay amount
- Shows minimal confirmation modal
- Updates UI optimistically

**API Call:**
```typescript
POST /billing/charges/quick-pay-all
Body: {
    patient_checkin_id: number,
    payment_method: 'cash' | 'card' | 'mobile_money',
    charges: number[] // all pending charge IDs
}
```

#### 4. Bill Waiver Modal

**File:** `resources/js/pages/Billing/Payments/components/BillWaiverModal.tsx`

**Form Fields:**
- Charge selection (if multiple)
- Reason (required, min 10 characters)
- Confirmation checkbox

**Validation:**
- Requires billing.waive-charges permission
- Reason must be substantive
- Shows original amount and impact

#### 5. Bill Adjustment Modal

**File:** `resources/js/pages/Billing/Payments/components/BillAdjustmentModal.tsx`

**Form Fields:**
- Adjustment type: percentage discount or fixed amount
- Discount value
- Reason (required, min 10 characters)
- Preview of new amount

**Calculation:**
```typescript
// Percentage discount
finalAmount = originalAmount * (1 - discountPercentage / 100)

// Fixed amount discount
finalAmount = originalAmount - discountAmount
```

#### 6. Service Access Override Modal

**File:** `resources/js/pages/Billing/Payments/components/ServiceAccessOverrideModal.tsx`

**Form Fields:**
- Service type (pre-selected based on blocked service)
- Reason (required, min 20 characters)
- Duration (default 2 hours, configurable)

**Display:**
- Shows pending charges causing block
- Explains override will expire
- Warns about audit trail


### Backend Controllers

#### 1. Enhanced PaymentController

**File:** `app/Http/Controllers/Billing/PaymentController.php`

**New Methods:**

```php
// Quick pay all charges for a patient
public function quickPayAll(Request $request, PatientCheckin $checkin)
{
    $this->authorize('create', Charge::class);
    
    $validated = $request->validate([
        'payment_method' => 'required|in:cash,card,mobile_money,insurance,bank_transfer',
        'charges' => 'required|array',
    ]);
    
    // Process payment for all selected charges
    // Return updated billing status
}

// Get real-time billing status (for polling/refresh)
public function getBillingStatus(PatientCheckin $checkin)
{
    return response()->json([
        'pending_charges' => $this->billingService->getPendingCharges($checkin),
        'service_status' => $this->getServiceStatus($checkin),
        'active_overrides' => $this->getActiveOverrides($checkin),
    ]);
}
```

#### 2. BillAdjustmentController (New)

**File:** `app/Http/Controllers/Billing/BillAdjustmentController.php`

**Methods:**

```php
// Waive a charge completely
public function waive(Request $request, Charge $charge)
{
    $this->authorize('waive', $charge);
    
    $validated = $request->validate([
        'reason' => 'required|string|min:10|max:500',
    ]);
    
    DB::transaction(function () use ($charge, $validated) {
        // Create bill adjustment record
        BillAdjustment::create([
            'charge_id' => $charge->id,
            'adjustment_type' => 'waiver',
            'original_amount' => $charge->amount,
            'adjustment_amount' => $charge->amount,
            'final_amount' => 0,
            'reason' => $validated['reason'],
            'adjusted_by' => auth()->id(),
            'adjusted_at' => now(),
        ]);
        
        // Update charge
        $charge->update([
            'is_waived' => true,
            'waived_by' => auth()->id(),
            'waived_at' => now(),
            'waived_reason' => $validated['reason'],
            'status' => 'waived',
        ]);
    });
    
    return back()->with('success', 'Charge waived successfully');
}

// Apply discount to a charge
public function adjust(Request $request, Charge $charge)
{
    $this->authorize('adjust', $charge);
    
    $validated = $request->validate([
        'adjustment_type' => 'required|in:discount_percentage,discount_fixed',
        'adjustment_value' => 'required|numeric|min:0',
        'reason' => 'required|string|min:10|max:500',
    ]);
    
    // Calculate final amount
    $finalAmount = $this->calculateAdjustedAmount(
        $charge->amount,
        $validated['adjustment_type'],
        $validated['adjustment_value']
    );
    
    DB::transaction(function () use ($charge, $validated, $finalAmount) {
        // Create adjustment record
        BillAdjustment::create([
            'charge_id' => $charge->id,
            'adjustment_type' => $validated['adjustment_type'],
            'original_amount' => $charge->amount,
            'adjustment_amount' => $charge->amount - $finalAmount,
            'final_amount' => $finalAmount,
            'reason' => $validated['reason'],
            'adjusted_by' => auth()->id(),
            'adjusted_at' => now(),
        ]);
        
        // Update charge
        $charge->update([
            'original_amount' => $charge->amount,
            'amount' => $finalAmount,
            'adjustment_amount' => $charge->amount - $finalAmount,
        ]);
    });
    
    return back()->with('success', 'Charge adjusted successfully');
}
```

#### 3. ServiceOverrideController (New)

**File:** `app/Http/Controllers/Billing/ServiceOverrideController.php`

**Methods:**

```php
// Activate service access override
public function activate(Request $request, PatientCheckin $checkin)
{
    $this->authorize('overrideService', $checkin);
    
    $validated = $request->validate([
        'service_type' => 'required|in:consultation,laboratory,pharmacy,ward',
        'service_code' => 'nullable|string',
        'reason' => 'required|string|min:20|max:500',
        'duration_hours' => 'nullable|integer|min:1|max:24',
    ]);
    
    $override = ServiceAccessOverride::create([
        'patient_checkin_id' => $checkin->id,
        'service_type' => $validated['service_type'],
        'service_code' => $validated['service_code'] ?? null,
        'reason' => $validated['reason'],
        'authorized_by' => auth()->id(),
        'authorized_at' => now(),
        'expires_at' => now()->addHours($validated['duration_hours'] ?? 2),
        'is_active' => true,
    ]);
    
    return back()->with('success', "Service access override activated for {$validated['service_type']}");
}

// Deactivate override early
public function deactivate(ServiceAccessOverride $override)
{
    $this->authorize('overrideService', $override->patientCheckin);
    
    $override->update(['is_active' => false]);
    
    return back()->with('success', 'Service access override deactivated');
}

// Get active overrides for a patient
public function index(PatientCheckin $checkin)
{
    $overrides = ServiceAccessOverride::where('patient_checkin_id', $checkin->id)
        ->where('is_active', true)
        ->where('expires_at', '>', now())
        ->with('authorizedBy:id,name')
        ->get();
    
    return response()->json(['overrides' => $overrides]);
}
```


## Data Models

### ServiceAccessOverride Model

**File:** `app/Models/ServiceAccessOverride.php`

```php
class ServiceAccessOverride extends Model
{
    protected $fillable = [
        'patient_checkin_id',
        'service_type',
        'service_code',
        'reason',
        'authorized_by',
        'authorized_at',
        'expires_at',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'authorized_at' => 'datetime',
            'expires_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function patientCheckin(): BelongsTo
    {
        return $this->belongsTo(PatientCheckin::class);
    }

    public function authorizedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'authorized_by');
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function getRemainingDuration(): string
    {
        if ($this->isExpired()) {
            return 'Expired';
        }

        $diff = now()->diff($this->expires_at);
        return sprintf('%dh %dm', $diff->h, $diff->i);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true)
            ->where('expires_at', '>', now());
    }

    public function scopeForService($query, string $serviceType, ?string $serviceCode = null)
    {
        $query = $query->where('service_type', $serviceType);

        if ($serviceCode) {
            $query->where('service_code', $serviceCode);
        }

        return $query;
    }
}
```

### BillAdjustment Model

**File:** `app/Models/BillAdjustment.php`

```php
class BillAdjustment extends Model
{
    protected $fillable = [
        'charge_id',
        'adjustment_type',
        'original_amount',
        'adjustment_amount',
        'final_amount',
        'reason',
        'adjusted_by',
        'adjusted_at',
    ];

    protected function casts(): array
    {
        return [
            'original_amount' => 'decimal:2',
            'adjustment_amount' => 'decimal:2',
            'final_amount' => 'decimal:2',
            'adjusted_at' => 'datetime',
        ];
    }

    public function charge(): BelongsTo
    {
        return $this->belongsTo(Charge::class);
    }

    public function adjustedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'adjusted_by');
    }

    public function getAdjustmentPercentage(): float
    {
        if ($this->original_amount <= 0) {
            return 0;
        }

        return round(($this->adjustment_amount / $this->original_amount) * 100, 2);
    }

    public function isWaiver(): bool
    {
        return $this->adjustment_type === 'waiver';
    }

    public function scopeForCharge($query, int $chargeId)
    {
        return $query->where('charge_id', $chargeId);
    }

    public function scopeByUser($query, int $userId)
    {
        return $query->where('adjusted_by', $userId);
    }
}
```

### Enhanced Charge Model

**File:** `app/Models/Charge.php` (additions)

```php
// Add to existing Charge model

public function adjustments(): HasMany
{
    return $this->hasMany(BillAdjustment::class);
}

public function isWaived(): bool
{
    return $this->is_waived === true;
}

public function hasAdjustment(): bool
{
    return $this->adjustment_amount > 0;
}

public function getEffectiveAmount(): float
{
    if ($this->is_waived) {
        return 0;
    }

    return $this->amount;
}

public function scopeWaived($query)
{
    return $query->where('is_waived', true);
}

public function scopeAdjusted($query)
{
    return $query->where('adjustment_amount', '>', 0);
}
```


## Error Handling

### Frontend Error Handling

**Validation Errors:**
- Display inline error messages below form fields
- Use Inertia's error bag for server-side validation
- Show toast notifications for successful operations

**Network Errors:**
- Retry failed requests automatically (max 2 retries)
- Show user-friendly error messages
- Maintain form state on error

**Permission Errors:**
- Hide unauthorized actions (don't show buttons)
- Show informative message if user attempts unauthorized action
- Redirect to appropriate page if needed

### Backend Error Handling

**Payment Processing Errors:**
```php
try {
    DB::transaction(function () use ($charges, $payment) {
        // Process payment
    });
} catch (\Exception $e) {
    Log::error('Payment processing failed', [
        'user_id' => auth()->id(),
        'checkin_id' => $checkin->id,
        'error' => $e->getMessage(),
    ]);

    return back()->withErrors([
        'payment' => 'Payment processing failed. Please try again.',
    ]);
}
```

**Override Errors:**
```php
// Check if override already exists
$existingOverride = ServiceAccessOverride::active()
    ->forService($serviceType, $serviceCode)
    ->where('patient_checkin_id', $checkin->id)
    ->first();

if ($existingOverride) {
    return back()->withErrors([
        'override' => 'An active override already exists for this service.',
    ]);
}
```

**Adjustment Errors:**
```php
// Validate adjustment doesn't exceed charge amount
if ($adjustmentAmount > $charge->amount) {
    return back()->withErrors([
        'adjustment' => 'Adjustment amount cannot exceed charge amount.',
    ]);
}

// Prevent adjustment on already paid charges
if ($charge->isPaid()) {
    return back()->withErrors([
        'adjustment' => 'Cannot adjust a charge that has been paid.',
    ]);
}
```


## Testing Strategy

### Unit Tests

**BillingService Tests:**
```php
it('calculates adjusted amount correctly for percentage discount', function () {
    $service = new BillAdjustmentService();
    $result = $service->calculateAdjustedAmount(100, 'discount_percentage', 20);
    expect($result)->toBe(80.0);
});

it('calculates adjusted amount correctly for fixed discount', function () {
    $service = new BillAdjustmentService();
    $result = $service->calculateAdjustedAmount(100, 'discount_fixed', 25);
    expect($result)->toBe(75.0);
});

it('checks if service access override is expired', function () {
    $override = ServiceAccessOverride::factory()->create([
        'expires_at' => now()->subHour(),
    ]);
    expect($override->isExpired())->toBeTrue();
});
```

**Model Tests:**
```php
it('marks charge as waived correctly', function () {
    $charge = Charge::factory()->create(['amount' => 100]);
    
    $charge->update([
        'is_waived' => true,
        'waived_by' => 1,
        'waived_at' => now(),
        'status' => 'waived',
    ]);
    
    expect($charge->isWaived())->toBeTrue();
    expect($charge->getEffectiveAmount())->toBe(0.0);
});

it('calculates adjustment percentage correctly', function () {
    $adjustment = BillAdjustment::factory()->create([
        'original_amount' => 100,
        'adjustment_amount' => 20,
    ]);
    
    expect($adjustment->getAdjustmentPercentage())->toBe(20.0);
});
```

### Feature Tests

**Payment Processing:**
```php
it('allows billing clerk to process quick payment', function () {
    $clerk = User::factory()->withPermission('billing.create')->create();
    $checkin = PatientCheckin::factory()->create();
    $charge = Charge::factory()->pending()->create([
        'patient_checkin_id' => $checkin->id,
        'amount' => 100,
    ]);
    
    $response = $this->actingAs($clerk)
        ->post("/billing/charges/quick-pay-all", [
            'patient_checkin_id' => $checkin->id,
            'payment_method' => 'cash',
            'charges' => [$charge->id],
        ]);
    
    $response->assertRedirect();
    expect($charge->fresh()->isPaid())->toBeTrue();
});

it('prevents unauthorized user from waiving charges', function () {
    $user = User::factory()->create(); // No waive permission
    $charge = Charge::factory()->pending()->create();
    
    $response = $this->actingAs($user)
        ->post("/billing/charges/{$charge->id}/waive", [
            'reason' => 'Indigent patient',
        ]);
    
    $response->assertForbidden();
});
```

**Service Access Override:**
```php
it('allows authorized user to activate service override', function () {
    $admin = User::factory()->admin()->create();
    $checkin = PatientCheckin::factory()->create();
    
    $response = $this->actingAs($admin)
        ->post("/billing/checkin/{$checkin->id}/override", [
            'service_type' => 'laboratory',
            'reason' => 'Emergency - patient unconscious, urgent blood work needed',
        ]);
    
    $response->assertRedirect();
    expect(ServiceAccessOverride::count())->toBe(1);
    
    $override = ServiceAccessOverride::first();
    expect($override->service_type)->toBe('laboratory');
    expect($override->isExpired())->toBeFalse();
});

it('expires service override after 2 hours', function () {
    $override = ServiceAccessOverride::factory()->create([
        'expires_at' => now()->addHours(2),
    ]);
    
    $this->travel(3)->hours();
    
    expect($override->fresh()->isExpired())->toBeTrue();
});
```

**Bill Adjustment:**
```php
it('allows admin to waive charge completely', function () {
    $admin = User::factory()->admin()->create();
    $charge = Charge::factory()->pending()->create(['amount' => 150]);
    
    $response = $this->actingAs($admin)
        ->post("/billing/charges/{$charge->id}/waive", [
            'reason' => 'Indigent patient - unable to pay',
        ]);
    
    $response->assertRedirect();
    
    $charge->refresh();
    expect($charge->isWaived())->toBeTrue();
    expect($charge->status)->toBe('waived');
    expect(BillAdjustment::count())->toBe(1);
});

it('allows admin to apply percentage discount', function () {
    $admin = User::factory()->admin()->create();
    $charge = Charge::factory()->pending()->create(['amount' => 100]);
    
    $response = $this->actingAs($admin)
        ->post("/billing/charges/{$charge->id}/adjust", [
            'adjustment_type' => 'discount_percentage',
            'adjustment_value' => 20,
            'reason' => 'Staff discount',
        ]);
    
    $response->assertRedirect();
    
    $charge->refresh();
    expect($charge->amount)->toBe(80.0);
    expect($charge->original_amount)->toBe(100.0);
});
```

### Browser Tests (Pest v4)

```php
it('completes full payment workflow on single page', function () {
    $clerk = User::factory()->withPermission('billing.create')->create();
    $patient = Patient::factory()->create(['first_name' => 'John', 'last_name' => 'Doe']);
    $checkin = PatientCheckin::factory()->create(['patient_id' => $patient->id]);
    $charge = Charge::factory()->pending()->create([
        'patient_checkin_id' => $checkin->id,
        'amount' => 150,
    ]);
    
    $this->actingAs($clerk);
    
    $page = visit('/billing');
    
    $page->assertSee('Billing & Payments')
        ->fill('search', 'John Doe')
        ->waitFor('text', 'John Doe')
        ->click('View Details')
        ->assertSee('GHS 150')
        ->click('Quick Pay All')
        ->assertSee('Payment processed successfully');
    
    expect($charge->fresh()->isPaid())->toBeTrue();
});
```


## Permission System

### New Permissions

```php
// In database seeder or migration
$permissions = [
    'billing.view-all' => 'View all billing records',
    'billing.view-dept' => 'View department billing records',
    'billing.create' => 'Process payments',
    'billing.update' => 'Update billing records',
    'billing.waive-charges' => 'Waive patient charges',
    'billing.adjust-charges' => 'Adjust charge amounts',
    'billing.emergency-override' => 'Override service access requirements',
    'billing.cancel-charges' => 'Cancel charges',
    'billing.view-audit-trail' => 'View billing audit trail',
    'billing.configure' => 'Configure billing settings',
];
```

### Policy Implementation

**File:** `app/Policies/BillingPolicy.php`

```php
class BillingPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('billing.view-all') || $user->can('billing.view-dept');
    }

    public function create(User $user): bool
    {
        return $user->can('billing.create');
    }

    public function waive(User $user, Charge $charge): bool
    {
        // Only allow waiving pending charges
        if (!$charge->isPending()) {
            return false;
        }

        return $user->can('billing.waive-charges');
    }

    public function adjust(User $user, Charge $charge): bool
    {
        // Only allow adjusting pending charges
        if (!$charge->isPending()) {
            return false;
        }

        return $user->can('billing.adjust-charges');
    }

    public function overrideService(User $user, PatientCheckin $checkin): bool
    {
        return $user->can('billing.emergency-override');
    }

    public function cancel(User $user, Charge $charge): bool
    {
        // Only allow cancelling pending charges
        if (!$charge->isPending()) {
            return false;
        }

        return $user->can('billing.cancel-charges');
    }

    public function viewAuditTrail(User $user): bool
    {
        return $user->can('billing.view-audit-trail');
    }
}
```

### Frontend Permission Checks

```typescript
// In Inertia shared props
interface BillingPermissions {
    canViewAll: boolean;
    canViewDept: boolean;
    canCreate: boolean;
    canWaive: boolean;
    canAdjust: boolean;
    canOverride: boolean;
    canCancel: boolean;
    canViewAudit: boolean;
}

// Usage in components
{permissions.canWaive && (
    <Button onClick={() => openWaiverModal(charge)}>
        Waive Charge
    </Button>
)}

{permissions.canOverride && (
    <Button onClick={() => openOverrideModal(service)}>
        Override Service Access
    </Button>
)}
```


## UI/UX Design Patterns

### Single-Page Layout

```
┌─────────────────────────────────────────────────────────────┐
│  Billing & Payments                    [Configuration]      │
├─────────────────────────────────────────────────────────────┤
│  [Stats: Pending | Today's Revenue | Outstanding | Rate]   │
├─────────────────────────────────────────────────────────────┤
│                                                              │
│  Search Patient: [___________________________] 🔍           │
│                                                              │
│  ┌────────────────────────────────────────────────────┐    │
│  │ John Doe - PAT2025000123          [Quick Pay All] │    │
│  │ Patient Owes: GHS 450 | Insurance: GHS 200        │    │
│  │ 2 visits with charges                              │    │
│  │                                                     │    │
│  │ [▼ View Details]                                   │    │
│  └────────────────────────────────────────────────────┘    │
│                                                              │
│  ┌────────────────────────────────────────────────────┐    │
│  │ EXPANDED DETAILS                                   │    │
│  │                                                     │    │
│  │ Patient Info | Service Status | Payment Summary    │    │
│  │                                                     │    │
│  │ Visit 1: Cardiology - Jan 15, 2025                │    │
│  │ ☑ Consultation - GHS 150 (Pay: GHS 30)            │    │
│  │   [Quick Pay] [Waive] [Adjust]                    │    │
│  │ ☑ Lab Test - GHS 200 (Pay: GHS 200)               │    │
│  │   [Quick Pay] [Waive] [Adjust]                    │    │
│  │                                                     │    │
│  │ Service Access Status:                             │    │
│  │ ✅ Consultation - Allowed                          │    │
│  │ ❌ Laboratory - Blocked [Override]                 │    │
│  │                                                     │    │
│  │ ┌──────────────────────────────────────────────┐  │    │
│  │ │ PAYMENT FORM                                 │  │    │
│  │ │ Method: [Cash ▼]                             │  │    │
│  │ │ Amount: GHS 230.00                           │  │    │
│  │ │ Notes: [___________]                         │  │    │
│  │ │ [Process Payment]                            │  │    │
│  │ └──────────────────────────────────────────────┘  │    │
│  │                                                     │    │
│  │ Override History:                                  │    │
│  │ • Lab override by Dr. Smith (Jan 15, 2:30 PM)     │    │
│  │   Expires in: 1h 25m                               │    │
│  └────────────────────────────────────────────────────┘    │
└─────────────────────────────────────────────────────────────┘
```

### Color Coding

**Charge Status:**
- Pending: Orange/Amber
- Paid: Green
- Partial: Blue
- Waived: Gray
- Cancelled: Red (strikethrough)

**Service Status:**
- Allowed: Green with checkmark
- Blocked: Red with X icon
- Override Active: Yellow with clock icon

**Insurance Coverage:**
- Covered: Green badge with shield icon
- Not Covered: Gray badge
- Partial Coverage: Blue badge with percentage

### Responsive Design

**Desktop (>1024px):**
- Two-column layout: Search results | Selected patient details
- Inline payment form
- Full override history visible

**Tablet (768px - 1024px):**
- Single column with expandable sections
- Sticky payment summary at bottom
- Collapsible override history

**Mobile (<768px):**
- Stack all sections vertically
- Fixed payment button at bottom
- Simplified charge display
- Modal for payment form


## Patient Profile Integration

### Billing Summary Component

**File:** `resources/js/pages/Patients/components/BillingSummary.tsx`

**Location:** Patient profile page (`/patients/{patient}`)

**Display:**
```
┌─────────────────────────────────────────────────┐
│ Billing Summary                                 │
├─────────────────────────────────────────────────┤
│ Outstanding Balance: GHS 450.00                 │
│ Insurance Covered: GHS 200.00                   │
│ Patient Owes: GHS 250.00                        │
│                                                  │
│ Recent Payments:                                │
│ • Jan 15, 2025 - GHS 100 (Cash)                │
│ • Jan 10, 2025 - GHS 50 (Mobile Money)         │
│                                                  │
│ [Process Payment] (if has billing permission)  │
└─────────────────────────────────────────────────┘
```

**Props:**
```typescript
interface BillingSummaryProps {
    patient: Patient;
    canProcessPayment: boolean;
}

interface BillingSummaryData {
    totalOutstanding: number;
    insuranceCovered: number;
    patientOwes: number;
    recentPayments: Payment[];
    hasActiveOverrides: boolean;
}
```

**Backend Endpoint:**
```php
// In PatientController
public function show(Patient $patient)
{
    $this->authorize('view', $patient);
    
    $billingSummary = null;
    
    if (auth()->user()->can('patients.view')) {
        $billingSummary = $this->getBillingSummary($patient);
    }
    
    return Inertia::render('Patients/Show', [
        'patient' => $patient,
        'billingSummary' => $billingSummary,
        'canProcessPayment' => auth()->user()->can('billing.create'),
    ]);
}

private function getBillingSummary(Patient $patient)
{
    $checkins = $patient->checkins()
        ->with('charges')
        ->get();
    
    $pendingCharges = $checkins->flatMap->charges
        ->where('status', 'pending');
    
    $recentPayments = $checkins->flatMap->charges
        ->where('status', 'paid')
        ->sortByDesc('paid_at')
        ->take(5);
    
    return [
        'total_outstanding' => $pendingCharges->sum('amount'),
        'insurance_covered' => $pendingCharges->sum('insurance_covered_amount'),
        'patient_owes' => $pendingCharges->sum('patient_copay_amount'),
        'recent_payments' => $recentPayments->map(fn($charge) => [
            'date' => $charge->paid_at->format('M j, Y'),
            'amount' => $charge->paid_amount,
            'method' => $charge->metadata['payment_method'] ?? 'Unknown',
        ]),
    ];
}
```


## Service Page Integration

### Blocked Service Display

**Laboratory Page Example:**

```typescript
// In Lab/Show.tsx
interface LabOrderShowProps {
    labOrder: LabOrder;
    serviceBlocked: boolean;
    blockReason: string;
    pendingCharges: Charge[];
}

// Display
{serviceBlocked && (
    <Alert variant="destructive">
        <AlertTriangle className="h-4 w-4" />
        <AlertTitle>Service Blocked - Payment Required</AlertTitle>
        <AlertDescription>
            <p>{blockReason}</p>
            <p className="mt-2">Outstanding charges: {formatCurrency(totalPending)}</p>
            <p className="mt-2 font-medium">
                Please direct patient to the billing desk to resolve payment.
            </p>
        </AlertDescription>
    </Alert>
)}
```

**Pharmacy Dispensing Page Example:**

```typescript
// In Pharmacy/Dispensing/Show.tsx
{serviceBlocked ? (
    <Card className="border-red-200 bg-red-50">
        <CardHeader>
            <CardTitle className="flex items-center gap-2 text-red-700">
                <XCircle className="h-5 w-5" />
                Cannot Dispense - Payment Required
            </CardTitle>
        </CardHeader>
        <CardContent>
            <p>Patient has outstanding charges: {formatCurrency(totalPending)}</p>
            <p className="mt-2">Please direct patient to billing desk.</p>
            <Button 
                variant="outline" 
                onClick={() => router.visit(`/billing/checkin/${checkin.id}/billing`)}
                className="mt-4"
            >
                View Billing Details
            </Button>
        </CardContent>
    </Card>
) : (
    <DispensingForm prescription={prescription} />
)}
```

**Consultation Page Example:**

```typescript
// In Consultation/Show.tsx
{serviceBlocked && (
    <Banner variant="warning">
        <p>This consultation is blocked due to unpaid charges from previous visits.</p>
        <p>Total outstanding: {formatCurrency(totalPending)}</p>
        <p>Patient must visit billing desk before consultation can proceed.</p>
    </Banner>
)}
```

### Override Active Display

```typescript
// When override is active
{hasActiveOverride && (
    <Alert variant="success">
        <CheckCircle className="h-4 w-4" />
        <AlertTitle>Service Access Authorized</AlertTitle>
        <AlertDescription>
            <p>Emergency override active</p>
            <p className="text-sm">Expires in: {override.remainingDuration}</p>
            <p className="text-sm text-muted-foreground">
                Authorized by: {override.authorizedBy.name}
            </p>
        </AlertDescription>
    </Alert>
)}
```

### Backend Service Check

```php
// In middleware or controller
public function show(LabOrder $labOrder)
{
    $checkin = $labOrder->patientCheckin;
    
    // Check if service is blocked
    $canProceed = $this->billingService->canProceedWithService(
        $checkin,
        'laboratory',
        $labOrder->lab_service_code
    );
    
    $pendingCharges = [];
    $blockReason = '';
    
    if (!$canProceed) {
        $pendingCharges = $this->billingService->getPendingCharges($checkin, 'laboratory');
        $blockReason = 'Outstanding payment of ' . 
            formatCurrency($pendingCharges->sum('amount')) . ' required';
    }
    
    // Check for active override
    $activeOverride = ServiceAccessOverride::active()
        ->forService('laboratory')
        ->where('patient_checkin_id', $checkin->id)
        ->first();
    
    return Inertia::render('Lab/Show', [
        'labOrder' => $labOrder,
        'serviceBlocked' => !$canProceed && !$activeOverride,
        'blockReason' => $blockReason,
        'pendingCharges' => $pendingCharges,
        'activeOverride' => $activeOverride,
    ]);
}
```


## Performance Considerations

### Frontend Optimization

**1. Debounced Search:**
```typescript
const debouncedSearch = useMemo(
    () => debounce((query: string) => {
        searchPatients(query);
    }, 300),
    []
);
```

**2. Virtualized Lists:**
- Use `react-window` for long charge lists
- Render only visible items
- Improves performance with 100+ charges

**3. Optimistic UI Updates:**
```typescript
const handleQuickPay = async (chargeId: number) => {
    // Update UI immediately
    setCharges(prev => prev.map(c => 
        c.id === chargeId ? { ...c, status: 'paid' } : c
    ));
    
    try {
        await router.post(`/billing/charges/${chargeId}/quick-pay`);
    } catch (error) {
        // Revert on error
        setCharges(prev => prev.map(c => 
            c.id === chargeId ? { ...c, status: 'pending' } : c
        ));
    }
};
```

**4. Lazy Loading:**
- Load override history only when expanded
- Load payment history on demand
- Defer non-critical data

### Backend Optimization

**1. Eager Loading:**
```php
$patients = Patient::with([
    'checkins.charges' => fn($q) => $q->where('status', 'pending'),
    'checkins.department:id,name',
    'activeInsurance.plan.provider',
])
->where(/* search criteria */)
->limit(10)
->get();
```

**2. Query Optimization:**
```php
// Use indexes
Schema::table('charges', function (Blueprint $table) {
    $table->index(['patient_checkin_id', 'status']);
    $table->index(['service_type', 'status']);
});

Schema::table('service_access_overrides', function (Blueprint $table) {
    $table->index(['patient_checkin_id', 'is_active', 'expires_at']);
});
```

**3. Caching:**
```php
// Cache service charge rules
$rules = Cache::remember('service_charge_rules', 3600, function () {
    return ServiceChargeRule::active()->get()->keyBy('service_type');
});

// Cache billing configuration
$config = Cache::remember('billing_config', 3600, function () {
    return BillingConfiguration::active()->get()->pluck('value', 'key');
});
```

**4. Database Transactions:**
```php
// Use transactions for multi-step operations
DB::transaction(function () use ($charges, $payment) {
    foreach ($charges as $charge) {
        $charge->markAsPaid($payment->amount);
    }
    
    PaymentLog::create([/* ... */]);
});
```

### Monitoring

**1. Performance Metrics:**
- Track average payment processing time
- Monitor search response times
- Log slow queries (>100ms)

**2. Error Tracking:**
- Log all payment failures
- Track override activation frequency
- Monitor waiver/adjustment patterns

**3. Audit Logging:**
```php
Log::channel('billing_audit')->info('Charge waived', [
    'charge_id' => $charge->id,
    'original_amount' => $charge->amount,
    'waived_by' => auth()->id(),
    'reason' => $reason,
    'timestamp' => now(),
]);
```


## Migration Strategy

### Phase 1: Database Changes

1. Create new tables (service_access_overrides, bill_adjustments)
2. Add new columns to charges table
3. Create indexes for performance
4. Seed new permissions

### Phase 2: Backend Implementation

1. Create new models (ServiceAccessOverride, BillAdjustment)
2. Create new controllers (BillAdjustmentController, ServiceOverrideController)
3. Enhance existing controllers (PaymentController)
4. Update BillingService with new methods
5. Implement policies for new permissions
6. Add routes for new endpoints

### Phase 3: Frontend Implementation

1. Create new components (modals, forms)
2. Refactor Index.tsx to single-page layout
3. Add Quick Pay functionality
4. Implement override history display
5. Add billing summary to patient profile
6. Update service pages with block messages

### Phase 4: Testing

1. Write unit tests for new services
2. Write feature tests for new endpoints
3. Write browser tests for complete workflows
4. Test permission enforcement
5. Test edge cases (expired overrides, concurrent payments)

### Phase 5: Deployment

1. Run migrations in production
2. Deploy backend changes
3. Deploy frontend changes
4. Monitor for errors
5. Gather user feedback

### Rollback Plan

If issues arise:
1. Revert frontend to old two-page flow
2. Keep new backend endpoints (backward compatible)
3. New features (waivers, overrides) remain available
4. Fix issues and redeploy

### Data Migration

No data migration needed - all existing charges remain unchanged. New fields default to null/false.


## Security Considerations

### Authorization

**1. Permission Checks:**
- All financial operations require explicit permissions
- Check permissions in both controller and policy
- Frontend hides unauthorized actions

**2. Audit Trail:**
- Log all waivers with user ID and reason
- Log all adjustments with before/after amounts
- Log all overrides with service type and duration
- Immutable audit records (no updates/deletes)

**3. Sensitive Operations:**
```php
// Require reason for all financial exceptions
$request->validate([
    'reason' => 'required|string|min:10|max:500',
]);

// Log to dedicated audit channel
Log::channel('financial_audit')->info('Charge waived', [
    'charge_id' => $charge->id,
    'amount' => $charge->amount,
    'user_id' => auth()->id(),
    'user_name' => auth()->user()->name,
    'reason' => $reason,
    'ip_address' => request()->ip(),
    'timestamp' => now(),
]);
```

### Data Validation

**1. Amount Validation:**
```php
// Prevent negative amounts
'amount' => 'required|numeric|min:0',

// Prevent adjustment exceeding charge
'adjustment_value' => [
    'required',
    'numeric',
    'min:0',
    Rule::when(
        $request->adjustment_type === 'discount_fixed',
        ['max:' . $charge->amount]
    ),
],
```

**2. Status Validation:**
```php
// Only allow operations on pending charges
if (!$charge->isPending()) {
    abort(422, 'Cannot modify a charge that is not pending');
}

// Prevent double payment
if ($charge->isPaid()) {
    abort(422, 'Charge has already been paid');
}
```

### Rate Limiting

```php
// In routes/billing.php
Route::middleware(['throttle:60,1'])->group(function () {
    Route::post('/charges/{charge}/waive', ...);
    Route::post('/charges/{charge}/adjust', ...);
    Route::post('/checkin/{checkin}/override', ...);
});
```

### CSRF Protection

All POST/PUT/DELETE requests require CSRF token (handled by Laravel/Inertia automatically).

### SQL Injection Prevention

Use Eloquent ORM and parameter binding for all queries - never concatenate user input.

### XSS Prevention

All user input is escaped in React components automatically. For reasons/notes, use:
```typescript
<p className="whitespace-pre-wrap">{sanitize(reason)}</p>
```


## Future Enhancements

### Potential Additions (Not in Current Scope)

1. **Bulk Operations:**
   - Waive multiple charges at once
   - Apply discount to all charges for a patient
   - Bulk override for multiple services

2. **Payment Plans:**
   - Set up installment payment schedules
   - Automatic reminders for upcoming payments
   - Track payment plan compliance

3. **Advanced Reporting:**
   - Waiver trends by department
   - Override frequency analysis
   - Revenue impact of adjustments
   - Staff performance metrics

4. **Mobile App Integration:**
   - Patient can view bills on mobile
   - Mobile payment processing
   - Push notifications for payment reminders

5. **Integration with External Systems:**
   - Insurance company APIs for real-time verification
   - Mobile money payment gateways
   - Bank transfer integration
   - SMS payment confirmations

6. **AI/ML Features:**
   - Predict payment likelihood
   - Suggest payment plans based on patient history
   - Detect unusual waiver patterns
   - Fraud detection

7. **Enhanced Audit Features:**
   - Visual audit trail timeline
   - Export audit reports to PDF
   - Real-time alerts for high-value waivers
   - Approval workflows for large adjustments

8. **Patient Portal:**
   - Patients can view their bills online
   - Request payment plans
   - Make online payments
   - Download receipts

## Conclusion

This design provides a comprehensive approach to enhancing the billing UX while maintaining all existing functionality. The single-page flow reduces clicks by 60-70%, while new features (waivers, adjustments, centralized overrides) provide better financial control. The permission system ensures only authorized users can perform sensitive operations, and the audit trail maintains accountability.

The implementation follows Laravel and React best practices, uses existing HMS patterns, and is designed for maintainability and future extensibility.

