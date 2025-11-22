---
inclusion: manual
---

# HMS Insurance Module - Detailed Guide

> **📝 Recent Update (Nov 2024)**: Added support for tariff amounts and patient copay amounts in coverage rules. See "Tariff & Co-pay System" section for details.

## Overview

The insurance module handles insurance provider management, plan configuration, coverage rules, claims processing, and reporting. It integrates deeply with billing, pharmacy, and laboratory modules.

The module has been simplified to provide a streamlined user experience with:
- **Analytics Dashboard** - Single page with expandable report widgets
- **Unified Coverage Management** - Consolidated interface for rules, exceptions, and tariffs
- **Slide-over Claims Vetting** - Quick claim review without navigation
- **Flattened Navigation** - Direct access from Plans list to Coverage Management
- **Smart Defaults** - Auto-created 80% coverage rules for new plans

## Core Concepts

### Insurance Hierarchy

```
Insurance Provider (e.g., NHIS, Glico)
└── Insurance Plans (e.g., NHIS Standard, Corporate Premium)
    └── Coverage Rules (Category defaults + Item exceptions)
        └── Coverage Categories:
            - consultation
            - drugs
            - labs
            - procedures
            - diagnostics
            - consumables
```

### Coverage Determination Logic

Coverage is determined in this order:

1. **Item-Specific Exception** - Highest priority
   - Specific drug/lab/procedure with custom coverage
   - Example: Paracetamol 500mg covered at 100%

2. **Category Default** - Fallback
   - Default coverage for entire category
   - Example: All drugs covered at 80%

3. **No Coverage** - Default
   - If no rules exist, coverage is 0%

### Tariff & Co-pay System

The system supports flexible pricing with tariffs and co-payments:

**Tariff Amount** - Insurance negotiated price (optional)
- Stored in `insurance_coverage_rules.tariff_amount`
- If not set, uses standard hospital price
- Overrides tariff table if specified

**Patient Co-pay Amount** - Fixed additional charge (optional)
- Stored in `insurance_coverage_rules.patient_copay_amount`
- Applied per quantity (e.g., KES 2 per tablet)
- Added to percentage-based payment

**Calculation Examples:**

```php
// Scenario 1: Tariff + Fixed Copay
// Standard: GHS 20, Tariff: GHS 10, Coverage: 100%, Copay: GHS 15
// Insurance pays: GHS 10, Patient pays: GHS 15, Hospital gets: GHS 25

// Scenario 2: Standard Price Split
// Standard: GHS 20, Tariff: null, Coverage: 80%
// Insurance pays: GHS 16 (80%), Patient pays: GHS 4 (20%), Hospital gets: GHS 20

// Scenario 3: Standard + Percentage + Copay
// Standard: GHS 20, Tariff: null, Coverage: 80%, Copay: GHS 5
// Insurance pays: GHS 16 (80%), Patient pays: GHS 4 (20%) + GHS 5 = GHS 9, Hospital gets: GHS 25
```

## Database Schema

### Core Tables

```sql
insurance_providers
├── id, name, code, contact_person, phone, email
├── claim_submission_method (manual, api, portal)
├── payment_terms_days
└── is_active

insurance_plans
├── id, insurance_provider_id
├── plan_name, plan_code, plan_type
├── coverage_type (inpatient, outpatient, comprehensive)
├── annual_limit, visit_limit
├── default_copay_percentage
├── require_explicit_approval_for_new_items
└── effective_from, effective_to

insurance_coverage_rules
├── id, insurance_plan_id
├── coverage_category (enum)
├── item_code, item_description (NULL for category defaults)
├── is_covered, coverage_type (percentage, fixed_amount)
├── coverage_value
├── tariff_amount (NEW - insurance negotiated price, nullable)
├── patient_copay_percentage (calculated from coverage_value)
├── patient_copay_amount (NEW - fixed copay per unit, default 0)
├── max_quantity_per_visit, max_amount_per_visit
├── requires_preauthorization
└── effective_from, effective_to

insurance_tariffs (LEGACY - tariff_amount in rules preferred)
├── id, insurance_plan_id
├── item_type (drug, lab, procedure, consultation)
├── item_code, item_description
├── standard_price, insurance_tariff
└── effective_from, effective_to

patient_insurance
├── id, patient_id, insurance_plan_id
├── membership_id, policy_number, folder_id_prefix
├── is_dependent, principal_member_name
├── relationship_to_principal
├── coverage_start_date, coverage_end_date
└── status (active, suspended, expired)

insurance_claims
├── id, claim_check_code, folder_id
├── patient_id, patient_insurance_id
├── patient_checkin_id, consultation_id, patient_admission_id
├── patient demographics (surname, other_names, dob, gender)
├── membership_id, date_of_attendance, date_of_discharge
├── type_of_service (outpatient, inpatient, emergency)
├── type_of_attendance (new, review, referral)
├── primary_diagnosis_code, secondary_diagnoses (JSON)
├── c_drg_code, hin_number
├── total_claim_amount, approved_amount
├── patient_copay_amount, insurance_covered_amount
├── status (draft, pending_vetting, vetted, submitted, approved, rejected, paid)
├── vetted_by, vetted_at, submitted_by, submitted_at
└── rejection_reason, notes

insurance_claim_items
├── id, insurance_claim_id, charge_id
├── item_date, item_type (consultation, drug, lab, procedure)
├── code, description, quantity
├── unit_tariff, subtotal
├── is_covered, coverage_percentage
├── insurance_pays, patient_pays
├── is_approved, rejection_reason
└── notes
```

## Coverage Management

### Creating Coverage Rules

#### Category Default (applies to all items in category)

```php
InsuranceCoverageRule::create([
    'insurance_plan_id' => $planId,
    'coverage_category' => 'drugs',
    'item_code' => null,              // NULL = category default
    'item_description' => null,
    'is_covered' => true,
    'coverage_type' => 'percentage',
    'coverage_value' => 80.00,        // 80% coverage (patient pays 20%)
    'tariff_amount' => null,          // Use standard price
    'patient_copay_amount' => 0,      // No fixed copay
    'is_active' => true,
]);
```

#### Item-Specific Exception with Tariff and Copay

```php
InsuranceCoverageRule::create([
    'insurance_plan_id' => $planId,
    'coverage_category' => 'drugs',
    'item_code' => 'DRG001',          // Specific drug code
    'item_description' => 'Paracetamol 500mg',
    'is_covered' => true,
    'coverage_type' => 'percentage',
    'coverage_value' => 100.00,       // 100% coverage
    'tariff_amount' => 10.00,         // Insurance negotiated price (GHS 10)
    'patient_copay_amount' => 15.00,  // Fixed copay per unit (GHS 15)
    'max_quantity_per_visit' => 20,   // Max 20 tablets per visit
    'is_active' => true,
]);
// Result: Insurance pays GHS 10, Patient pays GHS 15, Hospital gets GHS 25
```

### Coverage Determination Service

```php
class InsuranceCoverageService
{
    public function determineCoverage(
        int $planId,
        string $category,
        string $itemCode,
        float $amount
    ): array {
        // 1. Check for item-specific exception
        $exception = InsuranceCoverageRule::where('insurance_plan_id', $planId)
            ->where('coverage_category', $category)
            ->where('item_code', $itemCode)
            ->where('is_active', true)
            ->first();

        if ($exception) {
            return $this->calculateCoverage($exception, $amount);
        }

        // 2. Fall back to category default
        $default = InsuranceCoverageRule::where('insurance_plan_id', $planId)
            ->where('coverage_category', $category)
            ->whereNull('item_code')
            ->where('is_active', true)
            ->first();

        if ($default) {
            return $this->calculateCoverage($default, $amount);
        }

        // 3. No coverage
        return [
            'is_covered' => false,
            'coverage_percentage' => 0,
            'insurance_pays' => 0,
            'patient_pays' => $amount,
        ];
    }

    private function calculateCoverage($rule, $amount, $quantity = 1): array
    {
        if (!$rule->is_covered) {
            return [
                'is_covered' => false,
                'coverage_percentage' => 0,
                'insurance_pays' => 0,
                'patient_pays' => $amount * $quantity,
            ];
        }

        // Determine effective price (tariff or standard)
        $effectivePrice = $rule->tariff_amount ?? $amount;
        $subtotal = $effectivePrice * $quantity;

        // Calculate insurance payment based on coverage percentage
        $coveragePercentage = $rule->coverage_value;
        $insurancePays = $subtotal * ($coveragePercentage / 100);
        
        // Calculate patient payment (percentage + fixed copay)
        $patientPercentagePayment = $subtotal - $insurancePays;
        $patientFixedCopay = ($rule->patient_copay_amount ?? 0) * $quantity;
        $patientPays = $patientPercentagePayment + $patientFixedCopay;

        // Apply max amount limit if set
        if ($rule->max_amount_per_visit && $insurancePays > $rule->max_amount_per_visit) {
            $insurancePays = $rule->max_amount_per_visit;
            $patientPays = $subtotal - $insurancePays + $patientFixedCopay;
        }

        return [
            'is_covered' => true,
            'coverage_percentage' => $coveragePercentage,
            'insurance_pays' => $insurancePays,
            'patient_pays' => $patientPays,
        ];
    }
}
```

### Excel Import for Coverage Rules

Coverage rules can be bulk imported via Excel templates:

**Excel Format:**

```
item_code | item_name   | current_price | coverage_type | coverage_value | tariff_amount | patient_copay_amount | notes
----------|-------------|---------------|---------------|----------------|---------------|---------------------|-------
PMOL      | Paracetamol | 20           | percentage    | 100           | 10            | 15                  | Tariff + copay
AMX500    | Amoxicillin | 20           | percentage    | 80            |               | 0                   | Standard split
MOR001    | Morphine    | 20           | percentage    | 80            |               | 5                   | Split + copay
```

**Column Descriptions:**

- `item_code` - Drug/lab/service code (required)
- `item_name` - Item description (optional)
- `current_price` - Hospital standard price (for reference only)
- `coverage_type` - percentage, fixed_amount, full, or excluded (required)
- `coverage_value` - Percentage (0-100) or fixed amount (required)
- `tariff_amount` - Insurance negotiated price (optional - leave empty to use standard price)
- `patient_copay_amount` - Fixed copay per unit (optional - leave empty for 0)
- `notes` - Additional notes (optional)

**Import Process:**

```php
// Download template
GET /admin/insurance/plans/{plan}/coverage/template/{category}

// Import file
POST /admin/insurance/plans/{plan}/coverage/import
{
    'file' => $excelFile,
    'category' => 'drug'
}
```

**Controller:** `InsuranceCoverageImportController`

## Claims Processing Workflow

### 1. Claim Creation (Automatic)

Claims are created automatically when a patient with insurance checks in:

```php
// When patient checks in with insurance
if ($patient->activeInsurance) {
    $claim = InsuranceClaim::create([
        'claim_check_code' => $this->generateClaimCheckCode(),
        'patient_id' => $patient->id,
        'patient_insurance_id' => $patient->activeInsurance->id,
        'patient_checkin_id' => $checkin->id,
        'membership_id' => $patient->activeInsurance->membership_id,
        'date_of_attendance' => now(),
        'status' => 'draft',
    ]);
}
```

### 2. Charge Creation with Insurance

When services are provided, charges are created with insurance calculation:

```php
event(new PrescriptionCreated($prescription));

// Listener
class CreateMedicationCharge
{
    public function handle(PrescriptionCreated $event)
    {
        $prescription = $event->prescription;
        $checkin = $prescription->consultation->patientCheckin;
        $insurance = $checkin->patient->activeInsurance;

        $amount = $prescription->drug->unit_price * $prescription->quantity;

        // Get insurance tariff if exists
        $tariff = InsuranceTariff::where('insurance_plan_id', $insurance->insurance_plan_id)
            ->where('item_type', 'drug')
            ->where('item_code', $prescription->drug->drug_code)
            ->first();

        $tariffAmount = $tariff ? $tariff->insurance_tariff : $amount;

        // Determine coverage
        $coverage = app(InsuranceCoverageService::class)->determineCoverage(
            $insurance->insurance_plan_id,
            'drugs',
            $prescription->drug->drug_code,
            $tariffAmount
        );

        // Create charge
        $charge = Charge::create([
            'patient_checkin_id' => $checkin->id,
            'prescription_id' => $prescription->id,
            'service_type' => 'medication',
            'service_code' => $prescription->drug->drug_code,
            'description' => $prescription->drug->name,
            'amount' => $amount,
            'insurance_tariff_amount' => $tariffAmount,
            'is_insurance_claim' => true,
            'insurance_covered_amount' => $coverage['insurance_pays'],
            'patient_copay_amount' => $coverage['patient_pays'],
            'status' => 'pending',
        ]);

        // Create claim item
        InsuranceClaimItem::create([
            'insurance_claim_id' => $checkin->insuranceClaim->id,
            'charge_id' => $charge->id,
            'item_type' => 'drug',
            'code' => $prescription->drug->drug_code,
            'description' => $prescription->drug->name,
            'quantity' => $prescription->quantity,
            'unit_tariff' => $tariffAmount / $prescription->quantity,
            'subtotal' => $tariffAmount,
            'is_covered' => $coverage['is_covered'],
            'coverage_percentage' => $coverage['coverage_percentage'],
            'insurance_pays' => $coverage['insurance_pays'],
            'patient_pays' => $coverage['patient_pays'],
        ]);
    }
}
```

### 3. Claims Vetting

Claims must be vetted before submission:

```php
public function vet(Request $request, InsuranceClaim $claim)
{
    $this->authorize('vet', $claim);

    $validated = $request->validate([
        'action' => 'required|in:approve,reject',
        'rejection_reason' => 'required_if:action,reject',
        'notes' => 'nullable|string',
    ]);

    if ($validated['action'] === 'approve') {
        $claim->update([
            'status' => 'vetted',
            'vetted_by' => auth()->id(),
            'vetted_at' => now(),
            'notes' => $validated['notes'] ?? null,
        ]);
    } else {
        $claim->update([
            'status' => 'rejected',
            'vetted_by' => auth()->id(),
            'vetted_at' => now(),
            'rejection_reason' => $validated['rejection_reason'],
        ]);
    }

    return redirect()->back()->with('success', 'Claim vetted successfully');
}
```

### 4. Claim Submission

Vetted claims can be submitted individually or in batches:

```php
public function submit(Request $request)
{
    $this->authorize('submit', InsuranceClaim::class);

    $validated = $request->validate([
        'claim_ids' => 'required|array',
        'claim_ids.*' => 'exists:insurance_claims,id',
        'batch_reference' => 'nullable|string',
    ]);

    $claims = InsuranceClaim::whereIn('id', $validated['claim_ids'])
        ->where('status', 'vetted')
        ->get();

    foreach ($claims as $claim) {
        $claim->update([
            'status' => 'submitted',
            'submitted_by' => auth()->id(),
            'submitted_at' => now(),
            'submission_date' => now()->toDateString(),
            'batch_reference' => $validated['batch_reference'] ?? null,
        ]);
    }

    return redirect()->back()->with('success', count($claims) . ' claims submitted');
}
```

## Frontend Patterns

### Unified Coverage Management

The Coverage Management interface consolidates coverage rules, exceptions, and tariffs:

**Component:** `resources/js/Pages/Admin/Insurance/Plans/CoverageManagement.tsx`

```tsx
<div className="space-y-6">
    {/* Global Search */}
    <div className="flex items-center gap-4">
        <Input
            placeholder="Search across all categories..."
            value={searchTerm}
            onChange={(e) => setSearchTerm(e.target.value)}
        />
        <Button onClick={() => setBulkImportOpen(true)}>
            Bulk Import
        </Button>
        <Button variant="outline" onClick={handleExport}>
            Export
        </Button>
    </div>

    {/* Category Cards */}
    <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        {categories.map(category => (
            <Card key={category.name}>
                <CardHeader>
                    <CardTitle>{category.label}</CardTitle>
                    <Badge variant={getCoverageColor(category.default_coverage)}>
                        {category.default_coverage}% default
                    </Badge>
                </CardHeader>
                <CardContent>
                    <p>{category.exception_count} exceptions</p>
                    <Button onClick={() => expandCategory(category)}>
                        View Details
                    </Button>
                </CardContent>
            </Card>
        ))}
    </div>

    {/* Expanded Card Content */}
    {expandedCategory && (
        <Card>
            <CardHeader>
                <CardTitle>{expandedCategory.label} - Coverage Rules</CardTitle>
            </CardHeader>
            <CardContent>
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Item Code</TableHead>
                            <TableHead>Description</TableHead>
                            <TableHead>Coverage</TableHead>
                            <TableHead>Tariff</TableHead>
                            <TableHead>Actions</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow>
                            <TableCell>DEFAULT</TableCell>
                            <TableCell>All {expandedCategory.label}</TableCell>
                            <TableCell>
                                <InlinePercentageEdit
                                    value={expandedCategory.default_coverage}
                                    onSave={handleUpdateDefault}
                                />
                            </TableCell>
                            <TableCell>Standard</TableCell>
                            <TableCell>-</TableCell>
                        </TableRow>
                        {expandedCategory.exceptions.map(exception => (
                            <TableRow key={exception.id}>
                                <TableCell>{exception.item_code}</TableCell>
                                <TableCell>{exception.item_description}</TableCell>
                                <TableCell>{exception.coverage_value}%</TableCell>
                                <TableCell>
                                    {exception.tariff_price 
                                        ? `GHS ${exception.tariff_price}` 
                                        : 'Standard'}
                                </TableCell>
                                <TableCell>
                                    <Button size="sm" onClick={() => editException(exception)}>
                                        Edit
                                    </Button>
                                </TableCell>
                            </TableRow>
                        ))}
                    </TableBody>
                </Table>
                <Button onClick={() => setAddExceptionOpen(true)}>
                    Add Exception
                </Button>
            </CardContent>
        </Card>
    )}
</div>
```

### Add Exception Modal with Tariff Support

**Component:** `resources/js/components/Insurance/AddExceptionModal.tsx`

```tsx
<Dialog open={isOpen} onOpenChange={setIsOpen}>
    <DialogContent>
        <DialogHeader>
            <DialogTitle>Add Coverage Exception</DialogTitle>
        </DialogHeader>
        
        <div className="space-y-4">
            {/* Item Search */}
            <div>
                <Label>Search for Item</Label>
                <Input
                    placeholder="Type item name or code..."
                    value={searchTerm}
                    onChange={(e) => setSearchTerm(e.target.value)}
                />
                {/* Search results dropdown */}
            </div>

            {/* Coverage Type */}
            <div>
                <Label>Coverage Type</Label>
                <RadioGroup value={coverageType} onValueChange={setCoverageType}>
                    <RadioGroupItem value="percentage">Percentage</RadioGroupItem>
                    <RadioGroupItem value="fixed">Fixed Amount</RadioGroupItem>
                    <RadioGroupItem value="full">Fully Covered</RadioGroupItem>
                    <RadioGroupItem value="excluded">Not Covered</RadioGroupItem>
                </RadioGroup>
            </div>

            {/* Coverage Value */}
            {coverageType === 'percentage' && (
                <div>
                    <Label>Coverage Percentage</Label>
                    <Input
                        type="number"
                        min="0"
                        max="100"
                        value={coverageValue}
                        onChange={(e) => setCoverageValue(e.target.value)}
                    />
                </div>
            )}

            {/* Tariff Pricing (NEW) */}
            <div>
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
            </div>

            {/* Preview */}
            <div className="bg-muted p-4 rounded">
                <h4>Preview</h4>
                <p>Standard Price: GHS {standardPrice}</p>
                {pricingType === 'custom' && (
                    <p>Tariff Price: GHS {tariffPrice}</p>
                )}
                <p>Insurance pays: GHS {insurancePays} ({coverageValue}%)</p>
                <p>Patient pays: GHS {patientPays}</p>
            </div>

            {/* Notes */}
            <div>
                <Label>Notes (Optional)</Label>
                <Textarea
                    placeholder="Explain why this exception exists..."
                    value={notes}
                    onChange={(e) => setNotes(e.target.value)}
                />
            </div>
        </div>

        <DialogFooter>
            <Button variant="outline" onClick={() => setIsOpen(false)}>
                Cancel
            </Button>
            <Button onClick={handleSave}>
                Add Exception
            </Button>
        </DialogFooter>
    </DialogContent>
</Dialog>
```

### Claims Vetting Slide-Over Panel

**Component:** `resources/js/components/Insurance/ClaimsVettingPanel.tsx`

```tsx
<Sheet open={isOpen} onOpenChange={setIsOpen}>
    <SheetContent className="w-full sm:max-w-2xl overflow-y-auto">
        <SheetHeader>
            <SheetTitle>Review Claim {claim.claim_check_code}</SheetTitle>
        </SheetHeader>
        
        <div className="space-y-6 py-4">
            {/* Patient Info */}
            <div>
                <h3 className="font-semibold mb-2">Patient Information</h3>
                <dl className="grid grid-cols-2 gap-2 text-sm">
                    <dt className="text-muted-foreground">Name:</dt>
                    <dd>{claim.patient_surname} {claim.patient_other_names}</dd>
                    <dt className="text-muted-foreground">Membership:</dt>
                    <dd>{claim.membership_id}</dd>
                    <dt className="text-muted-foreground">Insurance:</dt>
                    <dd>{claim.insurance_plan_name}</dd>
                    <dt className="text-muted-foreground">Visit Date:</dt>
                    <dd>{formatDate(claim.date_of_attendance)}</dd>
                </dl>
            </div>

            {/* Diagnosis */}
            <div>
                <h3 className="font-semibold mb-2">Diagnosis</h3>
                <p className="text-sm">
                    {claim.primary_diagnosis_code}: {claim.primary_diagnosis_description}
                </p>
            </div>

            {/* Claim Items */}
            <div>
                <h3 className="font-semibold mb-2">Claim Items</h3>
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Item</TableHead>
                            <TableHead>Qty</TableHead>
                            <TableHead>Tariff</TableHead>
                            <TableHead>Coverage</TableHead>
                            <TableHead>Insurance Pays</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {claim.items.map(item => (
                            <TableRow key={item.id}>
                                <TableCell>{item.description}</TableCell>
                                <TableCell>{item.quantity}</TableCell>
                                <TableCell>GHS {item.unit_tariff}</TableCell>
                                <TableCell>{item.coverage_percentage}%</TableCell>
                                <TableCell>GHS {item.insurance_pays}</TableCell>
                            </TableRow>
                        ))}
                    </TableBody>
                </Table>
            </div>

            {/* Financial Summary */}
            <div className="bg-muted p-4 rounded">
                <h3 className="font-semibold mb-2">Financial Summary</h3>
                <dl className="grid grid-cols-2 gap-2 text-sm">
                    <dt>Total Claim Amount:</dt>
                    <dd className="font-semibold">GHS {claim.total_claim_amount}</dd>
                    <dt>Insurance Covered:</dt>
                    <dd className="text-green-600">GHS {claim.insurance_covered_amount}</dd>
                    <dt>Patient Copay:</dt>
                    <dd className="text-orange-600">GHS {claim.patient_copay_amount}</dd>
                </dl>
            </div>

            {/* Vetting Actions */}
            <div className="flex gap-2">
                <Button 
                    className="flex-1" 
                    onClick={() => handleVet('approve')}
                    disabled={processing}
                >
                    Approve Claim
                </Button>
                <Button 
                    className="flex-1" 
                    variant="destructive" 
                    onClick={() => setShowRejectDialog(true)}
                    disabled={processing}
                >
                    Reject Claim
                </Button>
            </div>
        </div>
    </SheetContent>
</Sheet>

{/* Rejection Dialog */}
<Dialog open={showRejectDialog} onOpenChange={setShowRejectDialog}>
    <DialogContent>
        <DialogHeader>
            <DialogTitle>Reject Claim</DialogTitle>
        </DialogHeader>
        <div>
            <Label>Rejection Reason</Label>
            <Textarea
                placeholder="Explain why this claim is being rejected..."
                value={rejectionReason}
                onChange={(e) => setRejectionReason(e.target.value)}
                required
            />
        </div>
        <DialogFooter>
            <Button variant="outline" onClick={() => setShowRejectDialog(false)}>
                Cancel
            </Button>
            <Button 
                variant="destructive" 
                onClick={() => handleVet('reject')}
                disabled={!rejectionReason.trim()}
            >
                Confirm Rejection
            </Button>
        </DialogFooter>
    </DialogContent>
</Dialog>
```

### Analytics Dashboard with Expandable Widgets

**Component:** `resources/js/Pages/Admin/Insurance/Reports/Index.tsx`

```tsx
<div className="space-y-6">
    {/* Date Range Filter */}
    <Card>
        <CardContent className="pt-6">
            <div className="flex items-center gap-4">
                <div>
                    <Label>From</Label>
                    <Input
                        type="date"
                        value={dateRange.from}
                        onChange={(e) => setDateRange({...dateRange, from: e.target.value})}
                    />
                </div>
                <div>
                    <Label>To</Label>
                    <Input
                        type="date"
                        value={dateRange.to}
                        onChange={(e) => setDateRange({...dateRange, to: e.target.value})}
                    />
                </div>
                <Button onClick={handleApplyDateRange}>Apply</Button>
            </div>
        </CardContent>
    </Card>

    {/* Report Widgets */}
    <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        <AnalyticsWidget
            title="Claims Summary"
            icon={FileText}
            color="blue"
            endpoint="/api/insurance/reports/claims-summary"
            dateRange={dateRange}
            renderSummary={(data) => (
                <div>
                    <p className="text-2xl font-bold">{data.total_claims}</p>
                    <p className="text-sm text-muted-foreground">Total Claims</p>
                </div>
            )}
            renderDetails={(data) => (
                <div className="space-y-4">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Status</TableHead>
                                <TableHead>Count</TableHead>
                                <TableHead>Amount</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {data.by_status.map(status => (
                                <TableRow key={status.status}>
                                    <TableCell>{status.status}</TableCell>
                                    <TableCell>{status.count}</TableCell>
                                    <TableCell>GHS {status.amount}</TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </div>
            )}
        />

        {/* Additional widgets for other reports */}
        <AnalyticsWidget title="Revenue Analysis" {...} />
        <AnalyticsWidget title="Outstanding Claims" {...} />
        <AnalyticsWidget title="Vetting Performance" {...} />
        <AnalyticsWidget title="Utilization Report" {...} />
        <AnalyticsWidget title="Rejection Analysis" {...} />
    </div>
</div>
```

### Plans List with Quick Actions

**Component:** `resources/js/Pages/Admin/Insurance/Plans/Index.tsx`

```tsx
<Table>
    <TableHeader>
        <TableRow>
            <TableHead>Plan Name</TableHead>
            <TableHead>Provider</TableHead>
            <TableHead>Type</TableHead>
            <TableHead>Status</TableHead>
            <TableHead>Actions</TableHead>
        </TableRow>
    </TableHeader>
    <TableBody>
        {plans.map(plan => (
            <TableRow key={plan.id}>
                <TableCell>
                    <Link href={`/admin/insurance/plans/${plan.id}`}>
                        {plan.plan_name}
                    </Link>
                </TableCell>
                <TableCell>{plan.provider_name}</TableCell>
                <TableCell>{plan.plan_type}</TableCell>
                <TableCell>
                    <Badge variant={plan.is_active ? 'success' : 'secondary'}>
                        {plan.is_active ? 'Active' : 'Inactive'}
                    </Badge>
                </TableCell>
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
            </TableRow>
        ))}
    </TableBody>
</Table>
```

## Reporting

### Claims Summary Report

```php
public function claimsSummary(Request $request)
{
    $startDate = $request->input('start_date', now()->startOfMonth());
    $endDate = $request->input('end_date', now()->endOfMonth());

    $summary = InsuranceClaim::whereBetween('date_of_attendance', [$startDate, $endDate])
        ->selectRaw('
            status,
            COUNT(*) as claim_count,
            SUM(total_claim_amount) as total_amount,
            SUM(approved_amount) as approved_amount,
            SUM(patient_copay_amount) as patient_copay,
            SUM(insurance_covered_amount) as insurance_covered
        ')
        ->groupBy('status')
        ->get();

    return Inertia::render('Insurance/Reports/ClaimsSummary', [
        'summary' => $summary,
        'startDate' => $startDate,
        'endDate' => $endDate,
    ]);
}
```

## Common Scenarios

### Scenario 1: Patient with 80% Drug Coverage

```
Drug: Paracetamol 500mg x 20 tablets
Standard Price: $10
Insurance Tariff: $8
Coverage: 80%

Calculation:
- Insurance pays: $8 × 80% = $6.40
- Patient pays: $8 × 20% = $1.60
```

### Scenario 2: Drug with Exception (100% Coverage)

```
Drug: Insulin (exception rule)
Standard Price: $50
Insurance Tariff: $45
Coverage: 100% (exception)

Calculation:
- Insurance pays: $45 × 100% = $45.00
- Patient pays: $0.00
```

### Scenario 3: Uncovered Item

```
Drug: Cosmetic cream (not covered)
Standard Price: $30
Coverage: 0% (no rule exists)

Calculation:
- Insurance pays: $0.00
- Patient pays: $30.00 (full amount)
```

## Smart Defaults for New Plans

When creating a new insurance plan, the system automatically creates default coverage rules at 80% for all six categories:

```php
// In InsurancePlanController@store
DB::transaction(function () use ($validated) {
    $plan = InsurancePlan::create($validated);
    
    // Auto-create default coverage rules
    $categories = ['consultation', 'drug', 'lab', 'procedure', 'ward', 'nursing'];
    foreach ($categories as $category) {
        InsuranceCoverageRule::create([
            'insurance_plan_id' => $plan->id,
            'coverage_category' => $category,
            'item_code' => null,  // NULL = category default
            'coverage_type' => 'percentage',
            'coverage_value' => 80.00,
            'patient_copay_percentage' => 20.00,
            'is_covered' => true,
            'is_active' => true,
        ]);
    }
    
    return $plan;
});
```

This reduces plan setup time from 10 minutes to under 2 minutes.

## Best Practices

### 1. Always Use Coverage Service

Don't calculate coverage manually - use the service:

```php
// ✅ Correct
$coverage = app(InsuranceCoverageService::class)->determineCoverage(
    $planId, $category, $itemCode, $amount
);

// ❌ Wrong
$insurancePays = $amount * 0.8; // Hardcoded
```

### 2. Integrate Tariffs with Exceptions

Set custom tariffs when adding exceptions:

```php
// When creating exception with custom tariff
InsuranceCoverageRule::create([
    'insurance_plan_id' => $planId,
    'coverage_category' => 'drugs',
    'item_code' => 'DRG001',
    'coverage_value' => 100.00,
    // ... other fields
]);

// Create associated tariff
InsuranceTariff::create([
    'insurance_plan_id' => $planId,
    'item_type' => 'drug',
    'item_code' => 'DRG001',
    'standard_price' => 50.00,
    'insurance_tariff' => 40.00,  // Negotiated rate
    'effective_from' => now(),
]);
```

### 3. Use Slide-Over Panels for Quick Actions

For actions that don't require full page navigation, use slide-over panels:

```tsx
// ✅ Correct - Slide-over for quick review
<ClaimsVettingPanel 
    claimId={selectedClaimId}
    isOpen={isPanelOpen}
    onClose={() => setIsPanelOpen(false)}
    onVetSuccess={refreshClaimsList}
/>

// ❌ Wrong - Full page navigation for simple action
router.visit(`/admin/insurance/claims/${claimId}/vet`);
```

### 4. Lazy Load Widget Details

For analytics dashboards, lazy load detailed data:

```tsx
// ✅ Correct - Load details only when expanded
const [isExpanded, setIsExpanded] = useState(false);
const [details, setDetails] = useState(null);

useEffect(() => {
    if (isExpanded && !details) {
        fetchWidgetDetails().then(setDetails);
    }
}, [isExpanded]);

// ❌ Wrong - Load all details upfront
useEffect(() => {
    fetchAllWidgetDetails(); // Loads data for all widgets
}, []);
```

### 5. Validate Claims Before Submission

Ensure all required fields are present:

```php
public function canSubmit(InsuranceClaim $claim): bool
{
    return $claim->status === 'vetted'
        && $claim->primary_diagnosis_code
        && $claim->items->count() > 0
        && $claim->total_claim_amount > 0;
}
```

## Troubleshooting

### Issue: Coverage not applying correctly

**Check:**
1. Is there an active insurance plan for the patient?
2. Is the coverage rule active and within effective dates?
3. Is the item code matching exactly?
4. Is there a category default if no exception exists?

### Issue: Claim submission failing

**Check:**
1. Is the claim status 'vetted'?
2. Are all required fields populated?
3. Does the claim have at least one item?
4. Is the primary diagnosis code valid?

### Issue: Tariff not being used

**Check:**
1. Is the tariff active and within effective dates?
2. Does the item_code match exactly?
3. Is the item_type correct (drug, lab, procedure)?

## Navigation Patterns

### Flattened Navigation

The simplified navigation reduces clicks from 5 to 3 for common workflows:

**Old Flow (5 clicks):**
```
Plans List → Plan Details → Coverage Tab → Category Card → Add Exception
```

**New Flow (3 clicks):**
```
Plans List → [Manage Coverage Button] → Add Exception
```

**Implementation:**

```tsx
// Plans List with Quick Actions
<TableCell>
    <div className="flex gap-2">
        <Link href={`/admin/insurance/plans/${plan.id}/coverage`}>
            <Button size="sm" variant="outline">
                Manage Coverage
            </Button>
        </Link>
        <Link href={`/admin/insurance/claims?plan_id=${plan.id}`}>
            <Button size="sm" variant="outline">
                View Claims
            </Button>
        </Link>
    </div>
</TableCell>
```

### Menu Structure

The insurance menu is organized into five sections:

1. **Providers** - Manage insurance companies
2. **Plans** - View and configure insurance plans
3. **Coverage** - Accessed via Plans (not separate menu item)
4. **Claims** - Process and vet insurance claims
5. **Analytics** - View reports and metrics (renamed from "Reports")

Removed menu items:
- Coverage Rules (merged into Coverage Management)
- Tariffs (integrated into exception workflow)

## Component Documentation

### New Components

#### AnalyticsWidget

**Location:** `resources/js/components/Insurance/AnalyticsWidget.tsx`

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

**Usage:**
- Displays collapsed summary by default
- Expands inline to show detailed data
- Lazy loads details only when expanded
- Includes skeleton loading states

#### ClaimsVettingPanel

**Location:** `resources/js/components/Insurance/ClaimsVettingPanel.tsx`

**Props:**
```typescript
interface ClaimsVettingPanelProps {
    claimId: number | null;
    isOpen: boolean;
    onClose: () => void;
    onVetSuccess: () => void;
}
```

**Features:**
- Slide-over panel from right side
- Displays claim details, items, and financial summary
- Approve/reject actions with validation
- Keyboard shortcuts (Escape to close, Ctrl+Enter to approve)
- No page navigation required

### Modified Components

#### CoverageManagement (formerly CoverageDashboard)

**Location:** `resources/js/Pages/Admin/Insurance/Plans/CoverageManagement.tsx`

**Changes:**
- Removed RecentItemsPanel integration
- Removed KeyboardShortcutsHelp
- Removed QuickActionsMenu
- Added global search functionality
- Moved bulk import button to page level
- Added tariff column to exceptions table
- Unified display of rules, exceptions, and tariffs

#### AddExceptionModal

**Location:** `resources/js/components/Insurance/AddExceptionModal.tsx`

**Changes:**
- Added tariff pricing section
- Radio group: "Use Standard Price" vs "Set Custom Tariff"
- Conditional tariff price input field
- Real-time preview with tariff calculations
- Updated API call to include tariff data

## API Endpoints

### Coverage Management

- `GET /admin/insurance/plans/{plan}/coverage` - Get coverage rules and exceptions
- `POST /admin/insurance/coverage-rules` - Create exception (with optional tariff)
- `PUT /admin/insurance/coverage-rules/{rule}` - Update exception
- `DELETE /admin/insurance/coverage-rules/{rule}` - Delete exception

### Claims Vetting

- `GET /admin/insurance/claims/{claim}` - Get claim details (JSON response)
- `POST /admin/insurance/claims/{claim}/vet` - Vet claim (approve/reject)

### Analytics

- `GET /admin/insurance/reports/claims-summary` - Claims summary widget data
- `GET /admin/insurance/reports/revenue-analysis` - Revenue widget data
- `GET /admin/insurance/reports/outstanding-claims` - Outstanding claims widget data
- `GET /admin/insurance/reports/vetting-performance` - Vetting performance widget data
- `GET /admin/insurance/reports/utilization` - Utilization widget data
- `GET /admin/insurance/reports/rejection-analysis` - Rejection analysis widget data

All report endpoints accept `start_date` and `end_date` query parameters.

---

**Remember**: Insurance rules can be complex. Always test coverage calculations thoroughly and maintain clear audit trails for all changes.
