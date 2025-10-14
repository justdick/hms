# Patient History Feature - Consultation Page

## Overview
Added a comprehensive patient history sidebar to the consultation page, allowing doctors to quickly review previous visits and make informed decisions about current treatment.

## Implementation

### UI/UX Approach: Hybrid Sidebar + Modal Pattern

We implemented a combination of:
1. **Collapsible Sidebar (Sheet)** - Quick access to recent visit summaries
2. **Detailed Modal Dialog** - Full consultation details when needed

### Components Created

#### 1. PatientHistorySidebar Component
**Location:** `resources/js/components/Consultation/PatientHistorySidebar.tsx`

**Features:**
- Button trigger showing count of previous visits
- Side sheet that opens from the right
- Medical alerts/allergies prominently displayed at top (red alert box)
- Scrollable list of previous consultations showing:
  - Visit date and time
  - Attending physician and department
  - Chief complaint (highlighted in blue box)
  - Quick summary: # of diagnoses, prescriptions, lab tests
  - Top diagnoses with ICD codes as badges
- Click any visit to open detailed modal
- Full dark mode support

#### 2. PreviousVisitModal Component
**Location:** `resources/js/components/Consultation/PreviousVisitModal.tsx`

**Features:**
- Large modal (max-width 4xl) with scrollable content
- Visit header showing physician, department, and status
- Tabbed interface with 4 tabs:
  - **Notes Tab:** Full SOAP notes (Chief Complaint, Subjective, Objective, Assessment, Plan)
  - **Vitals Tab:** Vital signs in card format (Temperature, BP, Heart Rate, Respiratory Rate)
  - **Diagnosis Tab:** All diagnoses with ICD codes, primary diagnosis highlighted
  - **Treatment Tab:** Medications prescribed + Lab tests ordered with details
- Full dark mode support
- Clean, medical EMR-style interface

### Integration

**Modified Files:**
1. `resources/js/pages/Consultation/Show.tsx`
   - Added PatientHistorySidebar import
   - Placed button in header next to status badge
   - Passes previousConsultations and allergies data

2. `app/Http/Controllers/Consultation/ConsultationController.php`
   - Enhanced query to load vital signs for previous consultations
   - Added lab orders relationship loading
   - Loads all necessary data: diagnoses (with is_primary flag), prescriptions (with status), lab orders

### Data Loaded for Previous Visits

For each previous consultation, we load:
- Basic consultation info (date, status, notes)
- Doctor and department information
- Latest vital signs from that visit
- All diagnoses with ICD codes
- All prescriptions with dosage, frequency, duration, instructions
- All lab orders with test details, priority, status

### Benefits

1. **Quick Access:** Button always visible in header
2. **Non-Intrusive:** Sidebar doesn't block current work
3. **Comprehensive:** Full detail available when needed
4. **Medical Standards:** Follows EMR best practices
5. **Performance:** Loads only last 10 consultations by default
6. **Responsive:** Works on tablets and desktops
7. **Dark Mode:** Full support for dark theme

## Usage

1. Open any consultation: `/consultation/{id}`
2. Click "Previous Visits (X)" button in header
3. Browse recent visits in sidebar
4. Click any visit card to see full details in modal
5. Review SOAP notes, vitals, diagnoses, and treatments
6. Close modal to return to sidebar, or close both to continue current consultation

## Technical Notes

- Uses shadcn/ui components (Sheet, Dialog, ScrollArea, Tabs, Card)
- Properly typed TypeScript interfaces
- Optimized queries with eager loading to prevent N+1
- Formatted with Laravel Pint
- Built successfully with Vite

## Future Enhancements (Optional)

- Add vital signs trend charts
- Filter/search within previous visits
- Print previous visit summary
- Export consultation history to PDF
- Add allergy management system
- Show medication interactions/contraindications
