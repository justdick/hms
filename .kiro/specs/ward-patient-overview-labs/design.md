# Design Document

## Overview

This design enhances the ward patient show page by introducing two new tabs: an Overview tab that consolidates key patient information, and a Labs tab for detailed laboratory data. The Overview tab will serve as the landing page, providing healthcare providers with immediate access to critical information. The Labs tab will conditionally appear only when lab orders exist.

## Architecture

### Component Structure

```
WardPatientShow (Page)
├── OverviewTab (New Component)
│   ├── DiagnosisSummaryCard
│   ├── PrescriptionsSummaryCard
│   ├── VitalsSummaryCard
│   └── LabsSummaryCard
├── LabsTab (New Component - Conditional)
│   ├── LabOrdersTable
│   └── LabResultsDisplay
├── VitalsTab (Existing)
├── MedicationsTab (Existing)
├── MedicationHistoryTab (Existing)
├── NotesTab (Existing)
└── RoundsTab (Existing)
```

### Data Flow

1. Backend controller loads patient admission with all related data (consultation, ward rounds, lab orders, vitals, prescriptions)
2. Frontend receives comprehensive admission data via Inertia props
3. Overview tab aggregates and displays summary information from various sources
4. Labs tab conditionally renders based on presence of lab orders
5. Click handlers on overview cards navigate to respective detailed tabs

## Components and Interfaces

### 1. OverviewTab Component

**Purpose**: Display consolidated patient information in an at-a-glance format

**Props**:
```typescript
interface OverviewTabProps {
    admission: PatientAdmission;
    onNavigateToTab: (tabValue: string) => void;
}
```

**Layout**: 
- Grid layout with 4 main cards (2x2 on desktop, stacked on mobile)
- Each card is clickable and navigates to the detailed tab
- Visual indicators for alerts and critical information

**Sub-components**:

#### DiagnosisSummaryCard
- Displays most recent diagnosis from consultation or latest ward round
- Shows diagnosis name, ICD code, and diagnosing physician
- Displays "No diagnosis recorded" if none exists
- Clickable to navigate to Ward Rounds tab

#### PrescriptionsSummaryCard
- Lists active prescriptions (up to 5 most recent)
- Shows medication name, dosage, frequency
- Displays count of total active prescriptions
- Badge showing pending medication count
- Clickable to navigate to Medication Administration tab

#### VitalsSummaryCard
- Displays most recent vital signs with values
- Shows time since last recording
- Visual indicator if vitals are overdue (>4 hours)
- Displays vitals schedule status if configured
- Clickable to navigate to Vital Signs tab

#### LabsSummaryCard
- Shows count of pending, in-progress, and completed labs
- Displays most recent lab result (if any)
- Highlights urgent lab orders
- Displays "No labs ordered" if none exist
- Clickable to navigate to Labs tab (if labs exist)

### 2. LabsTab Component

**Purpose**: Display detailed laboratory orders and results

**Props**:
```typescript
interface LabsTabProps {
    labOrders: LabOrder[];
    admissionId: number;
}
```

**Features**:
- Tabbed sub-navigation for filtering by status (All, Pending, In Progress, Completed)
- Table view with columns: Test Name, Status, Priority, Ordered Date, Ordered By, Results
- Expandable rows to show detailed results and special instructions
- Color-coded status badges
- Highlight abnormal results

**Sub-components**:

#### LabOrdersTable
- Sortable table displaying all lab orders
- Status filter tabs
- Expandable rows for detailed information

#### LabResultsDisplay
- Formatted display of lab result values
- Reference ranges when available
- Visual indicators for abnormal values (high/low)
- Result notes and interpretation

### 3. Tab Navigation Enhancement

**Changes to existing TabsList**:
- Reorder tabs: Overview (new), Vitals, Medications, History, Labs (new, conditional), Notes, Rounds
- Set Overview as default tab (`defaultValue="overview"`)
- Conditionally render Labs tab based on presence of lab orders

## Data Models

### Existing Models (No Changes Required)

The existing TypeScript interfaces already support all required data:
- `PatientAdmission`
- `WardRound` with `diagnoses`, `prescriptions`, `lab_orders`
- `Consultation` with `diagnosis`, `prescriptions`
- `LabOrder` with `lab_service`, `status`, `priority`, `result_values`, `result_notes`
- `VitalSign`
- `MedicationAdministration`

### Computed Data

```typescript
// In WardPatientShow component
const latestDiagnosis = useMemo(() => {
    // Get diagnosis from consultation or most recent ward round
    const wardRoundDiagnoses = admission.ward_rounds
        ?.flatMap(round => round.diagnoses || [])
        .sort((a, b) => b.id - a.id);
    
    return wardRoundDiagnoses?.[0] || 
           (admission.consultation?.diagnosis ? {
               diagnosis_name: admission.consultation.diagnosis,
               icd_code: null,
               diagnosis_type: 'consultation'
           } : null);
}, [admission]);

const allLabOrders = useMemo(() => {
    return admission.ward_rounds
        ?.flatMap(round => round.lab_orders || [])
        .sort((a, b) => new Date(b.ordered_at).getTime() - new Date(a.ordered_at).getTime()) || [];
}, [admission]);

const labOrdersByStatus = useMemo(() => {
    return {
        pending: allLabOrders.filter(lab => lab.status === 'pending'),
        in_progress: allLabOrders.filter(lab => lab.status === 'in_progress'),
        completed: allLabOrders.filter(lab => lab.status === 'completed'),
        cancelled: allLabOrders.filter(lab => lab.status === 'cancelled'),
    };
}, [allLabOrders]);
```

## Error Handling

### Missing Data Scenarios

1. **No Diagnosis**: Display "No diagnosis recorded" with suggestion to complete ward round
2. **No Prescriptions**: Display "No active prescriptions" 
3. **No Vitals**: Display "No vitals recorded" with overdue indicator
4. **No Labs**: Hide Labs tab entirely, show "No labs ordered" in overview
5. **Incomplete Lab Results**: Show "Pending" status with order date

### Navigation Errors

- If user clicks on a summary card but the target tab doesn't exist, show toast notification
- Gracefully handle missing data in detailed views

## Testing Strategy

### Unit Tests

1. Test OverviewTab component rendering with various data states
2. Test DiagnosisSummaryCard with consultation diagnosis vs ward round diagnosis
3. Test PrescriptionsSummaryCard with different prescription counts
4. Test VitalsSummaryCard with overdue vs current vitals
5. Test LabsSummaryCard with various lab order statuses
6. Test LabsTab conditional rendering based on lab orders presence
7. Test LabOrdersTable filtering and sorting
8. Test LabResultsDisplay with normal and abnormal values

### Feature Tests

1. Test navigation from overview cards to detailed tabs
2. Test Labs tab visibility based on lab orders
3. Test default tab is Overview on page load
4. Test lab order status filtering
5. Test visual indicators for alerts (overdue vitals, pending meds, urgent labs)

### Browser Tests

1. Test responsive layout on mobile, tablet, and desktop
2. Test tab navigation and state persistence
3. Test clickable cards and navigation flow
4. Test expandable lab order rows
5. Test dark mode compatibility

## UI/UX Considerations

### Visual Design

- Use card-based layout for overview sections
- Consistent color scheme: 
  - Blue for informational
  - Green for normal/completed
  - Yellow/Orange for warnings
  - Red for critical/urgent
- Icons for each section (Stethoscope for diagnosis, Pill for meds, Heart for vitals, TestTube for labs)
- Hover effects on clickable cards
- Smooth transitions between tabs

### Accessibility

- Proper ARIA labels for all interactive elements
- Keyboard navigation support for tabs and cards
- Screen reader announcements for status changes
- Sufficient color contrast for all text
- Focus indicators for keyboard users

### Performance

- Use React.memo for summary cards to prevent unnecessary re-renders
- Lazy load Labs tab content only when tab is active
- Optimize lab orders table with virtualization if list is very long
- Cache computed values with useMemo

## Implementation Notes

### File Structure

```
resources/js/
├── Pages/Ward/
│   └── PatientShow.tsx (modify)
├── components/Ward/
│   ├── OverviewTab.tsx (new)
│   ├── DiagnosisSummaryCard.tsx (new)
│   ├── PrescriptionsSummaryCard.tsx (new)
│   ├── VitalsSummaryCard.tsx (new)
│   ├── LabsSummaryCard.tsx (new)
│   ├── LabsTab.tsx (new)
│   ├── LabOrdersTable.tsx (new)
│   └── LabResultsDisplay.tsx (new)
```

### Backend Changes

No backend changes required. All necessary data is already loaded in the existing `PatientAdmissionController@show` method.

### Styling

- Use existing Tailwind classes for consistency
- Follow dark mode patterns from existing components
- Reuse card, badge, and button components from shadcn/ui
- Maintain responsive grid patterns used elsewhere in the app
