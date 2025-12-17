# Requirements Document

## Introduction

This feature integrates imaging/radiology services (X-ray, CT scan, MRI, Ultrasound, etc.) into the Hospital Management System. Currently, imaging services exist in the lab_services table under the "Imaging" category but lack support for image file uploads and a dedicated radiology workflow. This integration will:

1. Enhance the existing lab orders system to support image attachments
2. Rename "Laboratory" sidebar menu to "Investigations" with sub-menus for Laboratory and Radiology
3. Allow doctors to order imaging studies and upload external patient images during consultation
4. Provide a dedicated Radiology worklist for radiology staff to upload images and enter reports
5. Display imaging results with image viewing capability in consultation interface

## Glossary

- **HMS**: Hospital Management System
- **Imaging Study**: A diagnostic procedure that produces visual images (X-ray, CT, MRI, Ultrasound, etc.)
- **Modality**: The type of imaging equipment/technique used (X-Ray, CT, MRI, Ultrasound, Mammography)
- **External Image**: An imaging study performed at another facility that the patient brings to the hospital
- **Radiology Worklist**: A queue of pending imaging orders for radiology staff to process
- **Investigation**: A collective term for both laboratory tests and imaging studies
- **Lab Order**: An order for either a laboratory test or imaging study (existing model)
- **Imaging Attachment**: A file (JPEG, PNG, PDF) attached to an imaging order

## Requirements

### Requirement 1: Database Enhancement for Imaging Support

**User Story:** As a system administrator, I want the database to distinguish between laboratory tests and imaging studies, so that the system can handle image file storage and imaging-specific workflows.

#### Acceptance Criteria

1. WHEN a lab service is created or updated THEN the HMS SHALL store an `is_imaging` boolean flag to identify imaging services
2. WHEN a lab service is an imaging type THEN the HMS SHALL store the `modality` (X-Ray, CT, MRI, Ultrasound, etc.)
3. WHEN an imaging order is completed THEN the HMS SHALL support storing multiple image attachments per order
4. WHEN an image attachment is uploaded THEN the HMS SHALL store file path, file name, file type, file size, description, and upload metadata
5. WHEN an external image is uploaded THEN the HMS SHALL store the external facility name and study date

### Requirement 2: Sidebar Navigation Restructuring

**User Story:** As a hospital staff member, I want the sidebar navigation to group laboratory and radiology under "Investigations", so that I can easily access both departments from a unified menu.

#### Acceptance Criteria

1. WHEN a user views the sidebar THEN the HMS SHALL display "Investigations" as a parent menu item with Laboratory and Radiology as sub-items
2. WHEN a user clicks on "Laboratory" sub-item THEN the HMS SHALL navigate to the existing lab worklist page
3. WHEN a user clicks on "Radiology" sub-item THEN the HMS SHALL navigate to the new radiology worklist page
4. WHEN a user has laboratory permissions THEN the HMS SHALL display the Laboratory sub-item
5. WHEN a user has radiology permissions THEN the HMS SHALL display the Radiology sub-item

### Requirement 3: Consultation Interface - Ordering Investigations

**User Story:** As a doctor, I want to order both laboratory tests and imaging studies from the consultation interface, so that I can request all necessary investigations for my patient.

#### Acceptance Criteria

1. WHEN a doctor views the investigations section in consultation THEN the HMS SHALL display separate tabs for "Laboratory Tests" and "Imaging"
2. WHEN a doctor orders an imaging study THEN the HMS SHALL allow selection of imaging type, priority, and clinical indication
3. WHEN a doctor orders an imaging study THEN the HMS SHALL create a lab order with the imaging service and generate appropriate billing charges
4. WHEN displaying ordered investigations THEN the HMS SHALL visually distinguish between laboratory tests and imaging studies using icons
5. WHEN an imaging order has attached images THEN the HMS SHALL display an indicator showing images are available

### Requirement 4: External Image Upload in Consultation

**User Story:** As a doctor, I want to upload imaging results that patients bring from external facilities, so that I can document and reference these images in the patient's record.

#### Acceptance Criteria

1. WHEN a doctor uploads an external image THEN the HMS SHALL allow selection of the imaging study type
2. WHEN a doctor uploads an external image THEN the HMS SHALL require the external facility name and study date
3. WHEN a doctor uploads an external image THEN the HMS SHALL accept JPEG, PNG, and PDF file formats up to 50MB per file
4. WHEN a doctor uploads an external image THEN the HMS SHALL allow multiple files per upload with descriptions for each
5. WHEN an external image is uploaded THEN the HMS SHALL mark it as external (non-billable) and store it with the patient's imaging history

### Requirement 5: Radiology Worklist

**User Story:** As a radiology technician, I want to view a worklist of pending imaging orders, so that I can process imaging studies in priority order.

#### Acceptance Criteria

1. WHEN a radiology staff views the worklist THEN the HMS SHALL display all pending imaging orders sorted by priority and order time
2. WHEN displaying the worklist THEN the HMS SHALL show patient name, study type, priority level, ordering doctor, and time ordered
3. WHEN a radiology staff filters the worklist THEN the HMS SHALL allow filtering by date, modality, status, and priority
4. WHEN an imaging order has STAT priority THEN the HMS SHALL highlight it prominently in the worklist
5. WHEN a radiology staff selects an order THEN the HMS SHALL allow them to mark it as in-progress, upload images, and complete with a report

### Requirement 6: Image Upload and Report Entry (Radiology Staff)

**User Story:** As a radiology technician or radiologist, I want to upload images and enter reports for completed imaging studies, so that doctors can view the results.

#### Acceptance Criteria

1. WHEN completing an imaging study THEN the HMS SHALL allow upload of one or more image files (JPEG, PNG, PDF)
2. WHEN uploading images THEN the HMS SHALL allow adding a description for each image (e.g., "PA View", "Lateral View")
3. WHEN completing an imaging study THEN the HMS SHALL require a text report with findings and impression sections
4. WHEN a study is completed THEN the HMS SHALL record the completion timestamp and the user who completed it
5. WHEN images are uploaded THEN the HMS SHALL store them in local storage organized by year/month/patient/order

### Requirement 7: Imaging Results Viewing

**User Story:** As a doctor, I want to view imaging results including images and reports, so that I can make informed clinical decisions.

#### Acceptance Criteria

1. WHEN a doctor views a completed imaging order THEN the HMS SHALL display all attached images as thumbnails
2. WHEN a doctor clicks on an image thumbnail THEN the HMS SHALL open a lightbox/gallery view for full-size viewing
3. WHEN viewing imaging results THEN the HMS SHALL display the radiologist's report with findings and impression
4. WHEN viewing imaging results THEN the HMS SHALL show study metadata (ordered date, performed date, performed by)
5. WHEN an imaging order is from an external facility THEN the HMS SHALL clearly indicate it as external with facility name and date

### Requirement 8: Patient History Integration

**User Story:** As a doctor, I want to see a patient's imaging history in the consultation sidebar, so that I can review previous imaging studies.

#### Acceptance Criteria

1. WHEN viewing patient history THEN the HMS SHALL display previous imaging studies separately from laboratory tests
2. WHEN displaying imaging history THEN the HMS SHALL show study type, date, and whether images are available
3. WHEN a doctor clicks on a historical imaging study THEN the HMS SHALL open the results view with images and report
4. WHEN displaying imaging history THEN the HMS SHALL include both internal and external imaging studies

### Requirement 9: Permissions and Access Control

**User Story:** As a system administrator, I want to control who can order imaging, upload images, and enter reports, so that only authorized staff can perform these actions.

#### Acceptance Criteria

1. WHEN a user attempts to order imaging studies THEN the HMS SHALL verify the user has the `investigations.order` permission
2. WHEN a user attempts to upload images in radiology THEN the HMS SHALL verify the user has the `radiology.upload` permission
3. WHEN a user attempts to enter radiology reports THEN the HMS SHALL verify the user has the `radiology.report` permission
4. WHEN a user attempts to upload external images THEN the HMS SHALL verify the user has the `investigations.upload-external` permission
5. WHEN a user attempts to view the radiology worklist THEN the HMS SHALL verify the user has the `radiology.view-worklist` permission
