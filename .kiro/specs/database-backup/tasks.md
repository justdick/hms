# Implementation Plan

- [x] 1. Set up database schema and models






  - [x] 1.1 Create migration for backups table

    - Create migration with columns: id, filename, file_size, file_path, google_drive_file_id, status (enum), source (enum), is_protected, created_by, completed_at, error_message, timestamps
    - Add foreign key to users table for created_by
    - Add indexes on status, created_at, is_protected
    - _Requirements: 1.3, 3.1_

  - [x] 1.2 Create migration for backup_settings table

    - Create migration with columns: id, schedule_enabled, schedule_frequency, schedule_time, cron_expression, retention_daily, retention_weekly, retention_monthly, google_drive_enabled, google_drive_folder_id, google_credentials (encrypted), notification_emails (json), timestamps
    - _Requirements: 2.2, 5.1, 6.1, 7.1_

  - [x] 1.3 Create migration for backup_logs table

    - Create migration with columns: id, backup_id, user_id, action (enum), details (text), timestamps
    - Add foreign keys to backups and users tables
    - _Requirements: 8.4_
  - [x] 1.4 Create Backup model with relationships and scopes


    - Define fillable, casts, relationships (creator, logs)
    - Add scopes: completed(), unprotected(), local(), onGoogleDrive()
    - Add helper methods: isOnGoogleDrive(), isLocal()
    - _Requirements: 3.2_

  - [x] 1.5 Create BackupSettings model

    - Define fillable, casts
    - Add helper method to get singleton instance
    - _Requirements: 2.2, 5.1_
  - [x] 1.6 Create BackupLog model


    - Define fillable, casts, relationships
    - _Requirements: 8.4_
  - [x] 1.7 Create model factories for Backup, BackupSettings, BackupLog


    - Create factories with realistic test data
    - Add states for different backup statuses and sources
    - _Requirements: All_

- [x] 2. Implement permissions and authorization






  - [x] 2.1 Add backup permissions to the permission seeder

    - Add permissions: backups.view, backups.create, backups.delete, backups.restore, backups.manage-settings
    - _Requirements: 8.2_

  - [x] 2.2 Create BackupPolicy with authorization methods

    - Implement viewAny, view, create, delete, restore, manageSettings methods
    - Register policy in AuthServiceProvider
    - _Requirements: 8.1, 8.2, 8.3_

  - [x] 2.3 Write property test for unauthorized access denial


    - **Property 16: Unauthorized Access Denial**
    - **Validates: Requirements 8.1**

  - [x] 2.4 Write property test for permission granularity

    - **Property 17: Permission Granularity Enforcement**
    - **Validates: Requirements 8.2, 8.3**

- [x] 3. Implement core backup service





  - [x] 3.1 Create BackupService with database dump functionality


    - Implement createBackup() method using mysqldump
    - Handle file compression (gzip)
    - Store backup in storage/app/backups/
    - _Requirements: 1.1, 1.2_

  - [x] 3.2 Implement backup metadata recording

    - Record filename, file_size, file_path, source, created_by, status
    - Update status to completed on success
    - _Requirements: 1.3_

  - [x] 3.3 Implement backup deletion in BackupService

    - Delete local file and database record
    - Handle missing files gracefully
    - _Requirements: 3.4_

  - [x] 3.4 Implement backup download functionality

    - Return StreamedResponse for backup file
    - Handle missing files with appropriate error
    - _Requirements: 3.3_

  - [x] 3.5 Write property test for backup creation

    - **Property 1: Backup Creation Produces Valid File**
    - **Validates: Requirements 1.1, 1.2**

  - [x] 3.6 Write property test for backup metadata completeness

    - **Property 2: Backup Metadata Completeness**
    - **Validates: Requirements 1.3**

  - [x] 3.7 Write property test for download returns valid file

    - **Property 8: Download Returns Valid File**
    - **Validates: Requirements 3.3**
  - [x] 3.8 Write property test for deletion removes from all locations


    - **Property 9: Deletion Removes From All Locations**
    - **Validates: Requirements 3.4**

- [x] 4. Checkpoint - Ensure all tests pass









  - Ensure all tests pass, ask the user if questions arise.

- [x] 5. Implement Google Drive integration





  - [x] 5.1 Install Google Drive API package


    - Add google/apiclient package via composer
    - _Requirements: 6.1_

  - [x] 5.2 Create GoogleDriveService

    - Implement isConfigured(), testConnection(), upload(), download(), delete() methods
    - Handle OAuth authentication
    - _Requirements: 6.1, 6.2_


  - [x] 5.3 Integrate Google Drive upload into BackupService
    - Upload backup after successful local storage
    - Update backup record with google_drive_file_id
    - Handle upload failures gracefully (keep local backup)
    - _Requirements: 1.4, 6.3, 6.4_
  - [x] 5.4 Integrate Google Drive deletion into BackupService
    - Delete from Google Drive when deleting backup
    - Handle missing Google Drive files gracefully
    - _Requirements: 3.4_
  - [x] 5.5 Write property test for Google Drive upload on success
    - **Property 3: Google Drive Upload on Success**
    - **Validates: Requirements 1.4**
  - [x] 5.6 Write property test for Google Drive failure graceful degradation
    - **Property 15: Google Drive Failure Graceful Degradation**
    - **Validates: Requirements 6.3**

- [x] 6. Implement restore service




  - [x] 6.1 Create RestoreService with pre-restore backup

    - Implement createPreRestoreBackup() method
    - Mark pre-restore backups with special source identifier
    - _Requirements: 4.3_

  - [x] 6.2 Implement database restore functionality
    - Download from Google Drive if not local
    - Execute mysql import command
    - Handle restore failures
    - _Requirements: 4.4_
  - [x] 6.3 Implement restore failure recovery
    - Attempt to restore pre-restore backup on failure
    - Log all restore attempts
    - _Requirements: 4.6_
  - [x] 6.4 Write property test for pre-restore backup creation


    - **Property 10: Pre-Restore Backup Creation**
    - **Validates: Requirements 4.3**

  - [x] 6.5 Write property test for restore round-trip consistency
    - **Property 11: Restore Round-Trip Consistency**
    - **Validates: Requirements 4.4**
  - [x] 6.6 Write property test for restore failure state preservation

    - **Property 12: Restore Failure State Preservation**
    - **Validates: Requirements 4.6**

- [x] 7. Checkpoint - Ensure all tests pass





  - Ensure all tests pass, ask the user if questions arise.

- [x] 8. Implement retention service





  - [x] 8.1 Create RetentionService


    - Implement applyRetentionPolicy() method
    - Categorize backups by daily, weekly, monthly
    - _Requirements: 5.2_

  - [x] 8.2 Implement backup categorization logic
    - Identify which backups to keep based on retention settings
    - Exclude protected backups from deletion

    - _Requirements: 5.2, 5.4_
  - [x] 8.3 Implement retention cleanup execution
    - Delete excess backups from both local and Google Drive
    - Log all deletions
    - _Requirements: 5.2, 5.3_
  - [x] 8.4 Write property test for retention policy correctness


    - **Property 13: Retention Policy Correctness**
    - **Validates: Requirements 5.2**
  - [x] 8.5 Write property test for protected backup exclusion

    - **Property 14: Protected Backup Exclusion**
    - **Validates: Requirements 5.4**

- [x] 9. Implement notification service
  - [x] 9.1 Create BackupNotificationService
    - Implement notifyBackupFailure(), notifyRestoreFailure(), notifyScheduledBackupFailure() methods
    - _Requirements: 7.2, 7.3, 7.4_
  - [x] 9.2 Create BackupFailedNotification mailable
    - Include error details, backup info, timestamp
    - _Requirements: 7.2, 7.3_
  - [x] 9.3 Integrate notifications into BackupService and RestoreService
    - Send notifications on failures
    - _Requirements: 1.5, 7.2, 7.3_
  - [x] 9.4 Write property test for failure notification dispatch

    - **Property 4: Failure Notification Dispatch**
    - **Validates: Requirements 1.5, 7.2, 7.3, 7.4**

- [x] 10. Implement audit logging





  - [x] 10.1 Create AuditService for backup operations


    - Log create, delete, restore, settings change operations
    - Record user_id, action, backup_id, details, timestamp
    - _Requirements: 8.4_
  - [x] 10.2 Integrate audit logging into services


    - Add logging calls to BackupService, RestoreService
    - _Requirements: 8.4_
  - [x] 10.3 Write property test for audit log completeness


    - **Property 18: Audit Log Completeness**
    - **Validates: Requirements 8.4**

- [x] 11. Checkpoint - Ensure all tests pass





  - Ensure all tests pass, ask the user if questions arise.

- [x] 12. Implement scheduled backups





  - [x] 12.1 Create CreateBackupJob


    - Implement ShouldQueue with retry logic (3 attempts, 60s backoff)
    - Handle job failures with notification
    - _Requirements: 2.1, 2.3_
  - [x] 12.2 Register scheduled backup in Laravel scheduler


    - Read schedule from BackupSettings
    - Support daily, weekly, and custom cron expressions
    - _Requirements: 2.1, 2.2_
  - [x] 12.3 Create CleanupBackupsJob for scheduled retention


    - Run retention cleanup on schedule
    - _Requirements: 5.2_
  - [x] 12.4 Write property test for scheduled backup retry behavior


    - **Property 5: Scheduled Backup Retry Behavior**
    - **Validates: Requirements 2.3**

- [x] 13. Implement Artisan commands






  - [x] 13.1 Create backup:create command

    - Accept --source option (default: cli)
    - Display progress and result
    - _Requirements: 1.2_

  - [x] 13.2 Create backup:restore command

    - Accept backup ID argument
    - Require --force flag or interactive confirmation
    - _Requirements: 4.2_

  - [x] 13.3 Create backup:cleanup command

    - Run retention policy manually
    - Display deleted backups count
    - _Requirements: 5.2_
  - [x] 13.4 Create backup:list command


    - Display all backups with status and storage info
    - _Requirements: 3.1_

- [x] 14. Implement controllers and UI



  - [x] 14.1 Create BackupController with CRUD operations


    - Implement index, store, show, destroy, download, restore methods
    - Apply BackupPolicy authorization
    - _Requirements: 1.1, 3.1, 3.3, 3.4, 4.1_


  - [x] 14.2 Create BackupSettingsController

    - Implement edit, update methods
    - Add testGoogleDrive endpoint

    - _Requirements: 2.2, 5.1, 6.1, 6.2, 7.1_
  - [x] 14.3 Create Form Requests for validation

    - StoreBackupRequest, UpdateBackupSettingsRequest, RestoreBackupRequest
    - _Requirements: All_

  - [x] 14.4 Create Backup/Index.tsx page

    - Display backup list with actions
    - Add create backup button
    - Show storage location indicators
    - _Requirements: 3.1, 3.2_



  - [x] 14.5 Create Backup/Settings.tsx page
    - Schedule configuration form
    - Retention policy settings
    - Google Drive configuration with test connection button
    - Notification email settings
    - _Requirements: 2.2, 5.1, 6.1, 7.1_

  - [x] 14.6 Create restore confirmation modal
    - Display warning about database replacement
    - Require explicit confirmation
    - _Requirements: 4.1_

  - [x] 14.7 Write property test for backup list data completeness
    - **Property 6: Backup List Data Completeness**
    - **Validates: Requirements 3.1**

  - [x] 14.8 Write property test for storage location accuracy
    - **Property 7: Storage Location Accuracy**
    - **Validates: Requirements 3.2**

- [x] 15. Add routes and navigation
  - [x] 15.1 Add backup routes to web.php
    - Resource routes for backups
    - Settings routes
    - Download and restore routes

    - _Requirements: All_
  - [x] 15.2 Add backup menu item to admin navigation
    - Show only to users with backups.view permission
    - _Requirements: 8.1_

- [x] 16. Final Checkpoint - Ensure all tests pass
  - Ensure all tests pass, ask the user if questions arise.
