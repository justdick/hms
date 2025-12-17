# Design Document: Imaging/Radiology Integration

## Overview

This feature enhances the HMS to support imaging/radiology services by:
1. Adding image attachment support to the existing lab orders system
2. Restructuring navigation to group Laboratory and Radiology under "Investigations"
3. Creating a dedicated Radiology worklist for radiology staff
4. Enabling image viewing with lightbox/gallery functionality
5. Supporting external image uploads for patient-brought imaging studies

The design follows the existing HMS architecture patterns, using event-driven billing, policy-based authorization, and Inertia.js for the frontend.

## Architecture

### High-Level Architecture

```
┌─────────────────────────────────────────────────────────────────┐
│                     Consultation Interface                       │
│  ┌─────────────────┐  ┌─────────────────┐  ┌─────────────────┐  │
│  │  Lab Tests Tab  │  │  Imaging Tab    │  │ External Upload │  │
│  └────────┬────────┘  └────────┬────────┘  └────────┬────────┘  │
└───────────┼─────────────────────┼─────────────────────┼─────────┘
            │                     │                     │
            ▼                     ▼                     ▼
┌─────────────────────────────────────────────────────────────────┐
│                        Lab Order Model                           │
│  (Enhanced with is_imaging flag and imaging attachments)         │
└─────────────────────────────────────────────────────────────────┘
            │                     │
            ▼                     ▼
┌─────────────────────┐  ┌─────────────────────────────────────────┐
│   Lab Worklist      │  │         Radiology Worklist              │
│   (Existing)        │  │         (New)                           │
│   - Sample collect  │  │         - Image upload                  │
│   - Results entry   │  │         - Report entry                  │
└─────────────────────┘  └─────────────────────────────────────────┘
```

### File Storage Architecture

```
storage/app/imaging/
├── {year}/
│   └── {month}/
│       └── {patient_id}/
│           └── {lab_order_id}/
│               ├── original/
│               │   ├── image_001.jpg
│               │   └── image_002.pdf
│               └── thumbnails/
│                   └── image_001_thumb.jpg
```

## Components and Interfaces

### Backend Components

#### 1. Models

**LabService Model Enhancement**
- Add `is_imaging` boolean field
- Add `modality` string field (nullable)
- Add scopes: `scopeImaging()`, `scopeLaboratory()`

**ImagingAttachment Model (New)**
```php
class ImagingAttachment extends Model
{
    protected $fillable = [
        'lab_order_id',
        'file_path',
        'file_name',
        'file_type',
        'file_size',
        'description',
        'is_external',
        'external_facility_name',
        'external_study_date',
        'uploaded_by',
        'uploaded_at',
    ];
    
    // Relationships
    public function labOrder(): BelongsTo;
    public function uploadedBy(): BelongsTo;
    
    // Accessors
    public function getUrlAttribute(): string;
    public function getThumbnailUrlAttribute(): string;
}
```

**LabOrder Model Enhancement**
- Add `imagingAttachments()` HasMany relationship
- Add `isImaging()` helper method
- Add `scopeImaging()` and `scopeLaboratory()` scopes

#### 2. Controllers

**RadiologyController (New)**
```php
class RadiologyController extends Controller
{
    public function index();           // Worklist
    public function show(LabOrder $order);  // Order details
    public function uploadImages(Request $request, LabOrder $order);
    public function complete(Request $request, LabOrder $order);
}
```

**ImagingAttachmentController (New)**
```php
class ImagingAttachmentController extends Controller
{
    public function store(Request $request, LabOrder $order);
    public function destroy(ImagingAttachment $attachment);
    public function download(ImagingAttachment $attachment);
}
```

**ConsultationLabOrderController Enhancement**
- Add external image upload endpoint
- Filter lab services by type (imaging vs laboratory)

#### 3. Services

**ImagingStorageService (New)**
```php
class ImagingStorageService
{
    public function store(UploadedFile $file, LabOrder $order): string;
    public function generateThumbnail(string $path): string;
    public function delete(string $path): bool;
    public function getStoragePath(LabOrder $order): string;
}
```

#### 4. Policies

**RadiologyPolicy (New)**
```php
class RadiologyPolicy
{
    public function viewWorklist(User $user): bool;
    public function uploadImages(User $user, LabOrder $order): bool;
    public function enterReport(User $user, LabOrder $order): bool;
}
```

**LabOrderPolicy Enhancement**
- Add `uploadExternalImages()` method

### Frontend Components

#### 1. Pages

**Radiology/Index.tsx (New)**
- Radiology worklist with filtering and sorting
- Priority-based highlighting (STAT orders)
- Quick actions for each order

**Radiology/Show.tsx (New)**
- Order details view
- Image upload interface
- Report entry form

#### 2. Components

**Consultation/InvestigationsSection.tsx (New)**
- Tabbed interface for Lab Tests and Imaging
- Replaces current lab orders section

**Consultation/ImagingOrderDialog.tsx (New)**
- Dialog for ordering imaging studies
- Modality filter, priority selection, clinical indication

**Consultation/ExternalImageUpload.tsx (New)**
- Dialog for uploading external images
- Facility name, study date, file upload

**Imaging/ImageGallery.tsx (New)**
- Lightbox/gallery component for viewing images
- Thumbnail grid with full-size view on click

**Imaging/ImagingResultsModal.tsx (New)**
- Modal for viewing imaging results
- Images, report, and metadata display

#### 3. Sidebar Update

**app-sidebar.tsx Enhancement**
- Replace "Laboratory" with "Investigations"
- Add sub-items: Laboratory, Radiology
- Permission-based visibility

## Data Models

### Database Schema Changes

#### lab_services table (ALTER)
```sql
ALTER TABLE lab_services
ADD COLUMN is_imaging BOOLEAN DEFAULT FALSE,
ADD COLUMN modality VARCHAR(50) NULL;

-- Update existing imaging services
UPDATE lab_services SET is_imaging = TRUE WHERE category = 'Imaging';
```

#### imaging_attachments table (CREATE)
```sql
CREATE TABLE imaging_attachments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    lab_order_id BIGINT UNSIGNED NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    file_type VARCHAR(50) NOT NULL,
    file_size BIGINT UNSIGNED NOT NULL,
    description VARCHAR(255) NULL,
    is_external BOOLEAN DEFAULT FALSE,
    external_facility_name VARCHAR(255) NULL,
    external_study_date DATE NULL,
    uploaded_by BIGINT UNSIGNED NOT NULL,
    uploaded_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    
    FOREIGN KEY (lab_order_id) REFERENCES lab_orders(id) ON DELETE CASCADE,
    FOREIGN KEY (uploaded_by) REFERENCES users(id),
    INDEX idx_lab_order_id (lab_order_id),
    INDEX idx_is_external (is_external)
);
```

### Entity Relationships

```
LabService (1) ──────── (N) LabOrder
     │                        │
     │ is_imaging             │
     │ modality               │
     │                        │
     │                        ▼
     │                  ImagingAttachment (N)
     │                        │
     │                        │ file_path
     │                        │ is_external
     │                        │ external_facility_name
     │                        │ uploaded_by
     │                        ▼
     │                      User
     │
     ▼
NhisItemMapping
```

## Correctness Properties

*A property is a characteristic or behavior that should hold true across all valid executions of a system-essentially, a formal statement about what the system should do. Properties serve as the bridge between human-readable specifications and machine-verifiable correctness guarantees.*

### Property 1: Imaging flag persistence
*For any* lab service, setting `is_imaging` to true and saving should result in the persisted value being true when retrieved.
**Validates: Requirements 1.1**

### Property 2: Imaging attachments relationship
*For any* lab order with imaging attachments, the count of attachments retrieved through the relationship should equal the count of attachments created for that order.
**Validates: Requirements 1.3**

### Property 3: Attachment metadata completeness
*For any* uploaded imaging attachment, all required fields (file_path, file_name, file_type, file_size, uploaded_by, uploaded_at) should be non-null.
**Validates: Requirements 1.4**

### Property 4: External image metadata
*For any* imaging attachment marked as external, the external_facility_name and external_study_date fields should be non-null.
**Validates: Requirements 1.5, 4.2**

### Property 5: Permission-based menu visibility
*For any* user, the Laboratory sub-item should be visible if and only if the user has laboratory permissions, and the Radiology sub-item should be visible if and only if the user has radiology permissions.
**Validates: Requirements 2.4, 2.5**

### Property 6: Imaging order billing
*For any* imaging order created through the consultation interface, a corresponding billing charge should be created with the correct service code and amount.
**Validates: Requirements 3.3**

### Property 7: Image attachment indicator
*For any* imaging order, the "has images" indicator should be true if and only if the order has at least one imaging attachment.
**Validates: Requirements 3.5**

### Property 8: File type validation
*For any* file upload attempt, the system should accept only JPEG, PNG, and PDF files, and reject all other file types.
**Validates: Requirements 4.3**

### Property 9: External image non-billable
*For any* external image upload, no billing charge should be created.
**Validates: Requirements 4.5**

### Property 10: Worklist sorting
*For any* set of pending imaging orders, the worklist should display them sorted by priority (STAT > urgent > routine) and then by order time (oldest first).
**Validates: Requirements 5.1**

### Property 11: Worklist filtering
*For any* filter criteria applied to the worklist, all returned orders should match the specified criteria.
**Validates: Requirements 5.3**

### Property 12: Order status transitions
*For any* imaging order, valid status transitions should be: ordered → in_progress → completed, and the system should reject invalid transitions.
**Validates: Requirements 5.5**

### Property 13: Completion requires report
*For any* imaging order completion attempt, the system should reject completion if no report text is provided.
**Validates: Requirements 6.3**

### Property 14: Completion metadata
*For any* completed imaging order, the result_entered_at timestamp and the completing user should be recorded.
**Validates: Requirements 6.4**

### Property 15: File storage path structure
*For any* uploaded image, the file should be stored in a path matching the pattern: imaging/{year}/{month}/{patient_id}/{lab_order_id}/original/{filename}.
**Validates: Requirements 6.5**

### Property 16: External indicator display
*For any* imaging order from an external facility, the results view should display the external facility name and study date.
**Validates: Requirements 7.5**

### Property 17: History separation
*For any* patient with both lab tests and imaging studies, the history display should show imaging studies in a separate section from laboratory tests.
**Validates: Requirements 8.1**

### Property 18: History completeness
*For any* patient, the imaging history should include both internal and external imaging studies.
**Validates: Requirements 8.4**

### Property 19: Authorization - order imaging
*For any* user without the `investigations.order` permission, attempting to order an imaging study should be rejected with a 403 status.
**Validates: Requirements 9.1**

### Property 20: Authorization - upload images
*For any* user without the `radiology.upload` permission, attempting to upload images in radiology should be rejected with a 403 status.
**Validates: Requirements 9.2**

### Property 21: Authorization - enter report
*For any* user without the `radiology.report` permission, attempting to enter a radiology report should be rejected with a 403 status.
**Validates: Requirements 9.3**

### Property 22: Authorization - external upload
*For any* user without the `investigations.upload-external` permission, attempting to upload external images should be rejected with a 403 status.
**Validates: Requirements 9.4**

### Property 23: Authorization - view worklist
*For any* user without the `radiology.view-worklist` permission, attempting to access the radiology worklist should be rejected with a 403 status.
**Validates: Requirements 9.5**

## Error Handling

### File Upload Errors
- Invalid file type: Return 422 with message "Only JPEG, PNG, and PDF files are allowed"
- File too large: Return 422 with message "File size exceeds 50MB limit"
- Storage failure: Return 500 with message "Failed to store file. Please try again."

### Authorization Errors
- Missing permission: Return 403 with appropriate message
- Invalid order state: Return 422 with message explaining valid states

### Validation Errors
- Missing required fields: Return 422 with field-specific error messages
- Invalid external image data: Return 422 with message "External images require facility name and study date"

## Testing Strategy

### Unit Tests
- ImagingStorageService: Test file storage, thumbnail generation, path generation
- ImagingAttachment model: Test relationships, accessors
- LabService scopes: Test imaging() and laboratory() scopes

### Feature Tests (Pest)
- RadiologyController: Test worklist, image upload, completion flow
- Authorization: Test all permission checks
- File upload: Test valid/invalid file types and sizes
- External image upload: Test validation and non-billable behavior

### Property-Based Tests (Pest with Faker)
- Use Pest's property-based testing capabilities
- Generate random lab orders, attachments, and users
- Verify properties hold across all generated inputs
- Minimum 100 iterations per property test

### Test Annotations
Each property-based test must include:
```php
/**
 * Feature: imaging-radiology-integration, Property 1: Imaging flag persistence
 * Validates: Requirements 1.1
 */
```
