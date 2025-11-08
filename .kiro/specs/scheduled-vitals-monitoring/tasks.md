# Implementation Plan

- [x] 1. Create database schema and models





  - Create migration for `vitals_schedules` table with columns: patient_admission_id, interval_minutes, next_due_at, last_recorded_at, is_active, created_by
  - Create migration for `vitals_alerts` table with columns: vitals_schedule_id, patient_admission_id, due_at, status, acknowledged_at, acknowledged_by
  - Add indexes for performance: idx_next_due_at, idx_admission_active, idx_status_due, idx_admission_status
  - _Requirements: 1.1, 1.4_

- [x] 1.1 Create VitalsSchedule model


  - Implement model with fillable fields and casts
  - Add relationships: patientAdmission(), createdBy(), alerts(), activeAlert()
  - Implement calculateNextDueTime() method to add interval to base time
  - Implement getCurrentStatus() method returning 'upcoming', 'due', or 'overdue' based on next_due_at and 15-minute grace period
  - Implement getTimeUntilDue() and getTimeOverdue() helper methods
  - Implement markAsCompleted() method to update schedule after vitals recorded
  - _Requirements: 1.1, 1.4, 3.1, 3.4, 6.1, 6.2_


- [x] 1.2 Create VitalsAlert model

  - Implement model with fillable fields and casts
  - Add relationships: vitalsSchedule(), patientAdmission(), acknowledgedBy()
  - Implement markAsDue(), markAsOverdue(), markAsCompleted() status transition methods
  - Implement acknowledge() method to record user acknowledgment
  - _Requirements: 2.1, 3.3, 4.1_

- [x] 1.3 Create model factories and seeders


  - Create VitalsScheduleFactory with realistic test data
  - Create VitalsAlertFactory with various status states
  - Create seeder to populate test schedules for existing admissions
  - _Requirements: 1.1, 2.1_

- [x] 2. Implement backend services






  - Create VitalsScheduleService class in app/Services directory
  - Create VitalsAlertService class in app/Services directory
  - _Requirements: 1.1, 2.1, 3.1, 4.1_


- [x] 2.1 Implement VitalsScheduleService

  - Implement createSchedule() method to create new schedule with validation
  - Implement updateSchedule() method to modify interval
  - Implement disableSchedule() method to deactivate schedule
  - Implement calculateNextDueTime() method using interval_minutes
  - Implement recordVitalsCompleted() method to update schedule and create next due time
  - Implement getScheduleStatus() method returning status array with time calculations
  - _Requirements: 1.1, 1.5, 6.1, 6.2, 6.3, 6.4_


- [x] 2.2 Implement VitalsAlertService

  - Implement checkDueAlerts() method to find schedules where next_due_at is within current time
  - Implement checkOverdueAlerts() method to find alerts past 15-minute grace period
  - Implement createAlert() method to generate new alert for schedule
  - Implement updateAlertStatus() method to transition alert states
  - Implement getActiveAlertsForWard() method with eager loading
  - Implement getActiveAlertsForUser() method for personalized alerts
  - Implement acknowledgeAlert() and dismissAlert() methods
  - _Requirements: 2.1, 3.1, 3.3, 4.1, 4.3, 5.1_

- [x] 3. Create scheduled command for monitoring





  - Create CheckDueVitalsCommand using php artisan make:command
  - Implement handle() method to check for due and overdue vitals
  - Query active schedules where next_due_at <= now
  - Create or update alerts with appropriate status (due or overdue based on 15-minute grace period)
  - Schedule command to run every minute in routes/console.php
  - _Requirements: 2.1, 3.1, 3.3, 4.1_

- [x] 4. Create API routes and controllers





  - Create VitalsScheduleController in app/Http/Controllers/Ward directory
  - Create VitalsAlertController in app/Http/Controllers/Ward directory
  - Add routes in routes/web.php for schedule management
  - Add API routes in routes/api.php for alert polling
  - _Requirements: 1.1, 2.1, 5.5_


- [x] 4.1 Implement VitalsScheduleController

  - Implement store() method to create schedule with validation (interval 15-1440 minutes, active admission)
  - Implement update() method to modify schedule interval
  - Implement destroy() method to disable schedule
  - Implement alerts() method to fetch ward alerts
  - Create Form Request classes for validation with custom error messages
  - Add authorization checks for ward access
  - _Requirements: 1.1, 1.2, 1.3, 1.5_


- [x] 4.2 Implement VitalsAlertController
  - Implement active() method to return active alerts for current user's wards
  - Implement acknowledge() method to mark alert as acknowledged
  - Implement dismiss() method to dismiss alert
  - Return JSON responses with patient, bed, and timing information
  - Add authorization checks
  - _Requirements: 2.1, 2.3, 2.4, 4.4, 5.1_

- [x] 5. Update existing models with schedule relationships





  - Add vitalsSchedule() relationship to PatientAdmission model
  - Add activeVitalsSchedule() relationship to PatientAdmission model
  - Update PatientAdmission discharge logic to disable vitals schedules
  - Update VitalSign model observer to trigger schedule completion
  - _Requirements: 6.1, 6.3, 9.1_

- [x] 6. Create frontend hooks and utilities





  - Create useVitalsAlerts hook in resources/js/hooks directory
  - Create useSoundAlert hook in resources/js/hooks directory
  - Create useVitalsSchedule hook for schedule management
  - Create audio utility functions in resources/js/lib/audio.ts
  - _Requirements: 2.1, 2.5, 4.2, 7.1, 7.2_

- [x] 6.1 Implement useVitalsAlerts hook


  - Set up polling mechanism to fetch alerts every 30 seconds
  - Implement state management for alerts array
  - Implement acknowledgeAlert() function calling API
  - Implement dismissAlert() function calling API
  - Add loading and error states
  - Filter alerts by ward if wardId provided
  - _Requirements: 2.1, 4.3, 5.1_

- [x] 6.2 Implement useSoundAlert hook


  - Load sound preferences from localStorage
  - Implement playAlert() function with 'gentle' and 'urgent' sound types
  - Implement updateSettings() function to persist preferences
  - Handle audio playback errors gracefully
  - Respect browser autoplay policies
  - _Requirements: 2.2, 4.2, 7.1, 7.2, 7.3, 7.5_

- [x] 6.3 Create audio utility and sound files


  - Create audio.ts utility with preload and play functions
  - Add gentle alert sound file (soft beep) to public/sounds directory
  - Add urgent alert sound file (prominent tone) to public/sounds directory
  - Implement volume control in audio utility
  - _Requirements: 2.2, 4.2, 7.2_

- [x] 7. Build VitalsScheduleModal component





  - Create VitalsScheduleModal.tsx in resources/js/components/Ward directory
  - Implement form with interval selection dropdown (1h, 2h, 4h, 6h, 8h, 12h)
  - Add custom interval input field for minutes
  - Show preview of next 3 due times based on selected interval
  - Implement save handler calling API endpoint
  - Add loading and error states
  - Style with Tailwind CSS and shadcn/ui components
  - _Requirements: 1.1, 1.2, 1.3, 1.5_

- [x] 8. Build VitalsStatusBadge component





  - Create VitalsStatusBadge.tsx in resources/js/components/Ward directory
  - Display status with color coding: green (upcoming), yellow (due), red (overdue)
  - Show time until due or time overdue
  - Make badge clickable to navigate to vitals recording
  - Add tooltip with detailed schedule information
  - Style with Tailwind CSS badges
  - _Requirements: 5.2, 5.3, 6.2, 6.3, 6.4_

- [x] 9. Build VitalsAlertToast component





  - Create VitalsAlertToast.tsx in resources/js/components/Ward directory
  - Display patient name, bed number, and due time
  - Implement different styling for due vs overdue alerts
  - Add "Record Vitals" button navigating to vitals form
  - Add "Dismiss" button calling dismiss API
  - Set auto-dismiss timing: 10 seconds for due, 15 seconds for overdue
  - Integrate with useSoundAlert hook to play sound on display
  - _Requirements: 2.1, 2.2, 2.3, 2.4, 2.5, 4.1, 4.2, 4.4, 4.5_

- [x] 10. Build VitalsAlertDashboard component




  - Create VitalsAlertDashboard.tsx in resources/js/components/Ward directory
  - Display table of all active schedules with patient info
  - Show status, next due time, and time calculations
  - Implement sorting by urgency (overdue first, then due, then upcoming)
  - Add ward filter dropdown
  - Add quick action buttons for each patient
  - Style with Tailwind CSS and shadcn/ui table components
  - _Requirements: 5.1, 5.2, 5.3, 5.4, 5.5_

- [x] 11. Update Ward Show page





  - Modify resources/js/Pages/Ward/Show.tsx to integrate vitals alerts
  - Add useVitalsAlerts hook to fetch ward alerts
  - Display VitalsStatusBadge for each patient in CurrentPatientsTable
  - Add stats card showing count of patients with due/overdue vitals
  - Integrate VitalsAlertToast to show new alerts
  - Update patient list to highlight patients with overdue vitals
  - _Requirements: 5.1, 5.2, 6.1, 6.2_

- [x] 12. Update Patient Show page





  - Modify resources/js/Pages/Ward/PatientShow.tsx to show vitals schedule
  - Display current schedule with interval and next due time
  - Add VitalsStatusBadge showing current status
  - Add button to open VitalsScheduleModal for creating/editing schedule
  - Add quick "Record Vitals Now" button
  - Show schedule history section
  - _Requirements: 6.1, 6.3, 6.4, 6.5_

- [x] 13. Implement alert polling and toast system





  - Set up polling in Ward Show and Patient Show pages using useVitalsAlerts
  - Implement toast notification system using shadcn/ui toast
  - Show VitalsAlertToast when new due/overdue alerts detected
  - Trigger sound alerts using useSoundAlert hook
  - Implement repeat notifications for overdue alerts (every 15 minutes)
  - Handle multiple simultaneous alerts gracefully
  - _Requirements: 2.1, 2.2, 2.5, 4.1, 4.3_

- [x] 14. Add sound alert settings page




  - Create settings section for vitals alert preferences
  - Add toggle to enable/disable sound alerts
  - Add volume slider (0-100%)
  - Add sound type selector (gentle/urgent)
  - Add test button to preview sounds
  - Persist settings to localStorage
  - _Requirements: 7.1, 7.2, 7.3, 7.4_

- [x] 15. Update WardController to include schedule data






  - Modify Ward Show controller to eager load vitals schedules
  - Include schedule status in patient data
  - Add schedule statistics to ward stats
  - Optimize queries with proper eager loading
  - _Requirements: 5.1, 5.2, 6.1_

- [x] 16. Update WardPatientController to include schedule data




  - Modify Patient Show controller to load vitals schedule
  - Include next due time and status in response
  - Add schedule history to patient data
  - _Requirements: 6.1, 6.3, 6.4_

- [x] 17. Write backend tests




  - _Requirements: All_

- [x] 17.1 Write unit tests for VitalsSchedule model


  - Test calculateNextDueTime() with various intervals (1h, 4h, 12h)
  - Test getCurrentStatus() returns correct status based on time
  - Test getTimeUntilDue() calculations
  - Test getTimeOverdue() calculations with 15-minute grace period
  - Test markAsCompleted() updates schedule correctly
  - _Requirements: 3.1, 3.2, 3.3, 6.1, 6.2_

- [x] 17.2 Write unit tests for VitalsAlert model


  - Test status transition methods (markAsDue, markAsOverdue, markAsCompleted)
  - Test acknowledge() records user and timestamp
  - Test relationships load correctly
  - _Requirements: 2.1, 3.3, 4.1_

- [x] 17.3 Write unit tests for VitalsScheduleService


  - Test createSchedule() with valid and invalid data
  - Test updateSchedule() modifies interval correctly
  - Test disableSchedule() sets is_active to false
  - Test recordVitalsCompleted() calculates next due time
  - Test getScheduleStatus() returns correct status array
  - _Requirements: 1.1, 1.5, 6.1, 6.2, 6.3_

- [x] 17.4 Write unit tests for VitalsAlertService


  - Test checkDueAlerts() finds schedules at due time
  - Test checkOverdueAlerts() finds alerts past grace period
  - Test createAlert() generates alert with correct status
  - Test updateAlertStatus() transitions states correctly
  - Test getActiveAlertsForWard() filters by ward
  - _Requirements: 2.1, 3.1, 3.3, 4.1, 5.1_

- [x] 17.5 Write feature tests for schedule management


  - Test POST /wards/{ward}/patients/{admission}/vitals-schedule creates schedule
  - Test PUT endpoint updates schedule interval
  - Test DELETE endpoint disables schedule
  - Test validation rules (interval 15-1440 minutes)
  - Test authorization (only nurses/doctors can manage)
  - _Requirements: 1.1, 1.2, 1.3, 1.5_

- [x] 17.6 Write feature tests for alert API


  - Test GET /api/vitals-alerts/active returns user's ward alerts
  - Test POST /api/vitals-alerts/{alert}/acknowledge updates alert
  - Test POST /api/vitals-alerts/{alert}/dismiss dismisses alert
  - Test authorization for all endpoints
  - _Requirements: 2.1, 5.1_

- [x] 17.7 Write feature tests for scheduled command


  - Test command creates alerts at due time
  - Test command updates alert status to overdue after 15 minutes
  - Test command handles multiple schedules correctly
  - Test command skips inactive schedules
  - _Requirements: 2.1, 3.1, 3.3, 4.1_

- [x] 17.8 Write feature tests for discharge flow


  - Test patient discharge disables vitals schedule
  - Test discharge dismisses pending alerts
  - Test no new alerts created after discharge
  - _Requirements: 9.1, 9.2_

- [x] 18. Write browser tests




  - _Requirements: All_

- [x] 18.1 Write browser test for toast notifications


  - Test toast appears when alert becomes due
  - Test toast styling differs for due vs overdue
  - Test "Record Vitals" button navigates correctly
  - Test "Dismiss" button removes toast
  - Test toast auto-dismiss timing (10s for due, 15s for overdue)
  - _Requirements: 2.1, 2.3, 2.4, 2.5, 4.4, 4.5_

- [x] 18.2 Write browser test for sound alerts


  - Test audio plays when due alert appears
  - Test different sounds for due vs overdue
  - Test volume control works
  - Test mute functionality
  - Test sound preferences persist
  - _Requirements: 2.2, 4.2, 7.1, 7.2, 7.3_

- [x] 18.3 Write browser test for ward page integration


  - Test vitals status badges display on ward page
  - Test badge colors match status (green/yellow/red)
  - Test clicking badge navigates to patient page
  - Test ward stats show correct counts
  - _Requirements: 5.2, 5.3, 6.1, 6.2_

- [x] 18.4 Write browser test for patient page integration


  - Test schedule display shows interval and next due time
  - Test VitalsScheduleModal opens and saves schedule
  - Test schedule editing updates interval
  - Test "Record Vitals Now" button works
  - _Requirements: 6.1, 6.3, 6.4, 6.5_

- [x] 18.5 Write browser test for alert dashboard


  - Test dashboard displays all active schedules
  - Test sorting by urgency (overdue first)
  - Test ward filter works
  - Test quick actions navigate correctly
  - _Requirements: 5.1, 5.2, 5.3, 5.4, 5.5_

- [x] 18.6 Write browser test for end-to-end flow


  - Test create schedule → wait for due time → receive alert → record vitals → next alert scheduled
  - Test overdue alert appears after 15-minute grace period
  - Test discharge stops alerts
  - _Requirements: 1.1, 2.1, 3.1, 6.1, 9.1_
