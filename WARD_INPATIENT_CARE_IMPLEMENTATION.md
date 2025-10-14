# Ward & Inpatient Care System - Implementation Plan

## 📋 Overview

This document outlines the implementation plan for enhancing the ward management system to support comprehensive inpatient care workflows including ward rounds, medication administration, vital signs monitoring, and nursing documentation.

---

## ✅ IMPLEMENTATION PROGRESS

**Last Updated:** 2025-10-14 (Session 6) | **Current Status:** Phase 1, 2, 3, 4, 5 & 6 Complete

### Phase 1: Database & Backend Foundation ✅ COMPLETE
**Completion Date:** 2025-10-14 (Session 1)

#### ✅ Migrations Created and Run Successfully:
1. ✅ `2025_10_14_001936_remove_attending_doctor_from_patient_admissions.php`
   - Removed fixed `attending_doctor_id` column from `patient_admissions` table
   - Enables team-based care model where any doctor can attend to patients

2. ✅ `2025_10_14_002057_create_medication_administrations_table.php`
   - Created table to track scheduled and administered medications
   - Includes indexes: `med_admin_admission_scheduled_idx`, `med_admin_by_at_idx`
   - Status enum: scheduled, given, held, refused, omitted

3. ✅ `2025_10_14_002206_create_nursing_notes_table.php`
   - Created table for nursing documentation
   - Index: `nursing_notes_admission_noted_idx`
   - Note types: assessment, care, observation, incident, handover

4. ✅ `2025_10_14_002353_create_ward_rounds_table.php`
   - Created table for doctor ward round documentation
   - Indexes: `ward_rounds_admission_datetime_idx`, `ward_rounds_doctor_idx`
   - Patient status tracking: improving, stable, deteriorating, discharge_ready

5. ✅ `2025_10_14_002416_add_patient_admission_id_to_vital_signs_table.php`
   - Added `patient_admission_id` foreign key to `vital_signs` table
   - Enables vital signs recording for both outpatient and inpatient contexts

#### ✅ Models Created:
- ✅ `app/Models/MedicationAdministration.php` - Full model with relationships and scopes
- ✅ `app/Models/NursingNote.php` - Full model with relationships and scopes
- ✅ `app/Models/WardRound.php` - Full model with relationships and helper methods

#### ✅ Updated Existing Models:
- ✅ `app/Models/PatientAdmission.php`
  - Removed `attending_doctor_id` from fillable
  - Removed `attendingDoctor()` relationship
  - Added relationships: `vitalSigns()`, `latestVitalSigns()`, `medicationAdministrations()`, `pendingMedications()`, `wardRounds()`, `nursingNotes()`

- ✅ `app/Models/Prescription.php`
  - Added `medicationAdministrations()` relationship

- ✅ `app/Models/VitalSign.php`
  - Added `patient_admission_id` to fillable
  - Added `patientAdmission()` relationship

- ✅ `app/Models/Ward.php`
  - Already had `admissions()` relationship - no changes needed

- ✅ `app/Models/Consultation.php`
  - Added `patientAdmission()` relationship (hasOne)

#### ✅ Key Features:
- All database relationships properly configured
- Team-based care model implemented (no fixed attending doctor)
- Comprehensive audit trail through ward_rounds, nursing_notes, medication_administrations
- Supports both outpatient and inpatient vital signs recording

---

### Phase 2: Ward Show Page Enhancements ✅ COMPLETE
**Completion Date:** 2025-10-14 (Session 2)

#### ✅ Backend Complete (Session 1):
- ✅ `app/Http/Controllers/Ward/WardController.php` - Enhanced `show()` method
  - Loads patient admissions with comprehensive relationships
  - Eager loads: patient, bed, consultation.doctor, latestVitalSigns, pendingMedications
  - Includes counts for wardRounds and nursingNotes
  - Calculates ward statistics (total_patients, pending_meds_count, patients_needing_vitals)

#### ✅ Frontend Complete (Session 2):
- ✅ Completely rebuilt `resources/js/pages/Ward/Show.tsx` with comprehensive features:

  **Enhanced Stats Dashboard:**
  - ✅ Added 6 stat cards (Total Beds, Available, Occupied, Occupancy %, Pending Meds, Need Vitals)
  - ✅ All cards with dark mode support

  **Redesigned Tab Interface:**
  - ✅ Changed default tab from "beds" to "patients"
  - ✅ Added "Medications" tab with pending medication notifications
  - ✅ Added "Vital Signs" tab with comprehensive vitals overview
  - ✅ All tabs with proper dark mode styling

  **Enhanced Current Patients Tab:**
  - ✅ Real-time indicators for pending medications, overdue vitals, ward rounds count, nursing notes count
  - ✅ Latest vital signs displayed inline (Temperature, BP, Pulse, RR, SpO₂)
  - ✅ Doctor assignment from consultation (team-based care model)
  - ✅ Bed assignment clearly visible
  - ✅ Patient metrics row with icons and color-coded badges

  **New Medications Tab:**
  - ✅ Groups medications by patient
  - ✅ Shows drug name, strength, and scheduled time
  - ✅ Visual overdue indicators (red highlighting + badges)
  - ✅ Clock icons with formatted times
  - ✅ Full dark mode support

  **New Vital Signs Tab:**
  - ✅ Comprehensive vitals display for all patients
  - ✅ Large, easy-to-read vital sign cards with icons
  - ✅ Alert system for patients needing vitals (yellow highlighting)
  - ✅ Shows last recorded time and recording nurse
  - ✅ Individual metrics (Temp, BP, Pulse, RR, SpO₂) with proper units

  **TypeScript & Code Quality:**
  - ✅ Updated all TypeScript interfaces to match backend data structure
  - ✅ Fixed attending_doctor → consultation.doctor migration
  - ✅ Code formatted with Prettier
  - ✅ All icons imported and used correctly

**Key Features Implemented:**
- At-a-glance dashboard for ward managers
- Patient-centric view with all critical info visible
- Color-coded alerts for urgent matters (medications, vitals)
- Responsive design for all screen sizes
- Professional medical-grade UI
- Complete dark mode support throughout

**Files Modified:**
- `resources/js/pages/Ward/Show.tsx` (817 lines, completely rebuilt)

---

### Phase 3: Vital Signs Recording (Nurses) ✅ COMPLETE
**Completion Date:** 2025-10-14 (Session 3)

#### ✅ Backend Complete:
- ✅ `app/Http/Controllers/Vitals/VitalSignController.php` - Added `storeForAdmission()` method
  - Validates all vital sign inputs (temperature, blood pressure, pulse rate, respiratory rate, oxygen saturation, weight, height, notes)
  - Associates vitals with patient admission (via `patient_admission_id`)
  - Links to patient_checkin_id if available through consultation
  - Records the nurse who entered the vitals (`recorded_by`)
  - Returns redirect with success message
  - Uses proper authorization check with VitalSign policy

- ✅ `routes/wards.php` - Added vital signs route
  - `POST /admissions/{admission}/vitals` → `VitalSignController@storeForAdmission`
  - Named route: `admissions.vitals.store`
  - Middleware: auth, verified

#### ✅ Frontend Complete:
- ✅ `resources/js/components/Ward/RecordVitalsModal.tsx` - New modal component
  - Uses Inertia `<Form>` component for proper form handling (matches existing Checkin pattern)
  - Shows patient information banner (name, age, gender, admission number, bed)
  - Primary vitals (required): Temperature (°F), Blood Pressure (mmHg), Pulse Rate (bpm), Respiratory Rate (/min)
  - Optional vitals: Oxygen Saturation (%), Weight (kg), Height (cm)
  - Clinical notes field (optional)
  - Toast notifications using Sonner (success/error)
  - Full dark mode support with proper color classes
  - Loading states and disabled submit button during processing
  - Form validation with inline error messages

- ✅ `resources/js/pages/Ward/Show.tsx` - Integrated modal
  - Added state management (`vitalsModalOpen`, `selectedAdmission`)
  - Added "Record Vitals" button in **Current Patients** tab (right column, next to each patient)
  - Added "Record Vitals" button in **Vital Signs** tab (next to "Vitals Needed" badge)
  - Modal positioned at end of component (before closing AppLayout)
  - Calls `onSuccess={closeVitalsModal}` to refresh page after submission

**Key Features Implemented:**
- Reuses Inertia `<Form>` component pattern from existing CheckinVitalsModal
- Clean separation: separate modal for admission context vs checkin context
- Proper validation with user-friendly error messages
- All required fields marked with red asterisks
- Responsive design (works on mobile, tablet, desktop)
- Professional medical-grade UI styling
- Complete dark mode support
- Toast notifications for user feedback
- Automatically closes modal on successful submission
- Page refreshes to show newly recorded vitals

**Code Quality:**
- ✅ TypeScript types properly defined (local interfaces in component)
- ✅ Code formatted with Prettier
- ✅ PHP code formatted with Laravel Pint
- ✅ No TypeScript errors (verified with `npm run types`)
- ✅ Follows existing project patterns and conventions

**Files Created:**
- `resources/js/components/Ward/RecordVitalsModal.tsx` (395 lines)

**Files Modified:**
- `app/Http/Controllers/Vitals/VitalSignController.php` (added `storeForAdmission` method)
- `routes/wards.php` (added admission routes group)
- `resources/js/pages/Ward/Show.tsx` (integrated modal, added buttons)

---

### Phase 4: Nursing Notes ✅ COMPLETE
**Completion Date:** 2025-10-14 (Session 4)

#### ✅ Backend Complete:
- ✅ **NursingNotePolicy** ([NursingNotePolicy.php](app/Policies/NursingNotePolicy.php:1-91))
  - View permissions for Admin, Nurse, and Doctor roles
  - Create permissions for Admin and Nurse roles
  - Update allowed within 24 hours (nurses can only edit their own notes)
  - Delete allowed within 2 hours (nurses can only delete their own notes)
  - Admin has full control over all notes

- ✅ **NursingNoteController** ([NursingNoteController.php](app/Http/Controllers/Ward/NursingNoteController.php:1-77))
  - `index()` - Fetch all nursing notes for an admission with nurse info
  - `store()` - Create new nursing note with auto-assigned nurse_id and noted_at
  - `update()` - Edit existing note (with time-based authorization via policy)
  - `destroy()` - Delete note (with time-based authorization via policy)

- ✅ **Routes** ([wards.php](routes/wards.php:23-27))
  - GET `/admissions/{admission}/nursing-notes`
  - POST `/admissions/{admission}/nursing-notes`
  - PUT `/admissions/{admission}/nursing-notes/{nursingNote}`
  - DELETE `/admissions/{admission}/nursing-notes/{nursingNote}`

#### ✅ Frontend Complete:
- ✅ **NursingNotesModal Component** ([NursingNotesModal.tsx](resources/js/components/Ward/NursingNotesModal.tsx:1-437))
  - **Three View Modes**: List view, Create form, Edit form
  - **List View Features:**
    - Displays all nursing notes with color-coded badges by type
    - Shows note content, timestamp, and recording nurse
    - Edit button (visible for notes < 24 hours old)
    - Delete button (visible for notes < 2 hours old)
    - Empty state with "Add First Note" prompt

  - **Create/Edit Form Features:**
    - Note type selection dropdown with icons (Assessment, Care, Observation, Incident, Handover)
    - Large textarea for detailed note entry (minimum 10 characters)
    - Real-time validation with inline error messages
    - Cancel and Save buttons with loading states

  - **Note Types with Color-Coded UI:**
    - 🔵 **Assessment** (Blue) - Patient assessments with Stethoscope icon
    - 🟢 **Care** (Green) - Care activities with UserCheck icon
    - 🟣 **Observation** (Purple) - Clinical observations with Eye icon
    - 🔴 **Incident** (Red) - Incident reports with AlertCircle icon
    - 🟠 **Handover** (Orange) - Shift handovers with ClipboardList icon

  - **API Integration:**
    - Fetches notes dynamically when modal opens
    - Refreshes list after create/update/delete operations
    - Uses Inertia `<Form>` component for proper form handling
    - Toast notifications using Sonner for user feedback

- ✅ **Ward Show Page Integration** ([Show.tsx](resources/js/pages/Ward/Show.tsx:738-762))
  - Added "Notes" button alongside "Vitals" button for each patient
  - Button displays FileText icon and opens nursing notes modal
  - Modal state management with `nursingNotesModalOpen` and `selectedAdmission`
  - Displays nursing notes count in patient metrics (line 601-617)

#### ✅ Key Features Implemented:
- **Security & Permissions:**
  - Role-based access control (Admin, Nurse, Doctor can view; Admin, Nurse can create)
  - Time-based editing (24 hours) and deletion (2 hours)
  - Only nurses can edit/delete their own notes (Admin can edit/delete any)
  - Proper authorization checks in policy with time validation

- **User Experience:**
  - Clean, professional modal interface with three distinct views
  - Color-coded note type badges for quick visual identification
  - Real-time error handling with toast notifications
  - Responsive design with dark mode support
  - Minimum character validation (10 chars)
  - Empty state UI when no notes exist
  - Loading states during API calls
  - Edit/delete buttons with time-based visibility

- **Data Integrity:**
  - Auto-assigns current user as nurse_id
  - Auto-sets noted_at timestamp
  - Cannot edit notes after 24 hours
  - Cannot delete notes after 2 hours
  - All changes tracked with timestamps (created_at, updated_at)

**Code Quality:**
- ✅ TypeScript types properly defined
- ✅ PHP code formatted with Laravel Pint
- ✅ Follows existing project patterns (Inertia Form, modal structure)
- ✅ Proper error handling and user feedback
- ✅ Full dark mode support with semantic color classes

**Files Created:**
- `app/Policies/NursingNotePolicy.php` (91 lines)
- `app/Http/Controllers/Ward/NursingNoteController.php` (77 lines)
- `resources/js/components/Ward/NursingNotesModal.tsx` (437 lines)

**Files Modified:**
- `routes/wards.php` (added nursing notes routes)
- `resources/js/pages/Ward/Show.tsx` (added Notes button and modal integration)
- `resources/js/components/Ward/RecordVitalsModal.tsx` (fixed TypeScript interface for gender field)

---

### Phase 5: Medication Administration (Nurses) ✅ COMPLETE
**Completion Date:** 2025-10-14 (Session 5)

#### ✅ Backend Complete:
- ✅ **MedicationScheduleService** ([MedicationScheduleService.php](app/Services/MedicationScheduleService.php:1-125))
  - Parses medication frequencies: OD, BD, TDS, QDS, Q6H, Q8H, Q12H, PRN, STAT
  - Generates scheduled administration times for duration period
  - Calculates duration from strings ("5 days", "2 weeks", "1 month")
  - Includes `regenerateSchedule()` method for dosage changes

- ✅ **MedicationAdministrationPolicy** ([MedicationAdministrationPolicy.php](app/Policies/MedicationAdministrationPolicy.php:1-95))
  - Permission-based authorization (not role-based)
  - `view medication administrations` - View schedules
  - `administer medications` - Administer, hold, refuse medications
  - `delete medication administrations` - Delete within 2 hours
  - Time-based validation: Can administer 30 minutes before scheduled time

- ✅ **MedicationAdministrationController** ([MedicationAdministrationController.php](app/Http/Controllers/Ward/MedicationAdministrationController.php:1-117))
  - `index()` - Get medications grouped by date with drug info
  - `administer()` - Mark medication as given with dosage, route, notes
  - `hold()` - Hold medication with required reason (min 10 chars)
  - `refuse()` - Mark as refused by patient
  - `omit()` - Mark as omitted for other reasons

- ✅ **Routes** ([wards.php](routes/wards.php:30-35))
  - GET `/admissions/{admission}/medications`
  - POST `/admissions/{administration}/administer`
  - POST `/admissions/{administration}/hold`
  - POST `/admissions/{administration}/refuse`
  - POST `/admissions/{administration}/omit`

- ✅ **Auto-schedule Integration** ([ConsultationController.php](app/Http/Controllers/Consultation/ConsultationController.php:341-372))
  - Automatically generates schedules when prescriptions created for admitted patients
  - Uses dependency injection for MedicationScheduleService

#### ✅ Frontend Complete:
- ✅ **MedicationAdministrationPanel Component** ([MedicationAdministrationPanel.tsx](resources/js/components/Ward/MedicationAdministrationPanel.tsx:1-635))
  - **Sheet slide-over panel** from right side
  - **Three sections**: Due Now (red), Scheduled Today, Administered Today (green)
  - **Real-time indicators**: Overdue badges, time formatting with date-fns
  - **Three action buttons**: Given (green), Hold (warning), Refused (destructive)
  - **Two modal dialogs**:
    - AdministerMedicationDialog - Confirm dosage, route, optional notes
    - HoldMedicationDialog - Require reason (min 10 chars)
  - Fetches medications via API when opened
  - Groups medications by date
  - Loading states with spinner
  - Empty state for no medications
  - Full dark mode support
  - Toast notifications using Sonner

- ✅ **Ward Show Page Integration** ([Show.tsx](resources/js/pages/Ward/Show.tsx:750-786))
  - Added "Meds" button alongside "Notes" and "Vitals" buttons
  - State management for panel open/close
  - Panel component rendered at end of page

#### ✅ Key Features Implemented:
- **Automatic Schedule Generation**: Prescriptions for admitted patients auto-create schedules
- **Comprehensive Frequency Support**: OD, BD, TDS, QDS, Q6H, Q8H, Q12H, PRN, STAT
- **Permission-Based Security**: Granular permissions for viewing and administering
- **Time-Based Validation**: Can only administer within 30 minutes of scheduled time
- **Multiple Statuses**: scheduled, given, held, refused, omitted
- **Audit Trail**: Tracks who administered, when, dosage given, route, and notes
- **User-Friendly UI**: Color-coded priorities, overdue indicators, professional interface
- **Dark Mode**: Complete dark mode support with proper color classes
- **Mobile Responsive**: Works on all screen sizes
- **Date-fns Integration**: Professional date/time formatting and calculations

**Code Quality:**
- ✅ PHP code formatted with Laravel Pint
- ✅ TypeScript code formatted with Prettier
- ✅ Type-checked with TypeScript (no errors in new code)
- ✅ Follows project patterns (Inertia Form, permission-based auth)
- ✅ Proper error handling and user feedback
- ✅ Full dark mode support with semantic color classes

**Files Created:**
- `app/Services/MedicationScheduleService.php` (125 lines)
- `app/Policies/MedicationAdministrationPolicy.php` (95 lines)
- `app/Http/Controllers/Ward/MedicationAdministrationController.php` (117 lines)
- `resources/js/components/Ward/MedicationAdministrationPanel.tsx` (635 lines)

**Files Modified:**
- `routes/wards.php` (added medication administration routes)
- `app/Http/Controllers/Consultation/ConsultationController.php` (auto-schedule integration)
- `resources/js/pages/Ward/Show.tsx` (added Meds button and panel integration)

**Dependencies Added:**
- `date-fns` (npm package for date formatting and manipulation)

---

### Phase 6: Ward Rounds (Doctors) ✅ COMPLETE
**Completion Date:** 2025-10-14 (Session 6)

#### ✅ Backend Complete:
- ✅ **WardRoundController** ([WardRoundController.php](app/Http/Controllers/Ward/WardRoundController.php:1-85))
  - `index()` - Fetch all ward rounds for an admission with doctor info
  - `store()` - Create new ward round with auto-assigned doctor_id and round_datetime
  - `update()` - Edit existing ward round (with time-based authorization via policy)
  - `destroy()` - Delete ward round (admin only for medical record integrity)

- ✅ **StoreWardRoundRequest** ([StoreWardRoundRequest.php](app/Http/Requests/StoreWardRoundRequest.php:1-39))
  - Validation rules for all fields with character limits
  - Custom error messages
  - Permission check: `ward_rounds.create`

- ✅ **WardRoundPolicy** ([WardRoundPolicy.php](app/Policies/WardRoundPolicy.php:1-74))
  - Permission-based authorization (not role-based)
  - `ward_rounds.view` - View ward rounds
  - `ward_rounds.create` - Record ward rounds
  - `ward_rounds.update` - Update own rounds within 24 hours
  - `ward_rounds.delete` - Delete rounds (admin only, medical record integrity)
  - `ward_rounds.restore` - Restore deleted rounds
  - `ward_rounds.force_delete` - Permanently delete rounds

- ✅ **Routes** ([wards.php](routes/wards.php:38-42))
  - GET `/admissions/{admission}/ward-rounds`
  - POST `/admissions/{admission}/ward-rounds`
  - PUT `/admissions/{admission}/ward-rounds/{wardRound}`
  - DELETE `/admissions/{admission}/ward-rounds/{wardRound}`

- ✅ **Permissions Added to Seeder** ([PermissionSeeder.php](database/seeders/PermissionSeeder.php:146-152))
  - Added comprehensive ward rounds permissions
  - Assigned to Doctor role (view, create, update)
  - Assigned to Nurse role (view only)
  - Admin has all permissions

#### ✅ Frontend Complete:
- ✅ **WardRoundModal Component** ([WardRoundModal.tsx](resources/js/components/Ward/WardRoundModal.tsx:1-487))
  - **Three View Modes**: List view, Create form, Edit form
  - **List View Features:**
    - Displays all ward rounds with color-coded patient status badges
    - Shows progress note, clinical impression, plan, doctor name, and datetime
    - Edit button (visible for rounds < 24 hours old)
    - Delete button (visible for admin only)
    - Empty state with "Record First Round" prompt

  - **Create/Edit Form Features:**
    - Patient status selection dropdown with icons (Improving, Stable, Deteriorating, Discharge Ready)
    - Progress note textarea (required, min 10 chars)
    - Clinical impression textarea (optional)
    - Treatment plan textarea (optional)
    - Real-time validation with inline error messages
    - Cancel and Save buttons with loading states

  - **Patient Status with Color-Coded UI:**
    - 🟢 **Improving** (Green) - ArrowUpCircle icon
    - 🔵 **Stable** (Blue) - MinusCircle icon
    - 🔴 **Deteriorating** (Red) - ArrowDownCircle icon
    - 🟣 **Discharge Ready** (Purple) - CheckCircle2 icon

  - **API Integration:**
    - Fetches rounds dynamically when modal opens
    - Refreshes list after create/update/delete operations
    - Uses Inertia `<Form>` component for proper form handling
    - Toast notifications using Sonner for user feedback

- ✅ **Ward Show Page Integration** ([Show.tsx](resources/js/pages/Ward/Show.tsx:762-814))
  - Added "Rounds" button alongside "Meds", "Notes", and "Vitals" buttons for each patient
  - Button displays Stethoscope icon and opens ward round modal
  - Modal state management with `wardRoundModalOpen` and `selectedAdmission`
  - Displays ward rounds count in patient metrics (line 608-623)
  - Buttons arranged in 2x2 grid for better layout

#### ✅ Key Features Implemented:
- **Medical Record Integrity:**
  - Ward rounds are permanent medical records
  - Edit allowed within 24 hours (doctors can only edit their own)
  - Delete restricted to admin only (not time-based)
  - All changes tracked with timestamps (created_at, updated_at)

- **Patient Status Tracking:**
  - Four status options: Improving, Stable, Deteriorating, Discharge Ready
  - Color-coded badges for quick visual identification
  - Status progression visible in round history

- **Comprehensive Documentation:**
  - Progress notes (required) - Detailed observations and examination findings
  - Clinical impressions (optional) - Summary assessment
  - Treatment plans (optional) - Management and next steps
  - Doctor identification and round datetime

- **User Experience:**
  - Clean, professional modal interface with three distinct views
  - Color-coded patient status badges for quick visual identification
  - Real-time error handling with toast notifications
  - Responsive design with dark mode support
  - Minimum character validation (10 chars for progress note)
  - Empty state UI when no rounds exist
  - Loading states during API calls
  - Edit/delete buttons with permission-based visibility

- **Data Integrity:**
  - Auto-assigns current user as doctor_id
  - Auto-sets round_datetime timestamp
  - Cannot edit rounds after 24 hours
  - Cannot delete rounds (admin only for audit trail)
  - All changes tracked with timestamps

**Code Quality:**
- ✅ PHP code formatted with Laravel Pint
- ✅ TypeScript code formatted with Prettier
- ✅ Type-checked with TypeScript (no errors)
- ✅ Follows project patterns (Inertia Form, permission-based auth, modal structure)
- ✅ Proper error handling and user feedback
- ✅ Full dark mode support with semantic color classes

**Files Created:**
- `app/Http/Controllers/Ward/WardRoundController.php` (85 lines)
- `app/Http/Requests/StoreWardRoundRequest.php` (39 lines)
- `app/Policies/WardRoundPolicy.php` (74 lines)
- `resources/js/components/Ward/WardRoundModal.tsx` (487 lines)

**Files Modified:**
- `routes/wards.php` (added ward rounds routes)
- `database/seeders/PermissionSeeder.php` (added ward rounds permissions and role assignments)
- `resources/js/pages/Ward/Show.tsx` (added Rounds button and modal integration)

---

### Phase 7: Remaining Work 📝 NOT STARTED

#### Phase 7: Consultation Page Admission Context
**Priority:** HIGH | **Est:** 3-4 hours
- ⏳ Update `app/Http/Controllers/Consultation/ConsultationController.php`
- ⏳ Update `resources/js/pages/Consultation/Show.tsx`
- ⏳ Add admission context banner
- ⏳ Add "Nursing Care" tab for admitted patients

---

### Testing & Deployment 📝 NOT STARTED
- ⏳ Create factories for new models
- ⏳ Create seeders with test data
- ⏳ Write unit tests for services
- ⏳ Write feature tests for workflows
- ⏳ Write browser tests (Pest v4) for critical paths
- ⏳ Verify dark mode support
- ⏳ Performance testing

---

### Quick Start Guide for Next Session

**🎉 Session 6 Summary:**
- ✅ Implemented Phase 6: Ward Rounds - Complete doctor documentation system
- ✅ Created WardRoundController with CRUD operations (index, store, update, destroy)
- ✅ Created StoreWardRoundRequest with comprehensive validation and custom error messages
- ✅ Created WardRoundPolicy with permission-based authorization (edit within 24h, admin-only delete)
- ✅ Built WardRoundModal component with three views (list, create, edit)
- ✅ Added patient status tracking with color-coded badges (Improving, Stable, Deteriorating, Discharge Ready)
- ✅ Added "Rounds" button in Ward Show page in 2x2 button grid
- ✅ Added ward rounds permissions to PermissionSeeder with role assignments
- ✅ Medical record integrity: time-based editing, admin-only deletion
- ✅ Full dark mode support, toast notifications, and proper error handling
- ✅ All code formatted with Pint and Prettier, type-checked with TypeScript

**Phase 6 is now complete! All 6 core ward management phases done! 🎊**

**Next: Phase 7 - Consultation Page Admission Context** (FINAL PHASE)
- Integrate admission context into consultation pages
- Add admission banner showing ward, bed, and admission info
- Add "Nursing Care" tab for admitted patients showing:
  - Medication administration status
  - Recent nursing notes
  - Ward rounds history
- Estimated: 3-4 hours

**Getting Started:**
1. Update `app/Http/Controllers/Consultation/ConsultationController.php`
   - Load `patientAdmission` relationship with ward, bed, nursing data
   - Eager load ward rounds, nursing notes, medication administrations

2. Update `resources/js/pages/Consultation/Show.tsx`
   - Add admission context banner at top (blue alert with ward/bed info)
   - Add "Nursing Care" tab to tabs component
   - Create sub-components for displaying nursing data

**What's Complete:**
- ✅ Phase 1: Database & Backend Foundation
- ✅ Phase 2: Ward Show Page Enhancements
- ✅ Phase 3: Vital Signs Recording (Nurses)
- ✅ Phase 4: Nursing Notes
- ✅ Phase 5: Medication Administration (Nurses)
- ✅ Phase 6: Ward Rounds (Doctors)
- ⏳ Phase 7: Consultation Page Admission Context (LAST REMAINING)

**Key Achievements:**
- Complete inpatient workflow from admission to discharge
- Four working modals/panels: Meds, Notes, Rounds, Vitals
- Permission-based authorization throughout
- Medical record integrity with time-based editing restrictions
- Professional UI with dark mode support
- Auto-schedule generation for medications
- Comprehensive audit trail for all nursing and medical activities

---

## 🎯 Key Design Decisions

### 1. **Attending Doctor Model**
- ❌ **Removed**: Fixed `attending_doctor_id` from `patient_admissions` table
- ✅ **Replaced with**: Activity-based audit trail
  - Any doctor with ward permissions can attend to patients
  - All actions tracked via respective tables (ward_rounds, prescriptions, etc.)
  - Better supports team-based care and shift handovers

### 2. **User Interface Architecture**

#### **Ward Show Page = Primary Hub**
- Default tab: **Current Patients** (not Beds)
- Enhanced DataTable with inline actions
- Quick access modals for common nursing tasks

#### **Two Approaches for Doctor Documentation**
1. **Quick Ward Round Interface**: Sequential, lightweight for daily rounds
2. **Full Consultation Page**: Comprehensive for complex cases

#### **Nurse Workflows - From Ward Page**
- Record Vitals → Modal
- Administer Medications → Slide-over panel
- Add Nursing Notes → Modal
- All actions optimized for batch processing across multiple patients

---

## 📊 Database Schema Changes

### Phase 1: Remove Attending Doctor Constraint

```sql
-- Migration: remove_attending_doctor_from_admissions
ALTER TABLE patient_admissions
DROP COLUMN attending_doctor_id;
```

### Phase 2: Add Nursing & Care Tables

#### **1. Medication Administration Records**
```php
Schema::create('medication_administrations', function (Blueprint $table) {
    $table->id();
    $table->foreignId('prescription_id')->constrained()->onDelete('cascade');
    $table->foreignId('patient_admission_id')->constrained()->onDelete('cascade');
    $table->foreignId('administered_by_id')->constrained('users'); // Nurse
    $table->dateTime('scheduled_time');
    $table->dateTime('administered_at')->nullable();
    $table->enum('status', ['scheduled', 'given', 'held', 'refused', 'omitted']);
    $table->string('dosage_given')->nullable();
    $table->string('route')->nullable(); // oral, IV, IM, etc.
    $table->text('notes')->nullable(); // reason for holding, patient response, etc.
    $table->timestamps();

    $table->index(['patient_admission_id', 'scheduled_time']);
    $table->index(['administered_by_id', 'administered_at']);
});
```

#### **2. Nursing Notes**
```php
Schema::create('nursing_notes', function (Blueprint $table) {
    $table->id();
    $table->foreignId('patient_admission_id')->constrained()->onDelete('cascade');
    $table->foreignId('nurse_id')->constrained('users');
    $table->enum('type', ['assessment', 'care', 'observation', 'incident', 'handover']);
    $table->text('note');
    $table->dateTime('noted_at');
    $table->timestamps();

    $table->index(['patient_admission_id', 'noted_at']);
});
```

#### **3. Ward Rounds**
```php
Schema::create('ward_rounds', function (Blueprint $table) {
    $table->id();
    $table->foreignId('patient_admission_id')->constrained()->onDelete('cascade');
    $table->foreignId('doctor_id')->constrained('users');
    $table->text('progress_note');
    $table->enum('patient_status', [
        'improving',
        'stable',
        'deteriorating',
        'discharge_ready'
    ]);
    $table->text('clinical_impression')->nullable();
    $table->text('plan')->nullable();
    $table->dateTime('round_datetime');
    $table->timestamps();

    $table->index(['patient_admission_id', 'round_datetime']);
    $table->index('doctor_id');
});
```

#### **4. Update Vital Signs Table**
```sql
-- Add tracking for admitted patients
ALTER TABLE vital_signs
ADD COLUMN patient_admission_id BIGINT UNSIGNED NULL AFTER patient_checkin_id,
ADD CONSTRAINT fk_vital_admission
    FOREIGN KEY (patient_admission_id)
    REFERENCES patient_admissions(id) ON DELETE CASCADE;

-- recorded_by_id already exists, ensure it's indexed
CREATE INDEX idx_vital_signs_recorded_by ON vital_signs(recorded_by_id);
```

---

## 🏗️ Implementation Phases

### **Phase 1: Database & Backend Foundation**
**Priority: HIGH | Est: 2-3 hours** ✅ **COMPLETE**

- [x] 1.1 Create migration to remove `attending_doctor_id` from `patient_admissions`
- [x] 1.2 Create `medication_administrations` table migration
- [x] 1.3 Create `nursing_notes` table migration
- [x] 1.4 Create `ward_rounds` table migration
- [x] 1.5 Update `vital_signs` table to support admission tracking
- [x] 1.6 Create model: `MedicationAdministration.php`
- [x] 1.7 Create model: `NursingNote.php`
- [x] 1.8 Create model: `WardRound.php`
- [x] 1.9 Add relationships to existing models
- [ ] 1.10 Create factories for new models (deferred to testing phase)
- [ ] 1.11 Create seeders with test data (deferred to testing phase)

**Status:** All migrations run successfully. All models created and relationships configured.

---

### **Phase 2: Ward Show Page Enhancements**
**Priority: HIGH | Est: 4-5 hours**

#### **2.1 Backend: Enhanced Ward Controller** ✅ **COMPLETE**

**File:** `app/Http/Controllers/Ward/WardController.php`

```php
public function show(Ward $ward)
{
    $ward->load([
        'beds:id,ward_id,bed_number,status,type,is_active',
        'admissions' => function ($query) {
            $query->where('status', 'admitted')
                ->with([
                    'patient:id,first_name,last_name,date_of_birth,gender',
                    'bed:id,bed_number',
                    'consultation.doctor:id,name', // Get doctor from consultation
                    'latestVitalSigns' => function ($q) {
                        $q->latest('recorded_at')->limit(1);
                    },
                    'pendingMedications' => function ($q) {
                        $q->where('status', 'scheduled')
                          ->where('scheduled_time', '<=', now()->addHours(2));
                    }
                ])
                ->withCount(['wardRounds', 'nursingNotes'])
                ->orderBy('admitted_at', 'desc');
        },
    ]);

    return Inertia::render('Ward/Show', [
        'ward' => $ward,
        'stats' => [
            'total_patients' => $ward->admissions->count(),
            'pending_meds_count' => $ward->admissions->sum(fn($a) => $a->pendingMedications->count()),
            'patients_needing_vitals' => $ward->admissions->filter(fn($a) =>
                !$a->latestVitalSigns ||
                $a->latestVitalSigns->recorded_at < now()->subHours(4)
            )->count(),
        ]
    ]);
}
```

#### **2.2 Frontend: Enhanced Ward Show Page**

**File:** `resources/js/pages/Ward/Show.tsx`

**Changes:**
- [ ] Change default tab from `"beds"` to `"patients"`
- [ ] Replace simple list with TanStack DataTable
- [ ] Add action dropdown menu to each patient row
- [ ] Add stats cards for ward metrics
- [ ] Implement search and filtering

**DataTable Columns:**
```typescript
const columns: ColumnDef<PatientAdmission>[] = [
  {
    accessorKey: 'patient.full_name',
    header: 'Patient Name',
    cell: ({ row }) => (
      <div>
        <div className="font-medium">
          {row.original.patient.first_name} {row.original.patient.last_name}
        </div>
        <div className="text-sm text-gray-500">
          {calculateAge(row.original.patient.date_of_birth)}y •
          {row.original.patient.gender}
        </div>
      </div>
    ),
  },
  {
    accessorKey: 'admission_number',
    header: 'Admission #',
  },
  {
    accessorKey: 'bed.bed_number',
    header: 'Bed',
    cell: ({ row }) => (
      <Badge variant="outline">
        Bed {row.original.bed?.bed_number || 'Unassigned'}
      </Badge>
    ),
  },
  {
    id: 'latest_vitals',
    header: 'Latest Vitals',
    cell: ({ row }) => {
      const vitals = row.original.latestVitalSigns?.[0];
      if (!vitals) {
        return <span className="text-red-500">No vitals</span>;
      }
      return (
        <div className="text-sm">
          <div>BP: {vitals.blood_pressure_systolic}/{vitals.blood_pressure_diastolic}</div>
          <div className="text-gray-500">
            {formatRelativeTime(vitals.recorded_at)}
          </div>
        </div>
      );
    },
  },
  {
    accessorKey: 'admitted_at',
    header: 'Admitted',
    cell: ({ row }) => (
      <div>
        <div>{formatDate(row.original.admitted_at)}</div>
        <div className="text-sm text-gray-500">
          Day {calculateDaysSince(row.original.admitted_at)}
        </div>
      </div>
    ),
  },
  {
    id: 'actions',
    cell: ({ row }) => <PatientRowActions patient={row.original} />,
  },
];
```

**Action Menu Component:**
```typescript
function PatientRowActions({ patient }: { patient: PatientAdmission }) {
  return (
    <DropdownMenu>
      <DropdownMenuTrigger asChild>
        <Button variant="ghost" size="sm">
          <MoreVertical className="h-4 w-4" />
        </Button>
      </DropdownMenuTrigger>
      <DropdownMenuContent align="end">
        <DropdownMenuItem onClick={() => openVitalsModal(patient)}>
          <Activity className="mr-2 h-4 w-4" />
          Record Vitals
        </DropdownMenuItem>
        <DropdownMenuItem onClick={() => openMedicationPanel(patient)}>
          <Pill className="mr-2 h-4 w-4" />
          Administer Meds
        </DropdownMenuItem>
        <DropdownMenuItem onClick={() => openNursingNoteModal(patient)}>
          <FileText className="mr-2 h-4 w-4" />
          Add Nursing Note
        </DropdownMenuItem>
        <DropdownMenuSeparator />
        <DropdownMenuItem onClick={() => startWardRound(patient)}>
          <Stethoscope className="mr-2 h-4 w-4" />
          Quick Ward Round
        </DropdownMenuItem>
        <DropdownMenuItem onClick={() => viewFullChart(patient)}>
          <ClipboardList className="mr-2 h-4 w-4" />
          View Full Chart
        </DropdownMenuItem>
      </DropdownMenuContent>
    </DropdownMenu>
  );
}
```

---

### **Phase 3: Vital Signs Recording (Nurses)**
**Priority: HIGH | Est: 3-4 hours**

#### **3.1 Backend: Vital Signs Controller Enhancement**

**File:** `app/Http/Controllers/Vitals/VitalSignController.php` (create if doesn't exist)

```php
<?php

namespace App\Http\Controllers\Vitals;

use App\Http\Controllers\Controller;
use App\Models\VitalSign;
use App\Models\PatientAdmission;
use Illuminate\Http\Request;

class VitalSignController extends Controller
{
    public function store(Request $request, PatientAdmission $admission)
    {
        $validated = $request->validate([
            'temperature' => 'required|numeric|min:35|max:45',
            'blood_pressure_systolic' => 'required|integer|min:60|max:250',
            'blood_pressure_diastolic' => 'required|integer|min:40|max:150',
            'pulse_rate' => 'required|integer|min:40|max:200',
            'respiratory_rate' => 'required|integer|min:8|max:60',
            'oxygen_saturation' => 'nullable|integer|min:70|max:100',
            'notes' => 'nullable|string|max:500',
        ]);

        $vitalSign = VitalSign::create([
            ...$validated,
            'patient_admission_id' => $admission->id,
            'patient_checkin_id' => $admission->consultation->patient_checkin_id ?? null,
            'recorded_by_id' => auth()->id(),
            'recorded_at' => now(),
        ]);

        return redirect()->back()->with('success', 'Vital signs recorded successfully.');
    }
}
```

**Route:** `routes/wards.php`
```php
Route::post('/admissions/{admission}/vitals', [VitalSignController::class, 'store'])
    ->name('admissions.vitals.store');
```

#### **3.2 Frontend: Vital Signs Modal**

**File:** `resources/js/components/Ward/RecordVitalsModal.tsx`

```typescript
interface RecordVitalsModalProps {
  admission: PatientAdmission;
  open: boolean;
  onOpenChange: (open: boolean) => void;
}

export function RecordVitalsModal({
  admission,
  open,
  onOpenChange
}: RecordVitalsModalProps) {
  const { data, setData, post, processing, errors } = useForm({
    temperature: '',
    blood_pressure_systolic: '',
    blood_pressure_diastolic: '',
    pulse_rate: '',
    respiratory_rate: '',
    oxygen_saturation: '',
    notes: '',
  });

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    post(`/admissions/${admission.id}/vitals`, {
      onSuccess: () => {
        onOpenChange(false);
        // Reset form
      },
    });
  };

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-w-2xl">
        <DialogHeader>
          <DialogTitle>
            Record Vital Signs - {admission.patient.first_name} {admission.patient.last_name}
          </DialogTitle>
          <DialogDescription>
            Bed {admission.bed?.bed_number} • Admitted {formatRelativeTime(admission.admitted_at)}
          </DialogDescription>
        </DialogHeader>

        <form onSubmit={handleSubmit} className="space-y-6">
          <div className="grid grid-cols-2 gap-4">
            {/* Temperature */}
            <div>
              <Label htmlFor="temperature">Temperature (°F)</Label>
              <Input
                id="temperature"
                type="number"
                step="0.1"
                placeholder="98.6"
                value={data.temperature}
                onChange={(e) => setData('temperature', e.target.value)}
                className={errors.temperature ? 'border-red-500' : ''}
              />
              {errors.temperature && (
                <p className="text-sm text-red-500">{errors.temperature}</p>
              )}
            </div>

            {/* Blood Pressure */}
            <div>
              <Label>Blood Pressure (mmHg)</Label>
              <div className="flex gap-2">
                <Input
                  type="number"
                  placeholder="120"
                  value={data.blood_pressure_systolic}
                  onChange={(e) => setData('blood_pressure_systolic', e.target.value)}
                />
                <span className="flex items-center">/</span>
                <Input
                  type="number"
                  placeholder="80"
                  value={data.blood_pressure_diastolic}
                  onChange={(e) => setData('blood_pressure_diastolic', e.target.value)}
                />
              </div>
            </div>

            {/* Pulse Rate */}
            <div>
              <Label htmlFor="pulse_rate">Pulse Rate (bpm)</Label>
              <Input
                id="pulse_rate"
                type="number"
                placeholder="72"
                value={data.pulse_rate}
                onChange={(e) => setData('pulse_rate', e.target.value)}
              />
            </div>

            {/* Respiratory Rate */}
            <div>
              <Label htmlFor="respiratory_rate">Respiratory Rate (/min)</Label>
              <Input
                id="respiratory_rate"
                type="number"
                placeholder="16"
                value={data.respiratory_rate}
                onChange={(e) => setData('respiratory_rate', e.target.value)}
              />
            </div>

            {/* Oxygen Saturation */}
            <div>
              <Label htmlFor="oxygen_saturation">O₂ Saturation (%)</Label>
              <Input
                id="oxygen_saturation"
                type="number"
                placeholder="98"
                value={data.oxygen_saturation}
                onChange={(e) => setData('oxygen_saturation', e.target.value)}
              />
            </div>
          </div>

          {/* Notes */}
          <div>
            <Label htmlFor="notes">Notes (Optional)</Label>
            <Textarea
              id="notes"
              placeholder="Any observations..."
              value={data.notes}
              onChange={(e) => setData('notes', e.target.value)}
              rows={2}
            />
          </div>

          <DialogFooter>
            <Button
              type="button"
              variant="outline"
              onClick={() => onOpenChange(false)}
            >
              Cancel
            </Button>
            <Button type="submit" disabled={processing}>
              {processing ? 'Recording...' : 'Save Vital Signs'}
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  );
}
```

---

### **Phase 4: Medication Administration (Nurses)**
**Priority: HIGH | Est: 5-6 hours**

#### **4.1 Backend: Medication Schedule Generation**

When a prescription is created for an admitted patient, generate scheduled administration times.

**File:** `app/Services/MedicationScheduleService.php`

```php
<?php

namespace App\Services;

use App\Models\Prescription;
use App\Models\MedicationAdministration;
use Carbon\Carbon;

class MedicationScheduleService
{
    public function generateSchedule(Prescription $prescription): void
    {
        // Only for admitted patients
        if (!$prescription->consultation->patientAdmission) {
            return;
        }

        $schedules = $this->parseFrequency($prescription->frequency);
        $duration = $this->parseDuration($prescription->duration);

        $startDate = now();
        $endDate = $startDate->copy()->addDays($duration);

        foreach ($this->generateDates($startDate, $endDate) as $date) {
            foreach ($schedules as $time) {
                MedicationAdministration::create([
                    'prescription_id' => $prescription->id,
                    'patient_admission_id' => $prescription->consultation->patientAdmission->id,
                    'scheduled_time' => Carbon::parse($date->format('Y-m-d') . ' ' . $time),
                    'status' => 'scheduled',
                ]);
            }
        }
    }

    private function parseFrequency(string $frequency): array
    {
        // Parse frequencies like "TDS", "BD", "QID", "Q6H"
        $schedules = [
            'OD' => ['08:00'],
            'BD' => ['08:00', '20:00'],
            'TDS' => ['08:00', '14:00', '20:00'],
            'QID' => ['08:00', '12:00', '16:00', '20:00'],
            'Q6H' => ['06:00', '12:00', '18:00', '00:00'],
            'Q8H' => ['08:00', '16:00', '00:00'],
        ];

        return $schedules[strtoupper($frequency)] ?? ['08:00'];
    }

    private function parseDuration(string $duration): int
    {
        // Parse durations like "5 days", "2 weeks", "1 month"
        if (preg_match('/(\d+)\s*(day|week|month)/i', $duration, $matches)) {
            $value = (int) $matches[1];
            $unit = strtolower($matches[2]);

            return match ($unit) {
                'week' => $value * 7,
                'month' => $value * 30,
                default => $value,
            };
        }

        return 5; // default 5 days
    }

    private function generateDates(Carbon $start, Carbon $end): array
    {
        $dates = [];
        $current = $start->copy();

        while ($current <= $end) {
            $dates[] = $current->copy();
            $current->addDay();
        }

        return $dates;
    }
}
```

**Trigger schedule generation in PrescriptionController:**

```php
public function store(Request $request, Consultation $consultation)
{
    // ... validation ...

    $prescription = Prescription::create([...]);

    // Generate administration schedule for admitted patients
    if ($consultation->patientAdmission) {
        app(MedicationScheduleService::class)->generateSchedule($prescription);
    }

    return redirect()->back();
}
```

#### **4.2 Backend: Medication Administration Controller**

**File:** `app/Http/Controllers/Ward/MedicationAdministrationController.php`

```php
<?php

namespace App\Http\Controllers\Ward;

use App\Http\Controllers\Controller;
use App\Models\PatientAdmission;
use App\Models\MedicationAdministration;
use Illuminate\Http\Request;

class MedicationAdministrationController extends Controller
{
    public function index(PatientAdmission $admission)
    {
        $medications = MedicationAdministration::where('patient_admission_id', $admission->id)
            ->with('prescription.drug')
            ->orderBy('scheduled_time', 'asc')
            ->get()
            ->groupBy(function ($med) {
                return $med->scheduled_time->format('Y-m-d');
            });

        return response()->json($medications);
    }

    public function administer(Request $request, MedicationAdministration $administration)
    {
        $validated = $request->validate([
            'dosage_given' => 'required|string',
            'route' => 'required|string',
            'notes' => 'nullable|string|max:500',
        ]);

        $administration->update([
            'status' => 'given',
            'administered_at' => now(),
            'administered_by_id' => auth()->id(),
            'dosage_given' => $validated['dosage_given'],
            'route' => $validated['route'],
            'notes' => $validated['notes'] ?? null,
        ]);

        return redirect()->back()->with('success', 'Medication administered successfully.');
    }

    public function hold(Request $request, MedicationAdministration $administration)
    {
        $validated = $request->validate([
            'notes' => 'required|string|max:500',
        ]);

        $administration->update([
            'status' => 'held',
            'administered_by_id' => auth()->id(),
            'notes' => $validated['notes'],
        ]);

        return redirect()->back()->with('success', 'Medication held.');
    }
}
```

**Routes:** `routes/wards.php`
```php
Route::prefix('admissions/{admission}')->group(function () {
    Route::get('/medications', [MedicationAdministrationController::class, 'index'])
        ->name('admissions.medications.index');
    Route::post('/medications/{administration}/administer', [MedicationAdministrationController::class, 'administer'])
        ->name('admissions.medications.administer');
    Route::post('/medications/{administration}/hold', [MedicationAdministrationController::class, 'hold'])
        ->name('admissions.medications.hold');
});
```

#### **4.3 Frontend: Medication Administration Panel**

**File:** `resources/js/components/Ward/MedicationAdministrationPanel.tsx`

```typescript
interface MedicationAdministrationPanelProps {
  admission: PatientAdmission;
  open: boolean;
  onOpenChange: (open: boolean) => void;
}

export function MedicationAdministrationPanel({
  admission,
  open,
  onOpenChange,
}: MedicationAdministrationPanelProps) {
  const [medications, setMedications] = useState<MedicationAdministration[]>([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    if (open) {
      fetch(`/admissions/${admission.id}/medications`)
        .then((res) => res.json())
        .then((data) => {
          setMedications(data);
          setLoading(false);
        });
    }
  }, [open, admission.id]);

  const dueNow = medications.filter((med) =>
    med.status === 'scheduled' &&
    new Date(med.scheduled_time) <= new Date()
  );

  const upcoming = medications.filter((med) =>
    med.status === 'scheduled' &&
    new Date(med.scheduled_time) > new Date()
  );

  return (
    <Sheet open={open} onOpenChange={onOpenChange}>
      <SheetContent side="right" className="w-full sm:max-w-2xl overflow-y-auto">
        <SheetHeader>
          <SheetTitle>
            Medication Administration - {admission.patient.first_name} {admission.patient.last_name}
          </SheetTitle>
          <SheetDescription>
            Bed {admission.bed?.bed_number} • {dueNow.length} medication(s) due now
          </SheetDescription>
        </SheetHeader>

        <div className="mt-6 space-y-6">
          {/* Due Now Section */}
          {dueNow.length > 0 && (
            <div>
              <h3 className="text-lg font-semibold text-red-600 mb-3">
                Due Now ({dueNow.length})
              </h3>
              <div className="space-y-3">
                {dueNow.map((med) => (
                  <MedicationCard
                    key={med.id}
                    medication={med}
                    priority="high"
                  />
                ))}
              </div>
            </div>
          )}

          {/* Upcoming Section */}
          <div>
            <h3 className="text-lg font-semibold mb-3">
              Scheduled Today ({upcoming.length})
            </h3>
            <div className="space-y-3">
              {upcoming.map((med) => (
                <MedicationCard
                  key={med.id}
                  medication={med}
                  priority="normal"
                />
              ))}
            </div>
          </div>

          {/* Today's Timeline */}
          <div>
            <h3 className="text-lg font-semibold mb-3">Today's Timeline</h3>
            <MedicationTimeline medications={medications} />
          </div>
        </div>
      </SheetContent>
    </Sheet>
  );
}

function MedicationCard({ medication, priority }: {
  medication: MedicationAdministration;
  priority: 'high' | 'normal';
}) {
  const [showAdministerDialog, setShowAdministerDialog] = useState(false);

  return (
    <>
      <Card className={priority === 'high' ? 'border-red-300 bg-red-50' : ''}>
        <CardContent className="p-4">
          <div className="flex items-start justify-between">
            <div className="flex-1">
              <h4 className="font-semibold">
                {medication.prescription.drug?.name || medication.prescription.medication_name}
              </h4>
              <p className="text-sm text-gray-600">
                Dose: {medication.prescription.dosage} • Route: {medication.prescription.route || 'PO'}
              </p>
              <p className="text-sm text-gray-500 mt-1">
                Scheduled: {format(new Date(medication.scheduled_time), 'HH:mm')}
              </p>
            </div>
            <div className="flex gap-2">
              <Button
                size="sm"
                onClick={() => setShowAdministerDialog(true)}
                className="bg-green-600 hover:bg-green-700"
              >
                ✓ Given
              </Button>
              <Button
                size="sm"
                variant="outline"
                onClick={() => handleHold(medication)}
              >
                Hold
              </Button>
            </div>
          </div>
        </CardContent>
      </Card>

      <AdministerMedicationDialog
        medication={medication}
        open={showAdministerDialog}
        onOpenChange={setShowAdministerDialog}
      />
    </>
  );
}
```

---

### **Phase 5: Ward Rounds (Doctors)**
**Priority: MEDIUM | Est: 6-8 hours**

#### **5.1 Backend: Ward Round Controller**

**File:** `app/Http/Controllers/Ward/WardRoundController.php`

```php
<?php

namespace App\Http\Controllers\Ward;

use App\Http\Controllers\Controller;
use App\Models\PatientAdmission;
use App\Models\WardRound;
use Illuminate\Http\Request;
use Inertia\Inertia;

class WardRoundController extends Controller
{
    public function create(Ward $ward)
    {
        $patients = $ward->admissions()
            ->where('status', 'admitted')
            ->with([
                'patient',
                'bed',
                'consultation.diagnoses.diagnosis',
                'latestVitalSigns',
                'wardRounds' => fn($q) => $q->latest()->limit(1),
            ])
            ->get();

        return Inertia::render('Ward/WardRound', [
            'ward' => $ward,
            'patients' => $patients,
        ]);
    }

    public function store(Request $request, PatientAdmission $admission)
    {
        $validated = $request->validate([
            'progress_note' => 'required|string',
            'patient_status' => 'required|in:improving,stable,deteriorating,discharge_ready',
            'clinical_impression' => 'nullable|string',
            'plan' => 'nullable|string',
        ]);

        WardRound::create([
            ...$validated,
            'patient_admission_id' => $admission->id,
            'doctor_id' => auth()->id(),
            'round_datetime' => now(),
        ]);

        return redirect()->back()->with('success', 'Ward round documented.');
    }
}
```

**Routes:** `routes/wards.php`
```php
Route::get('/wards/{ward}/rounds/create', [WardRoundController::class, 'create'])
    ->name('wards.rounds.create');
Route::post('/admissions/{admission}/rounds', [WardRoundController::class, 'store'])
    ->name('admissions.rounds.store');
```

#### **5.2 Frontend: Ward Round Interface**

**File:** `resources/js/pages/Ward/WardRound.tsx`

```typescript
interface WardRoundProps {
  ward: Ward;
  patients: PatientAdmission[];
}

export default function WardRound({ ward, patients }: WardRoundProps) {
  const [currentIndex, setCurrentIndex] = useState(0);
  const currentPatient = patients[currentIndex];

  const { data, setData, post, processing } = useForm({
    progress_note: '',
    patient_status: 'stable',
    clinical_impression: '',
    plan: '',
  });

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    post(`/admissions/${currentPatient.id}/rounds`, {
      onSuccess: () => {
        // Move to next patient or exit
        if (currentIndex < patients.length - 1) {
          setCurrentIndex(currentIndex + 1);
          // Reset form
          setData({
            progress_note: '',
            patient_status: 'stable',
            clinical_impression: '',
            plan: '',
          });
        } else {
          // All patients done
          router.visit(`/wards/${ward.id}`);
        }
      },
    });
  };

  return (
    <AppLayout
      breadcrumbs={[
        { title: 'Wards', href: '/wards' },
        { title: ward.name, href: `/wards/${ward.id}` },
        { title: 'Ward Rounds', href: '' },
      ]}
    >
      <Head title={`Ward Rounds - ${ward.name}`} />

      <div className="space-y-6">
        {/* Progress Bar */}
        <Card>
          <CardContent className="p-4">
            <div className="flex items-center justify-between mb-2">
              <h2 className="text-lg font-semibold">
                Ward Round Progress
              </h2>
              <span className="text-sm text-gray-600">
                Patient {currentIndex + 1} of {patients.length}
              </span>
            </div>
            <Progress
              value={((currentIndex + 1) / patients.length) * 100}
              className="h-2"
            />
          </CardContent>
        </Card>

        {/* Patient Card */}
        <Card>
          <CardHeader className="bg-blue-50 dark:bg-blue-950">
            <div className="flex items-center justify-between">
              <div>
                <CardTitle className="text-2xl">
                  {currentPatient.patient.first_name} {currentPatient.patient.last_name}
                </CardTitle>
                <p className="text-sm text-gray-600 mt-1">
                  Bed {currentPatient.bed?.bed_number} •
                  Admission: {currentPatient.admission_number} •
                  Day {calculateDaysSince(currentPatient.admitted_at)}
                </p>
              </div>
              <Badge variant="outline" className="text-lg">
                {calculateAge(currentPatient.patient.date_of_birth)}y •
                {currentPatient.patient.gender}
              </Badge>
            </div>
          </CardHeader>

          <CardContent className="p-6 space-y-6">
            {/* Quick Review */}
            <div className="grid grid-cols-3 gap-4">
              <div className="rounded-lg border p-4 bg-gray-50 dark:bg-gray-900">
                <h4 className="text-sm font-medium text-gray-600 mb-2">
                  Admission Diagnosis
                </h4>
                <p className="text-sm">
                  {currentPatient.admission_reason}
                </p>
              </div>

              <div className="rounded-lg border p-4 bg-gray-50 dark:bg-gray-900">
                <h4 className="text-sm font-medium text-gray-600 mb-2">
                  Latest Vitals
                </h4>
                {currentPatient.latestVitalSigns?.[0] ? (
                  <div className="text-sm space-y-1">
                    <p>BP: {currentPatient.latestVitalSigns[0].blood_pressure_systolic}/{currentPatient.latestVitalSigns[0].blood_pressure_diastolic}</p>
                    <p>Temp: {currentPatient.latestVitalSigns[0].temperature}°F</p>
                    <p className="text-gray-500">
                      {formatRelativeTime(currentPatient.latestVitalSigns[0].recorded_at)}
                    </p>
                  </div>
                ) : (
                  <p className="text-sm text-red-500">No vitals recorded</p>
                )}
              </div>

              <div className="rounded-lg border p-4 bg-gray-50 dark:bg-gray-900">
                <h4 className="text-sm font-medium text-gray-600 mb-2">
                  Last Round
                </h4>
                {currentPatient.wardRounds?.[0] ? (
                  <div className="text-sm">
                    <Badge variant="outline">
                      {currentPatient.wardRounds[0].patient_status}
                    </Badge>
                    <p className="text-gray-500 mt-1">
                      {formatRelativeTime(currentPatient.wardRounds[0].round_datetime)}
                    </p>
                  </div>
                ) : (
                  <p className="text-sm text-gray-500">No previous rounds</p>
                )}
              </div>
            </div>

            {/* Ward Round Form */}
            <form onSubmit={handleSubmit} className="space-y-4">
              <div>
                <Label htmlFor="progress_note">Progress Note *</Label>
                <Textarea
                  id="progress_note"
                  placeholder="Document patient's progress, examination findings, and observations..."
                  value={data.progress_note}
                  onChange={(e) => setData('progress_note', e.target.value)}
                  rows={6}
                  className="font-mono text-sm"
                  required
                />
              </div>

              <div className="grid grid-cols-2 gap-4">
                <div>
                  <Label htmlFor="clinical_impression">Clinical Impression</Label>
                  <Textarea
                    id="clinical_impression"
                    placeholder="Summary assessment..."
                    value={data.clinical_impression}
                    onChange={(e) => setData('clinical_impression', e.target.value)}
                    rows={3}
                  />
                </div>

                <div>
                  <Label htmlFor="plan">Plan</Label>
                  <Textarea
                    id="plan"
                    placeholder="Management plan..."
                    value={data.plan}
                    onChange={(e) => setData('plan', e.target.value)}
                    rows={3}
                  />
                </div>
              </div>

              <div>
                <Label>Patient Status *</Label>
                <RadioGroup
                  value={data.patient_status}
                  onValueChange={(value) => setData('patient_status', value)}
                  className="flex gap-4 mt-2"
                >
                  <div className="flex items-center space-x-2">
                    <RadioGroupItem value="improving" id="improving" />
                    <Label htmlFor="improving" className="cursor-pointer">
                      📈 Improving
                    </Label>
                  </div>
                  <div className="flex items-center space-x-2">
                    <RadioGroupItem value="stable" id="stable" />
                    <Label htmlFor="stable" className="cursor-pointer">
                      ➡️ Stable
                    </Label>
                  </div>
                  <div className="flex items-center space-x-2">
                    <RadioGroupItem value="deteriorating" id="deteriorating" />
                    <Label htmlFor="deteriorating" className="cursor-pointer">
                      📉 Deteriorating
                    </Label>
                  </div>
                  <div className="flex items-center space-x-2">
                    <RadioGroupItem value="discharge_ready" id="discharge_ready" />
                    <Label htmlFor="discharge_ready" className="cursor-pointer">
                      ✅ Discharge Ready
                    </Label>
                  </div>
                </RadioGroup>
              </div>

              {/* Navigation Buttons */}
              <div className="flex justify-between pt-4">
                <Button
                  type="button"
                  variant="outline"
                  onClick={() => setCurrentIndex(Math.max(0, currentIndex - 1))}
                  disabled={currentIndex === 0}
                >
                  ◀ Previous Patient
                </Button>

                <div className="flex gap-2">
                  <Button
                    type="button"
                    variant="ghost"
                    onClick={() => router.visit(`/wards/${ward.id}`)}
                  >
                    Exit Rounds
                  </Button>
                  <Button type="submit" disabled={processing}>
                    {processing ? 'Saving...' :
                     currentIndex === patients.length - 1 ? 'Save & Complete' : 'Save & Next ▶'}
                  </Button>
                </div>
              </div>
            </form>
          </CardContent>
        </Card>
      </div>
    </AppLayout>
  );
}
```

---

### **Phase 6: Nursing Notes**
**Priority: MEDIUM | Est: 2-3 hours**

#### **6.1 Backend: Nursing Notes Controller**

**File:** `app/Http/Controllers/Ward/NursingNoteController.php`

```php
<?php

namespace App\Http\Controllers\Ward;

use App\Http\Controllers\Controller;
use App\Models\PatientAdmission;
use App\Models\NursingNote;
use Illuminate\Http\Request;

class NursingNoteController extends Controller
{
    public function store(Request $request, PatientAdmission $admission)
    {
        $validated = $request->validate([
            'type' => 'required|in:assessment,care,observation,incident,handover',
            'note' => 'required|string',
        ]);

        NursingNote::create([
            ...$validated,
            'patient_admission_id' => $admission->id,
            'nurse_id' => auth()->id(),
            'noted_at' => now(),
        ]);

        return redirect()->back()->with('success', 'Nursing note added.');
    }
}
```

**Route:** `routes/wards.php`
```php
Route::post('/admissions/{admission}/nursing-notes', [NursingNoteController::class, 'store'])
    ->name('admissions.nursing-notes.store');
```

#### **6.2 Frontend: Nursing Note Modal**

**File:** `resources/js/components/Ward/NursingNoteModal.tsx`

```typescript
interface NursingNoteModalProps {
  admission: PatientAdmission;
  open: boolean;
  onOpenChange: (open: boolean) => void;
}

export function NursingNoteModal({
  admission,
  open,
  onOpenChange,
}: NursingNoteModalProps) {
  const { data, setData, post, processing } = useForm({
    type: 'care',
    note: '',
  });

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    post(`/admissions/${admission.id}/nursing-notes`, {
      onSuccess: () => {
        onOpenChange(false);
      },
    });
  };

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-w-2xl">
        <DialogHeader>
          <DialogTitle>
            Add Nursing Note - {admission.patient.first_name} {admission.patient.last_name}
          </DialogTitle>
          <DialogDescription>
            Bed {admission.bed?.bed_number}
          </DialogDescription>
        </DialogHeader>

        <form onSubmit={handleSubmit} className="space-y-4">
          <div>
            <Label htmlFor="type">Note Type</Label>
            <Select value={data.type} onValueChange={(value) => setData('type', value)}>
              <SelectTrigger id="type">
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="assessment">Assessment</SelectItem>
                <SelectItem value="care">Care</SelectItem>
                <SelectItem value="observation">Observation</SelectItem>
                <SelectItem value="incident">Incident</SelectItem>
                <SelectItem value="handover">Handover</SelectItem>
              </SelectContent>
            </Select>
          </div>

          <div>
            <Label htmlFor="note">Note</Label>
            <Textarea
              id="note"
              placeholder="Enter nursing note..."
              value={data.note}
              onChange={(e) => setData('note', e.target.value)}
              rows={8}
              required
            />
          </div>

          <DialogFooter>
            <Button
              type="button"
              variant="outline"
              onClick={() => onOpenChange(false)}
            >
              Cancel
            </Button>
            <Button type="submit" disabled={processing}>
              {processing ? 'Saving...' : 'Save Note'}
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  );
}
```

---

### **Phase 7: Consultation Page Admission Context**
**Priority: HIGH | Est: 3-4 hours**

#### **7.1 Backend: Load Admission Context**

**File:** `app/Http/Controllers/Consultation/ConsultationController.php`

Update the `show` method:
```php
public function show(Consultation $consultation)
{
    $consultation->load([
        // ... existing relationships ...
        'patientAdmission' => function ($query) {
            $query->with([
                'ward:id,name,code',
                'bed:id,bed_number',
                'wardRounds' => fn($q) => $q->latest()->limit(3)->with('doctor:id,name'),
                'nursingNotes' => fn($q) => $q->latest()->limit(10)->with('nurse:id,name'),
                'medicationAdministrations' => fn($q) => $q->with('prescription.drug')
                    ->where('scheduled_time', '>=', now()->subDays(2))
                    ->orderBy('scheduled_time', 'desc'),
            ]);
        },
    ]);

    return Inertia::render('Consultation/Show', [
        'consultation' => $consultation,
        // ... other props ...
    ]);
}
```

#### **7.2 Frontend: Admission Context Banner**

**File:** `resources/js/pages/Consultation/Show.tsx`

Add at the top of the page (after header):

```typescript
{consultation.patient_admission && (
  <Card className="border-blue-500 bg-blue-50 dark:bg-blue-950">
    <CardContent className="p-4">
      <div className="flex items-center justify-between">
        <div className="flex items-center gap-4">
          <Hospital className="h-6 w-6 text-blue-600" />
          <div>
            <h3 className="font-semibold text-blue-900 dark:text-blue-100">
              INPATIENT CARE MODE
            </h3>
            <p className="text-sm text-blue-700 dark:text-blue-300">
              Ward: {consultation.patient_admission.ward.name} •
              Bed {consultation.patient_admission.bed?.bed_number} •
              Admitted {formatRelativeTime(consultation.patient_admission.admitted_at)} ago
            </p>
          </div>
        </div>
        <div className="flex gap-2">
          <Button
            variant="outline"
            size="sm"
            onClick={() => router.visit(`/wards/${consultation.patient_admission.ward_id}`)}
          >
            View Ward
          </Button>
          <Button
            variant="outline"
            size="sm"
            onClick={() => openDischargeDialog()}
          >
            Discharge Patient
          </Button>
        </div>
      </div>
    </CardContent>
  </Card>
)}
```

#### **7.3 Add "Nursing Care" Tab for Admitted Patients**

```typescript
<Tabs value={activeTab} onValueChange={setActiveTab}>
  <TabsList>
    {/* ... existing tabs ... */}

    {consultation.patient_admission && (
      <TabsTrigger value="nursing_care">
        <Stethoscope className="h-4 w-4 mr-2" />
        Nursing Care
      </TabsTrigger>
    )}
  </TabsList>

  {/* ... existing tab contents ... */}

  {consultation.patient_admission && (
    <TabsContent value="nursing_care">
      <div className="grid grid-cols-2 gap-6">
        {/* Medication Administration Status */}
        <Card>
          <CardHeader>
            <CardTitle>Medication Administration</CardTitle>
          </CardHeader>
          <CardContent>
            <MedicationAdministrationStatus
              administrations={consultation.patient_admission.medicationAdministrations}
            />
          </CardContent>
        </Card>

        {/* Nursing Notes */}
        <Card>
          <CardHeader>
            <CardTitle>Recent Nursing Notes</CardTitle>
          </CardHeader>
          <CardContent>
            <NursingNotesList
              notes={consultation.patient_admission.nursingNotes}
            />
          </CardContent>
        </Card>

        {/* Ward Rounds History */}
        <Card className="col-span-2">
          <CardHeader>
            <CardTitle>Ward Rounds History</CardTitle>
          </CardHeader>
          <CardContent>
            <WardRoundsHistory
              rounds={consultation.patient_admission.wardRounds}
            />
          </CardContent>
        </Card>
      </div>
    </TabsContent>
  )}
</Tabs>
```

---

## 🧪 Testing Strategy

### **Unit Tests**
- [ ] Test `MedicationScheduleService` frequency parsing
- [ ] Test `PatientAdmission::generateAdmissionNumber()`
- [ ] Test model relationships

### **Feature Tests**
- [ ] Test vital signs recording for admitted patients
- [ ] Test medication administration workflow
- [ ] Test ward round documentation
- [ ] Test nursing notes creation
- [ ] Test permissions (nurses can't do ward rounds, doctors can't administer meds)

### **Browser Tests (Pest v4)**
- [ ] Complete ward round workflow
- [ ] Nurse medication administration flow
- [ ] Vital signs recording from ward page

---

## 🚀 Deployment Checklist

- [ ] Run migrations in production
- [ ] Seed initial data if needed
- [ ] Test with real user roles (Doctor, Nurse)
- [ ] Verify dark mode support throughout
- [ ] Performance test with 50+ patients in ward
- [ ] Mobile responsiveness check

---

## 📝 Future Enhancements (Post-MVP)

- [ ] Fluid balance charting (intake/output)
- [ ] Care plan templates
- [ ] Barcode medication scanning
- [ ] Automated vital sign alerts
- [ ] Shift handover reports
- [ ] Discharge summary generation
- [ ] Family communication portal
- [ ] Integration with pharmacy for stock management

---

## 🎨 Dark Mode Requirements

All new components MUST support dark mode:
- Use `dark:` prefix for dark mode styles
- Test in both light and dark themes
- Use semantic colors from theme
- Card backgrounds: `bg-white dark:bg-gray-950`
- Text: `text-gray-900 dark:text-gray-100`
- Borders: `border-gray-200 dark:border-gray-800`

---

## 📚 Key Files Reference

### Models
- `app/Models/PatientAdmission.php`
- `app/Models/MedicationAdministration.php` (new)
- `app/Models/NursingNote.php` (new)
- `app/Models/WardRound.php` (new)
- `app/Models/VitalSign.php` (update)

### Controllers
- `app/Http/Controllers/Ward/WardController.php`
- `app/Http/Controllers/Ward/MedicationAdministrationController.php` (new)
- `app/Http/Controllers/Ward/NursingNoteController.php` (new)
- `app/Http/Controllers/Ward/WardRoundController.php` (new)
- `app/Http/Controllers/Vitals/VitalSignController.php` (new/update)

### Frontend Pages
- `resources/js/pages/Ward/Show.tsx` (major updates)
- `resources/js/pages/Ward/WardRound.tsx` (new)
- `resources/js/pages/Consultation/Show.tsx` (admission context updates)

### Frontend Components
- `resources/js/components/Ward/RecordVitalsModal.tsx` (new)
- `resources/js/components/Ward/MedicationAdministrationPanel.tsx` (new)
- `resources/js/components/Ward/NursingNoteModal.tsx` (new)
- `resources/js/components/Ward/PatientRowActions.tsx` (new)

---

**Implementation Priority:**
1. Phase 1 (Database) - MUST DO FIRST
2. Phase 2 (Ward Show) - Core functionality
3. Phase 3 (Vitals) - High value for nurses
4. Phase 4 (Medications) - Critical for patient safety
5. Phase 5 (Ward Rounds) - Doctor efficiency
6. Phase 6 (Nursing Notes) - Documentation
7. Phase 7 (Consultation Context) - Integration

---

Last Updated: 2025-10-14
