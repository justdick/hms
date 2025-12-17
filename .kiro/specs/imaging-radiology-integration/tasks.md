# Implementation Plan

## Phase 1: Database & Backend Foundation

- [x] 1. Database migrations and model updates






  - [x] 1.1 Create migration to add `is_imaging` and `modality` columns to lab_services table

    - Add `is_imaging` boolean column with default false
    - Add `modality` varchar(50) nullable column
    - Update existing imaging services to set `is_imaging = true`
    - _Requirements: 1.1, 1.2_

  - [x] 1.2 Create migration for imaging_attachments table

    - Create table with all required columns (file_path, file_name, file_type, file_size, description, is_external, external_facility_name, external_study_date, uploaded_by, uploaded_at)
    - Add foreign keys to lab_orders and users tables
    - Add indexes for lab_order_id and is_external
    - _Requirements: 1.3, 1.4, 1.5_

  - [x] 1.3 Update LabService model

    - Add `is_imaging` and `modality` to fillable
    - Add casts for `is_imaging` as boolean
    - Add `scopeImaging()` and `scopeLaboratory()` scopes
    - _Requirements: 1.1, 1.2_

  - [x] 1.4 Write property test for LabService imaging flag

    - **Property 1: Imaging flag persistence**
    - **Validates: Requirements 1.1**

  - [x] 1.5 Create ImagingAttachment model with factory

    - Define fillable, casts, and relationships (labOrder, uploadedBy)
    - Add URL and thumbnail URL accessors
    - Create factory for testing
    - _Requirements: 1.3, 1.4, 1.5_

  - [x] 1.6 Write property test for ImagingAttachment relationships

    - **Property 2: Imaging attachments relationship**
    - **Property 3: Attachment metadata completeness**
    - **Validates: Requirements 1.3, 1.4**

  - [x] 1.7 Update LabOrder model

    - Add `imagingAttachments()` HasMany relationship
    - Add `isImaging()` helper method
    - Add `scopeImaging()` and `scopeLaboratory()` scopes
    - Add `hasImages()` accessor
    - _Requirements: 1.3, 3.5_

- [x] 2. Checkpoint - Ensure all tests pass





  - Ensure all tests pass, ask the user if questions arise.

## Phase 2: File Storage Service

- [ ] 3. Imaging storage service
  - [ ] 3.1 Create ImagingStorageService
    - Implement `store()` method for file upload
    - Implement `getStoragePath()` for path generation (year/month/patient/order)
    - Implement `delete()` method for file removal
    - Configure local disk storage
    - _Requirements: 6.5_
  - [ ] 3.2 Write property test for file storage path structure
    - **Property 15: File storage path structure**
    - **Validates: Requirements 6.5**
  - [ ] 3.3 Create file validation rules
    - Validate file types (JPEG, PNG, PDF only)
    - Validate file size (max 50MB)
    - _Requirements: 4.3, 6.1_
  - [ ] 3.4 Write property test for file type validation
    - **Property 8: File type validation**
    - **Validates: Requirements 4.3**

- [ ] 4. Checkpoint - Ensure all tests pass
  - Ensure all tests pass, ask the user if questions arise.

## Phase 3: Permissions & Authorization

- [ ] 5. Permissions and policies
  - [ ] 5.1 Add new permissions to seeder
    - Add `investigations.order`, `investigations.upload-external`
    - Add `radiology.view-worklist`, `radiology.upload`, `radiology.report`
    - _Requirements: 9.1, 9.2, 9.3, 9.4, 9.5_
  - [ ] 5.2 Create RadiologyPolicy
    - Implement `viewWorklist()`, `uploadImages()`, `enterReport()` methods
    - Register policy in AuthServiceProvider
    - _Requirements: 9.2, 9.3, 9.5_
  - [ ] 5.3 Update LabOrderPolicy
    - Add `uploadExternalImages()` method
    - _Requirements: 9.4_
  - [ ] 5.4 Write property tests for authorization
    - **Property 19: Authorization - order imaging**
    - **Property 20: Authorization - upload images**
    - **Property 21: Authorization - enter report**
    - **Property 22: Authorization - external upload**
    - **Property 23: Authorization - view worklist**
    - **Validates: Requirements 9.1, 9.2, 9.3, 9.4, 9.5**

- [ ] 6. Checkpoint - Ensure all tests pass
  - Ensure all tests pass, ask the user if questions arise.

## Phase 4: Radiology Backend

- [ ] 7. Radiology controllers
  - [ ] 7.1 Create RadiologyController
    - Implement `index()` for worklist with filtering and sorting
    - Implement `show()` for order details
    - Implement `markInProgress()` for status update
    - Implement `complete()` for completing with report
    - Apply authorization via policy
    - _Requirements: 5.1, 5.2, 5.3, 5.5, 6.3, 6.4_
  - [ ] 7.2 Write property test for worklist sorting
    - **Property 10: Worklist sorting**
    - **Validates: Requirements 5.1**
  - [ ] 7.3 Write property test for worklist filtering
    - **Property 11: Worklist filtering**
    - **Validates: Requirements 5.3**
  - [ ] 7.4 Create ImagingAttachmentController
    - Implement `store()` for uploading images
    - Implement `destroy()` for deleting attachments
    - Implement `download()` for file download
    - Use ImagingStorageService for file operations
    - _Requirements: 6.1, 6.2_
  - [ ] 7.5 Write property test for completion requirements
    - **Property 13: Completion requires report**
    - **Property 14: Completion metadata**
    - **Validates: Requirements 6.3, 6.4**
  - [ ] 7.6 Create Form Requests
    - Create StoreImagingAttachmentRequest with file validation
    - Create CompleteImagingOrderRequest with report validation
    - _Requirements: 4.3, 6.1, 6.3_
  - [ ] 7.7 Add routes for radiology
    - Add routes for worklist, show, upload, complete
    - Apply middleware for authentication
    - _Requirements: 5.1, 5.5, 6.1_

- [ ] 8. Checkpoint - Ensure all tests pass
  - Ensure all tests pass, ask the user if questions arise.

## Phase 5: Consultation Backend Enhancement

- [ ] 9. Consultation imaging support
  - [ ] 9.1 Update ConsultationLabOrderController
    - Modify lab services query to separate imaging and laboratory
    - Add endpoint for external image upload
    - _Requirements: 3.1, 4.1_
  - [ ] 9.2 Create ExternalImageUploadRequest
    - Validate imaging study type, facility name, study date
    - Validate file types and sizes
    - _Requirements: 4.2, 4.3_
  - [ ] 9.3 Write property test for external image validation
    - **Property 4: External image metadata**
    - **Validates: Requirements 1.5, 4.2**
  - [ ] 9.4 Ensure external images are non-billable
    - Skip charge creation for external image uploads
    - Mark attachments as is_external = true
    - _Requirements: 4.5_
  - [ ] 9.5 Write property test for external image billing
    - **Property 9: External image non-billable**
    - **Validates: Requirements 4.5**
  - [ ] 9.6 Update patient history query
    - Separate imaging studies from laboratory tests
    - Include both internal and external imaging
    - _Requirements: 8.1, 8.4_
  - [ ] 9.7 Write property test for history separation
    - **Property 17: History separation**
    - **Property 18: History completeness**
    - **Validates: Requirements 8.1, 8.4**

- [ ] 10. Checkpoint - Ensure all tests pass
  - Ensure all tests pass, ask the user if questions arise.

## Phase 6: Sidebar Navigation Update

- [ ] 11. Update sidebar navigation
  - [ ] 11.1 Update app-sidebar.tsx
    - Replace "Laboratory" with "Investigations" parent menu
    - Add "Laboratory" and "Radiology" as sub-items
    - Add permission-based visibility for sub-items
    - _Requirements: 2.1, 2.2, 2.3, 2.4, 2.5_
  - [ ] 11.2 Update SharedData types
    - Add radiology permissions to auth.permissions type
    - _Requirements: 2.4, 2.5_
  - [ ] 11.3 Update HandleInertiaRequests middleware
    - Include radiology permissions in shared data
    - _Requirements: 2.4, 2.5_
  - [ ] 11.4 Write property test for menu visibility
    - **Property 5: Permission-based menu visibility**
    - **Validates: Requirements 2.4, 2.5**

- [ ] 12. Checkpoint - Ensure all tests pass
  - Ensure all tests pass, ask the user if questions arise.

## Phase 7: Radiology Frontend

- [ ] 13. Radiology worklist page
  - [ ] 13.1 Create Radiology/Index.tsx
    - Display pending imaging orders in data table
    - Implement filtering by date, modality, status, priority
    - Highlight STAT priority orders
    - Add quick actions (view, start, complete)
    - _Requirements: 5.1, 5.2, 5.3, 5.4_
  - [ ] 13.2 Create Radiology/Show.tsx
    - Display order details and patient info
    - Image upload interface with drag-and-drop
    - Report entry form with findings and impression
    - Complete button with validation
    - _Requirements: 6.1, 6.2, 6.3_
  - [ ] 13.3 Create ImageUploadZone component
    - Drag-and-drop file upload
    - File type and size validation
    - Progress indicator
    - Description input for each file
    - _Requirements: 6.1, 6.2_

- [ ] 14. Checkpoint - Ensure all tests pass
  - Ensure all tests pass, ask the user if questions arise.

## Phase 8: Consultation Frontend Enhancement

- [ ] 15. Consultation investigations section
  - [ ] 15.1 Create InvestigationsSection component
    - Tabbed interface with "Laboratory Tests" and "Imaging" tabs
    - Display orders with visual distinction (icons)
    - Show image indicator for orders with attachments
    - _Requirements: 3.1, 3.4, 3.5_
  - [ ] 15.2 Write property test for image indicator
    - **Property 7: Image attachment indicator**
    - **Validates: Requirements 3.5**
  - [ ] 15.3 Create ImagingOrderDialog component
    - Imaging study type selection with modality filter
    - Priority selection
    - Clinical indication text area
    - _Requirements: 3.2_
  - [ ] 15.4 Update Consultation/Show.tsx
    - Replace lab orders section with InvestigationsSection
    - Pass imaging services separately from lab services
    - _Requirements: 3.1_
  - [ ] 15.5 Write property test for imaging order billing
    - **Property 6: Imaging order billing**
    - **Validates: Requirements 3.3**

- [ ] 16. Checkpoint - Ensure all tests pass
  - Ensure all tests pass, ask the user if questions arise.

## Phase 9: External Image Upload & Results Viewing

- [ ] 17. External image upload
  - [ ] 17.1 Create ExternalImageUploadDialog component
    - Imaging study type selection
    - External facility name input (required)
    - Study date picker (required)
    - Multi-file upload with descriptions
    - _Requirements: 4.1, 4.2, 4.4_
  - [ ] 17.2 Add external upload button to InvestigationsSection
    - Button to open ExternalImageUploadDialog
    - Permission check for upload-external
    - _Requirements: 4.1_

- [ ] 18. Image viewing components
  - [ ] 18.1 Create ImageGallery component
    - Thumbnail grid display
    - Lightbox view on click
    - Navigation between images
    - Download button
    - _Requirements: 7.1, 7.2_
  - [ ] 18.2 Create ImagingResultsModal component
    - Display images using ImageGallery
    - Show radiologist report (findings, impression)
    - Display metadata (ordered date, performed date, performed by)
    - External indicator with facility name and date
    - _Requirements: 7.1, 7.3, 7.4, 7.5_
  - [ ] 18.3 Write property test for external indicator
    - **Property 16: External indicator display**
    - **Validates: Requirements 7.5**
  - [ ] 18.4 Update ConsultationLabOrdersTable
    - Use ImagingResultsModal for imaging orders
    - Keep existing LabResultsModal for lab tests
    - _Requirements: 7.1, 7.3_

- [ ] 19. Checkpoint - Ensure all tests pass
  - Ensure all tests pass, ask the user if questions arise.

## Phase 10: Patient History Integration

- [ ] 20. Patient history updates
  - [ ] 20.1 Update PatientHistorySidebar component
    - Add separate section for imaging history
    - Display study type, date, and image availability indicator
    - _Requirements: 8.1, 8.2_
  - [ ] 20.2 Add imaging history click handler
    - Open ImagingResultsModal when clicking on historical imaging study
    - _Requirements: 8.3_
  - [ ] 20.3 Update consultation controller
    - Include imaging history in patient history data
    - Include both internal and external imaging
    - _Requirements: 8.1, 8.4_

- [ ] 21. Final Checkpoint - Ensure all tests pass
  - Ensure all tests pass, ask the user if questions arise.

## Phase 11: Mittag Data Migration

- [ ] 22. Migrate imaging data from Mittag
  - [ ] 22.1 Create MigrateImagingFromMittag command
    - Read from `img_daily_register` table in mittag_old database
    - Map imaging codes to lab_services with `is_imaging = true`
    - Create lab_orders for each imaging order
    - Link to patient via folder_id mapping
    - Link to consultation via checkin mapping (same as lab orders)
    - Mark as `migrated_from_mittag = true`
    - _Requirements: Data migration_
  - [ ] 22.2 Migrate imaging results and files
    - Read from `img_results` table
    - Copy files from old `uploads/radiology/` directory to new storage structure
    - Create `imaging_attachments` records for each file
    - Use ImagingStorageService for proper path generation
    - _Requirements: Data migration_
  - [ ] 22.3 Migrate imaging comments/reports
    - Read from `img_comments` table
    - Store HTML content in `lab_orders.result_notes`
    - Strip HTML tags or convert to plain text if needed
    - _Requirements: Data migration_
  - [ ] 22.4 Update MigrateAllFromMittag command
    - Add call to `migrate:imaging-from-mittag` in the sequence
    - Ensure it runs after lab orders migration
    - _Requirements: Data migration_
  - [ ] 22.5 Test migration with dry-run
    - Run migration with `--dry-run` flag
    - Verify all 2 imaging orders would be migrated
    - Verify file paths are correctly mapped
    - _Requirements: Data migration_

- [ ] 23. Final Migration Checkpoint
  - Ensure all tests pass, ask the user if questions arise.
  - Run full migration and verify data integrity.
