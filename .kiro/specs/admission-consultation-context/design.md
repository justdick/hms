# Design Document

## Overview

This feature enhances the admitted patient show page by displaying consultation vitals and prescriptions from the consultation that led to admission. The design integrates consultation data seamlessly into existing tabs while maintaining clear visual distinction between consultation and ward data.

## Architecture

### Data Flow

```
Consultation → Patient Admission → Ward Patient Show Page
     ↓              ↓                      ↓
  Vitals      consultation_id      Display in Vitals Tab
  Prescriptions                    Display in Medications Tab
```

### Key Components

1. **Backend Controller Enhancement** (`WardPatientController@show`)
   - Load consultation relationship with vitals and prescriptions
   - Eager load related data to prevent N+1 queries

2. **Frontend Component Updates** (`PatientShow.tsx`)
   - Display consultation vitals in Vitals tab
   - Display consultation prescriptions in Medications tab
   - Add visual indicators for consultation data

3. **Data Models**
   - `PatientAdmission` - already has `consultation_id` foreign key
   - `Consultation` - has relationships to vitals and prescriptions
   - `VitalSign` - linked via `patient_checkin_id`
   - `Prescription` - linked via `consultation_id`

## Components and Interfaces

### Backend Changes

#### WardPatientController Enhancement

```php
public function show(Request $request, WardModel $ward, PatientAdmission $admission, VitalsScheduleService $scheduleService)
{
    // Existing code...
    
    $admission->load([
        // Existing relationships...
        'consultation.doctor',
        'consultation.patientCheckin.vitalSigns' => function ($query) {
            $query->latest()->with('recordedBy:id,name')->limit(1);
        },
        'consultation.prescriptions.drug',
    ]);
    
    // Existing code...
}
```

#### Data Structure

The consultation data will be passed to the frontend with this structure:

```typescript
interface Consultation {
    id: number;
    doctor: Doctor;
    chief_complaint?: string;
    diagnosis?: string;
    patient_checkin?: {
        vital_signs?: VitalSign[];
    };
    prescriptions?: Prescription[];
}
```

### Frontend Changes

#### Vitals Tab Enhancement

Add a new section at the top of the Vitals tab to display consultation vitals:

```tsx
{/* Consultation Vitals Section */}
{admission.consultation?.patient_checkin?.vital_signs?.[0] && (
    <Card className="border-blue-200 dark:border-blue-800">
        <CardHeader>
            <div className="flex items-center justify-between">
                <CardTitle className="flex items-center gap-2">
                    <Activity className="h-5 w-5 text-blue-600" />
                    Vitals from Admission Consultation
                </CardTitle>
                <Badge variant="outline" className="bg-blue-50 text-blue-700">
                    Consultation
                </Badge>
            </div>
        </CardHeader>
        <CardContent>
            {/* Display vital signs in a grid or table */}
        </CardContent>
    </Card>
)}
```

#### Medications Tab Enhancement

Add a new section at the top of the Medications tab to display consultation prescriptions:

```tsx
{/* Consultation Prescriptions Section */}
{admission.consultation?.prescriptions && admission.consultation.prescriptions.length > 0 && (
    <Card className="border-blue-200 dark:border-blue-800">
        <CardHeader>
            <div className="flex items-center justify-between">
                <CardTitle className="flex items-center gap-2">
                    <Pill className="h-5 w-5 text-blue-600" />
                    Prescriptions from Admission Consultation
                </CardTitle>
                <Badge variant="outline" className="bg-blue-50 text-blue-700">
                    Consultation
                </Badge>
            </div>
        </CardHeader>
        <CardContent>
            {/* Display prescriptions in a table */}
        </CardContent>
    </Card>
)}
```

### Visual Design

#### Color Scheme for Consultation Data
- Border: Blue (border-blue-200 / dark:border-blue-800)
- Badge: Blue outline with light blue background
- Icons: Blue accent color

#### Layout Structure

**Vitals Tab:**
```
┌─────────────────────────────────────┐
│ Vitals from Admission Consultation  │ ← New section (blue border)
│ [Badge: Consultation]               │
│ Temperature: 37.2°C                 │
│ BP: 120/80 mmHg                     │
│ ...                                 │
└─────────────────────────────────────┘

┌─────────────────────────────────────┐
│ Vitals Schedule                     │ ← Existing section
│ ...                                 │
└─────────────────────────────────────┘

┌─────────────────────────────────────┐
│ Recorded Vitals                     │ ← Existing section
│ ...                                 │
└─────────────────────────────────────┘
```

**Medications Tab:**
```
┌─────────────────────────────────────┐
│ Prescriptions from Admission        │ ← New section (blue border)
│ Consultation                        │
│ [Badge: Consultation]               │
│ Table of prescriptions...           │
└─────────────────────────────────────┘

┌─────────────────────────────────────┐
│ Medication Administration           │ ← Existing section
│ ...                                 │
└─────────────────────────────────────┘
```

## Data Models

### Existing Relationships

```php
// PatientAdmission Model
public function consultation(): BelongsTo
{
    return $this->belongsTo(Consultation::class);
}

// Consultation Model
public function patientCheckin(): BelongsTo
{
    return $this->belongsTo(PatientCheckin::class);
}

public function prescriptions(): HasMany
{
    return $this->hasMany(Prescription::class);
}

// PatientCheckin Model
public function vitalSigns(): HasMany
{
    return $this->hasMany(VitalSign::class);
}
```

### Data Loading Strategy

Use eager loading to prevent N+1 queries:

```php
$admission->load([
    'consultation' => function ($query) {
        $query->with([
            'doctor:id,name',
            'patientCheckin.vitalSigns' => function ($q) {
                $q->latest()->with('recordedBy:id,name')->limit(1);
            },
            'prescriptions' => function ($q) {
                $q->with('drug:id,name,strength,form');
            }
        ]);
    }
]);
```

## Error Handling

### Scenarios to Handle

1. **No Consultation Linked**
   - Admission exists but `consultation_id` is null
   - Display: No special section, existing functionality continues

2. **Consultation Exists but No Vitals**
   - Consultation linked but no vitals recorded during check-in
   - Display: Show consultation prescriptions only, no vitals section

3. **Consultation Exists but No Prescriptions**
   - Consultation linked but no medications prescribed
   - Display: Show consultation vitals only, no prescriptions section

4. **Consultation Deleted**
   - Soft-deleted consultation
   - Handle: Check for null consultation in frontend

### Error Messages

```typescript
// No vitals recorded
"No vitals were recorded during the admission consultation"

// No prescriptions
"No medications were prescribed during the admission consultation"
```

## Testing Strategy

### Unit Tests

1. **Controller Tests** (`WardPatientControllerTest.php`)
   - Test consultation data is loaded with admission
   - Test eager loading includes vitals and prescriptions
   - Test handling of admissions without consultations
   - Test handling of consultations without vitals/prescriptions

2. **Model Tests**
   - Test `PatientAdmission::consultation()` relationship
   - Test `Consultation::patientCheckin()` relationship
   - Test `Consultation::prescriptions()` relationship
   - Test `PatientCheckin::vitalSigns()` relationship

### Feature Tests

1. **Ward Patient Show Page Tests**
   - Test consultation vitals display in Vitals tab
   - Test consultation prescriptions display in Medications tab
   - Test visual indicators (badges, borders) are present
   - Test admissions without consultations render correctly
   - Test consultations without vitals/prescriptions render correctly

### Browser Tests

1. **End-to-End Tests** (`WardPatientConsultationContextBrowserTest.php`)
   - Navigate to admitted patient page
   - Verify consultation vitals section appears in Vitals tab
   - Verify consultation prescriptions section appears in Medications tab
   - Verify "View Consultation" button still works
   - Verify visual distinction between consultation and ward data

## Performance Considerations

### Query Optimization

1. **Eager Loading**
   - Load consultation with vitals and prescriptions in single query
   - Use `with()` to prevent N+1 queries
   - Limit vitals to most recent record

2. **Selective Column Loading**
   - Load only necessary columns for related models
   - Use `select()` in relationship queries

3. **Caching Strategy**
   - No additional caching needed (data changes frequently)
   - Rely on existing Inertia partial reloads

### Expected Performance

- Page load time: < 2 seconds (same as current)
- Additional queries: +1 (consultation with nested relationships)
- Memory impact: Minimal (small dataset per admission)

## Migration Strategy

### Database Changes

No database migrations required. All necessary relationships and foreign keys already exist:
- `patient_admissions.consultation_id` → `consultations.id`
- `consultations.patient_checkin_id` → `patient_checkins.id`
- `vital_signs.patient_checkin_id` → `patient_checkins.id`
- `prescriptions.consultation_id` → `consultations.id`

### Deployment Steps

1. Deploy backend changes (controller updates)
2. Deploy frontend changes (component updates)
3. No data migration or seeding required
4. Test on staging environment
5. Deploy to production

### Rollback Plan

If issues arise:
1. Revert frontend changes (remove consultation sections)
2. Revert backend changes (remove consultation eager loading)
3. No database rollback needed (no schema changes)

## Accessibility

### Screen Reader Support

- Use semantic HTML for consultation sections
- Add `aria-label` attributes to badges
- Ensure proper heading hierarchy

### Keyboard Navigation

- All interactive elements remain keyboard accessible
- Tab order follows logical flow
- Focus indicators visible

### Color Contrast

- Blue border and badges meet WCAG AA standards
- Text contrast ratios > 4.5:1
- Dark mode support maintained

## Security Considerations

### Authorization

- Existing authorization checks remain in place
- Nurses and doctors can view consultation data
- No new permission requirements

### Data Privacy

- Consultation data follows same privacy rules as admission data
- No PHI exposed beyond existing access controls
- Audit logging continues to track page access

## Future Enhancements

### Potential Improvements

1. **Consultation Diagnosis Display**
   - Show consultation diagnoses in a separate section
   - Link to ICD code details

2. **Lab Orders from Consultation**
   - Display lab orders from consultation
   - Show results if available

3. **Consultation Timeline**
   - Visual timeline showing consultation → admission → ward rounds
   - Interactive timeline navigation

4. **Medication Reconciliation**
   - Compare consultation prescriptions with ward prescriptions
   - Highlight discrepancies or changes

5. **Vitals Comparison**
   - Side-by-side comparison of consultation vitals vs current vitals
   - Trend indicators (improving, worsening, stable)
