# Design Document

## Overview

This feature enhances the patient management workflow by introducing a dedicated patient management interface accessible from the sidebar navigation. The design builds upon the existing patient registration system (which already supports insurance capture) and extends it with a comprehensive patient list page, profile management, and a streamlined check-in flow that allows immediate check-in after registration.

The solution leverages existing models (`Patient`, `PatientInsurance`, `PatientCheckin`) and components (`PatientRegistrationForm`, `CheckinModal`) while introducing new pages and workflows to improve the user experience.

## Architecture

### High-Level Architecture

```
┌─────────────────────────────────────────────────────────────┐
│                     Sidebar Navigation                       │
│  ┌──────────┐  ┌──────────┐  ┌──────────┐  ┌──────────┐   │
│  │Dashboard │  │Check-in  │  │Patients  │  │  Other   │   │
│  │          │  │          │  │   NEW    │  │  Menus   │   │
│  └──────────┘  └──────────┘  └──────────┘  └──────────┘   │
└─────────────────────────────────────────────────────────────┘
                                    │
                    ┌───────────────┴───────────────┐
                    │                               │
            ┌───────▼────────┐            ┌────────▼────────┐
            │  Patient List  │            │ Patient Profile │
            │      Page      │◄───────────┤      Page       │
            └───────┬────────┘            └────────┬────────┘
                    │                               │
        ┌───────────┼───────────┐                  │
        │           │           │                  │
┌───────▼──┐  ┌────▼─────┐  ┌──▼──────┐    ┌─────▼──────┐
│  Search  │  │ Register │  │  View   │    │    Edit    │
│ Patients │  │   New    │  │ Details │    │  Patient   │
└──────────┘  └────┬─────┘  └─────────┘    └────────────┘
                   │
            ┌──────▼──────┐
            │  Check-in   │
            │   Prompt    │
            └──────┬──────┘
                   │
            ┌──────▼──────┐
            │  Check-in   │
            │    Modal    │
            └─────────────┘
```

### Component Flow

1. **Navigation Entry**: User clicks "Patients" in sidebar → navigates to Patient List page
2. **Patient Registration**: User clicks "Register New Patient" → opens registration modal → on success → shows check-in prompt
3. **Check-in Prompt**: User confirms → opens check-in modal with pre-populated patient data
4. **Patient Profile**: User clicks patient in list → navigates to profile page → can view/edit details

## Components and Interfaces

### 1. Backend Components

#### Controllers

**PatientController** (Extend existing at `app/Http/Controllers/Patient/PatientController.php`)
- `index()`: Display patient list page with search and pagination
- `show($id)`: Display patient profile page
- `update($id)`: Update patient information
- `store()`: Already exists - handles patient registration with insurance

**Routes** (Add to `routes/web.php` or new `routes/patients.php`)
```php
Route::middleware(['auth', 'verified'])->prefix('patients')->name('patients.')->group(function () {
    Route::get('/', [PatientController::class, 'index'])->name('index');
    Route::get('/{patient}', [PatientController::class, 'show'])->name('show');
    Route::patch('/{patient}', [PatientController::class, 'update'])->name('update');
    Route::post('/', [PatientController::class, 'store'])->name('store');
});
```

#### Form Requests

**UpdatePatientRequest** (New at `app/Http/Requests/UpdatePatientRequest.php`)
- Validation rules for updating patient demographics
- Validation rules for updating insurance information
- Authorization logic

#### Resources

**PatientResource** (New at `app/Http/Resources/PatientResource.php`)
- Transform patient data for API responses
- Include relationships (active insurance, recent check-ins)
- Format dates and computed attributes

### 2. Frontend Components

#### Pages

**Patients/Index.tsx** (New at `resources/js/Pages/Patients/Index.tsx`)
- Patient list with search functionality
- Pagination support
- "Register New Patient" button
- Quick actions (view profile, check-in)
- Filters (by status, insurance status)

**Patients/Show.tsx** (New at `resources/js/Pages/Patients/Show.tsx`)
- Patient demographics display
- Insurance information display
- Check-in history
- Edit button
- Check-in button

**Patients/Edit.tsx** (New at `resources/js/Pages/Patients/Edit.tsx`)
- Editable patient form
- Insurance management
- Save/Cancel actions

#### Reusable Components

**PatientRegistrationModal** (New at `resources/js/components/Patient/RegistrationModal.tsx`)
- Wraps existing `PatientRegistrationForm` in a modal
- Handles modal open/close state
- Triggers check-in prompt on success

**CheckinPromptDialog** (New at `resources/js/components/Checkin/CheckinPromptDialog.tsx`)
- Confirmation dialog after patient registration
- "Check-in Now" and "Later" buttons
- Opens `CheckinModal` on confirmation

**PatientCard** (New at `resources/js/components/Patient/PatientCard.tsx`)
- Display patient summary in list view
- Shows patient number, name, age, gender, phone
- Insurance badge if active insurance exists
- Quick action buttons

#### Modified Components

**PatientRegistrationForm** (Existing at `resources/js/components/Patient/RegistrationForm.tsx`)
- Already supports insurance capture ✓
- No changes needed - already complete

**app-sidebar.tsx** (Existing at `resources/js/components/app-sidebar.tsx`)
- Add "Patients" navigation item
- Position between "Check-in" and "Consultation"

### 3. Data Models

#### Existing Models (No changes needed)

**Patient** (`app/Models/Patient.php`)
- Already has all required fields
- Already has insurance relationships
- Already has search scope

**PatientInsurance** (`app/Models/PatientInsurance.php`)
- Already handles insurance data
- Already has active scope

**PatientCheckin** (`app/Models/PatientCheckin.php`)
- Already handles check-in flow
- Already has relationships

## Data Models

### Patient List Response Structure

```typescript
interface PatientListResponse {
    data: Patient[];
    meta: {
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
    };
    links: {
        first: string;
        last: string;
        prev: string | null;
        next: string | null;
    };
}

interface Patient {
    id: number;
    patient_number: string;
    full_name: string;
    first_name: string;
    last_name: string;
    age: number;
    gender: 'male' | 'female';
    phone_number: string | null;
    date_of_birth: string;
    address: string | null;
    status: string;
    active_insurance: PatientInsurance | null;
    recent_checkin: PatientCheckin | null;
}
```

### Patient Profile Response Structure

```typescript
interface PatientProfileResponse {
    patient: Patient;
    insurance_plans: InsurancePlan[];
    checkin_history: PatientCheckin[];
    can_edit: boolean;
    can_checkin: boolean;
}

interface Patient {
    id: number;
    patient_number: string;
    first_name: string;
    last_name: string;
    full_name: string;
    gender: 'male' | 'female';
    date_of_birth: string;
    age: number;
    phone_number: string | null;
    address: string | null;
    emergency_contact_name: string | null;
    emergency_contact_phone: string | null;
    national_id: string | null;
    status: string;
    active_insurance: PatientInsurance | null;
    insurance_plans: PatientInsurance[];
    past_medical_surgical_history: string | null;
    drug_history: string | null;
    family_history: string | null;
    social_history: string | null;
}
```

## User Workflows

### Workflow 1: Register Patient from Patient List Page

```
1. User navigates to Patients page from sidebar
2. User clicks "Register New Patient" button
3. Registration modal opens with PatientRegistrationForm
4. User fills in patient details (including optional insurance)
5. User submits form
6. Backend creates patient and insurance records
7. Success toast appears
8. Check-in prompt dialog appears: "Would you like to check in this patient now?"
9a. User clicks "Check-in Now":
    - CheckinModal opens with patient pre-selected
    - User selects department
    - Check-in is created
    - User redirected to check-in dashboard or stays on patient list
9b. User clicks "Later":
    - Dialog closes
    - Patient list refreshes to show new patient
```

### Workflow 2: Register Patient from Check-in Page (Existing + Enhanced)

```
1. User navigates to Check-in page
2. User clicks "Register New" tab
3. User fills in patient registration form (including optional insurance)
4. User submits form
5. Backend creates patient and insurance records
6. Check-in prompt dialog appears: "Would you like to check in this patient now?"
7a. User clicks "Check-in Now":
    - CheckinModal opens with patient pre-selected
    - User selects department
    - Check-in is created
7b. User clicks "Later":
    - Dialog closes
    - User can search for patient to check in later
```

### Workflow 3: View and Edit Patient Profile

```
1. User navigates to Patients page
2. User searches for patient (by name, patient number, or phone)
3. User clicks on patient card
4. Patient profile page displays:
   - Demographics
   - Insurance information
   - Check-in history
5. User clicks "Edit" button
6. Edit form displays with current data
7. User modifies information
8. User clicks "Save"
9. Backend validates and updates patient record
10. Success toast appears
11. User redirected back to profile view
```

### Workflow 4: Quick Check-in from Patient List

```
1. User navigates to Patients page
2. User searches for patient
3. User clicks "Check-in" button on patient card
4. CheckinModal opens with patient pre-selected
5. User selects department
6. Check-in is created
7. Success toast appears
8. Patient list updates to show recent check-in
```

## Error Handling

### Validation Errors

**Patient Registration**
- Required fields: first_name, last_name, gender, date_of_birth
- Phone number format validation
- Date of birth must be in the past
- If insurance is provided:
  - insurance_plan_id, membership_id, coverage_start_date are required
  - If is_dependent is true: principal_member_name and relationship_to_principal are required

**Patient Update**
- Same validation as registration
- Additional check: patient must exist
- Authorization: user must have permission to edit patients

**Check-in Creation**
- Patient must exist
- Department must exist
- Patient cannot have an active check-in for the same day (optional business rule)

### Error Messages

```typescript
const errorMessages = {
    patientNotFound: 'Patient not found',
    registrationFailed: 'Failed to register patient. Please try again.',
    updateFailed: 'Failed to update patient information. Please try again.',
    checkinFailed: 'Failed to check in patient. Please try again.',
    invalidInsurance: 'Invalid insurance information provided',
    duplicateCheckin: 'Patient already has an active check-in today',
    unauthorized: 'You do not have permission to perform this action',
};
```

### Error Handling Strategy

1. **Form Validation Errors**: Display inline below each field
2. **Server Errors**: Display toast notification with error message
3. **Network Errors**: Display toast with retry option
4. **Authorization Errors**: Redirect to appropriate page with error message
5. **Not Found Errors**: Display 404 page or redirect to patient list

## Testing Strategy

### Backend Tests

**Feature Tests** (`tests/Feature/Patient/`)

1. **PatientManagementTest.php**
   - `test_user_can_view_patient_list()`
   - `test_user_can_search_patients()`
   - `test_user_can_view_patient_profile()`
   - `test_user_can_register_patient_without_insurance()`
   - `test_user_can_register_patient_with_insurance()`
   - `test_user_can_update_patient_information()`
   - `test_user_cannot_register_patient_with_invalid_data()`
   - `test_user_cannot_update_patient_without_permission()`

2. **PatientCheckinFlowTest.php**
   - `test_check_in_prompt_appears_after_registration()`
   - `test_user_can_check_in_immediately_after_registration()`
   - `test_user_can_skip_immediate_check_in()`
   - `test_user_can_check_in_from_patient_list()`

### Frontend Tests

**Component Tests** (Optional - using Pest Browser Testing)

1. **Patient List Page**
   - Test patient search functionality
   - Test pagination
   - Test "Register New Patient" button opens modal
   - Test quick check-in button

2. **Patient Registration Flow**
   - Test form validation
   - Test insurance section toggle
   - Test check-in prompt appears after successful registration
   - Test check-in modal opens when "Check-in Now" is clicked

3. **Patient Profile Page**
   - Test patient information display
   - Test edit functionality
   - Test check-in button

### Manual Testing Checklist

- [ ] Sidebar navigation shows "Patients" menu item
- [ ] Patient list page loads with search and pagination
- [ ] Register new patient without insurance
- [ ] Register new patient with insurance
- [ ] Check-in prompt appears after registration
- [ ] Immediate check-in works from prompt
- [ ] Skip immediate check-in works
- [ ] Patient profile page displays correctly
- [ ] Edit patient information works
- [ ] Quick check-in from patient list works
- [ ] Search patients by name, number, phone works
- [ ] Pagination works correctly
- [ ] Insurance badge displays for patients with active insurance
- [ ] Check-in history displays on profile page

## Security Considerations

### Authorization

**Permissions Required**
- `patients.view`: View patient list and profiles
- `patients.create`: Register new patients
- `patients.update`: Edit patient information
- `patients.delete`: Delete patients (future)
- `checkins.create`: Create check-ins

**Policy Implementation**
```php
// app/Policies/PatientPolicy.php
class PatientPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('patients.view');
    }

    public function view(User $user, Patient $patient): bool
    {
        return $user->can('patients.view');
    }

    public function create(User $user): bool
    {
        return $user->can('patients.create');
    }

    public function update(User $user, Patient $patient): bool
    {
        return $user->can('patients.update');
    }
}
```

### Data Protection

- Patient data is sensitive PHI (Protected Health Information)
- Ensure HTTPS is enforced for all patient-related endpoints
- Log all patient data access for audit trails
- Implement rate limiting on search endpoints to prevent data scraping
- Sanitize all user inputs to prevent XSS attacks
- Use parameterized queries to prevent SQL injection (Laravel ORM handles this)

## Performance Considerations

### Database Optimization

**Indexes**
- `patients.patient_number` (already indexed as unique)
- `patients.phone_number` (for search)
- `patients.first_name, patients.last_name` (composite index for name search)
- `patient_insurance.patient_id` (already indexed as foreign key)
- `patient_insurance.status` (for filtering active insurance)

**Query Optimization**
- Use eager loading for relationships: `Patient::with(['activeInsurance', 'recentCheckin'])`
- Paginate patient list (25 per page)
- Use `select()` to limit columns returned in list view
- Cache frequently accessed data (insurance plans, departments)

### Frontend Optimization

- Implement debouncing on search input (300ms delay)
- Use Inertia's prefetching for patient profile links
- Lazy load check-in history on profile page
- Implement virtual scrolling for large patient lists (future enhancement)
- Cache insurance plans in component state to avoid repeated fetches

## UI/UX Design

### Patient List Page Layout

```
┌─────────────────────────────────────────────────────────────┐
│  Patients                                    [Register New]  │
│  ─────────────────────────────────────────────────────────  │
│                                                              │
│  [Search patients...]                        [Filter ▼]     │
│                                                              │
│  ┌────────────────────────────────────────────────────────┐ │
│  │ 📋 P-2024-001  John Doe                    🛡️ Insured  │ │
│  │    Male, 45 years • +255 123 456 789                   │ │
│  │    Last check-in: 2 days ago          [View] [Check-in]│ │
│  └────────────────────────────────────────────────────────┘ │
│                                                              │
│  ┌────────────────────────────────────────────────────────┐ │
│  │ 📋 P-2024-002  Jane Smith                              │ │
│  │    Female, 32 years • +255 987 654 321                 │ │
│  │    Last check-in: 1 week ago          [View] [Check-in]│ │
│  └────────────────────────────────────────────────────────┘ │
│                                                              │
│  ← Previous    1 2 3 4 5    Next →                          │
└─────────────────────────────────────────────────────────────┘
```

### Patient Profile Page Layout

```
┌─────────────────────────────────────────────────────────────┐
│  ← Back to Patients                    [Edit] [Check-in]    │
│                                                              │
│  📋 P-2024-001  John Doe                                    │
│  ─────────────────────────────────────────────────────────  │
│                                                              │
│  ┌─────────────────────┐  ┌──────────────────────────────┐ │
│  │ Demographics        │  │ Insurance Information        │ │
│  │                     │  │                              │ │
│  │ Gender: Male        │  │ 🛡️ Active Coverage          │ │
│  │ Age: 45 years       │  │ Provider: NHIF               │ │
│  │ DOB: 1979-01-15     │  │ Plan: Standard               │ │
│  │ Phone: +255 123...  │  │ Member ID: 12345             │ │
│  │ Address: Dar es...  │  │ Valid until: 2025-12-31      │ │
│  └─────────────────────┘  └──────────────────────────────┘ │
│                                                              │
│  ┌────────────────────────────────────────────────────────┐ │
│  │ Check-in History                                       │ │
│  │                                                        │ │
│  │ • 2024-11-09 - General Medicine - Completed           │ │
│  │ • 2024-11-05 - Pediatrics - Completed                 │ │
│  │ • 2024-10-28 - General Medicine - Completed           │ │
│  └────────────────────────────────────────────────────────┘ │
└─────────────────────────────────────────────────────────────┘
```

### Check-in Prompt Dialog

```
┌─────────────────────────────────────────┐
│  Patient Registered Successfully! ✓     │
│                                         │
│  John Doe (P-2024-001) has been        │
│  registered successfully.               │
│                                         │
│  Would you like to check in this       │
│  patient for consultation now?          │
│                                         │
│           [Later]    [Check-in Now]    │
└─────────────────────────────────────────┘
```

## Implementation Notes

### Reusing Existing Components

The design maximizes reuse of existing components:

1. **PatientRegistrationForm**: Already complete with insurance support - no changes needed
2. **CheckinModal**: Already exists - will be reused for immediate check-in flow
3. **PatientSearchForm**: Already exists - can be reused in patient list page
4. **VitalsModal**: Already exists - may be useful in patient profile page

### New Components to Create

1. **PatientRegistrationModal**: Wrapper around existing form
2. **CheckinPromptDialog**: New confirmation dialog
3. **PatientCard**: List item component
4. **PatientList**: Main list page
5. **PatientProfile**: Profile view page
6. **PatientEdit**: Edit form page

### Route Organization

Consider creating a dedicated `routes/patients.php` file to keep patient-related routes organized separately from check-in routes. This improves maintainability and follows the existing pattern (separate files for billing, pharmacy, lab, etc.).

### Sidebar Navigation Update

Add the "Patients" menu item between "Check-in" and "Consultation" in the sidebar. Use the `Users` icon from lucide-react.

```typescript
{
    title: 'Patients',
    href: '/patients',
    icon: Users,
}
```
