# Theatre Procedures Implementation

## Overview
Added theatre procedures functionality to allow doctors to document both minor and major procedures performed during consultations. This unifies procedure management while maintaining separate workflows for OPD minor procedures (nurses) and theatre/consultation procedures (doctors).

## Database Changes

### Modified Migration
- **File**: `database/migrations/2025_11_21_175413_create_minor_procedure_types_table.php`
- **Changes**: Added `type` enum field ('minor', 'major') with default 'minor'
- **Note**: Modified original migration (dev environment - migrate:fresh required)

### New Table
- **Table**: `consultation_procedures`
- **Purpose**: Track procedures documented during consultations
- **Fields**:
  - `consultation_id` - Links to consultation
  - `doctor_id` - Doctor who performed/documented
  - `minor_procedure_type_id` - Links to procedure type (unified table)
  - `comments` - Optional procedure notes
  - `performed_at` - Timestamp of procedure

## Models Updated

### MinorProcedureType
- Added `type` field to fillable
- Added scopes: `byType()`, `minor()`, `major()`
- Now serves as unified procedure catalog

### Consultation
- Added `procedures()` relationship (hasMany ConsultationProcedure)

### ConsultationProcedure (New)
- Relationships: `consultation()`, `doctor()`, `procedureType()`
- Fillable: consultation_id, doctor_id, minor_procedure_type_id, comments, performed_at

## Backend Implementation

### Controllers

#### MinorProcedureTypeController
- Updated validation to require `type` field
- Handles both minor and major procedure types

#### ConsultationProcedureController (New)
- `store()` - Document procedure during consultation
- `destroy()` - Remove documented procedure
- Authorization via consultation policy

### Events & Listeners

#### ConsultationProcedurePerformed (Event)
- Dispatched when procedure is documented
- Carries ConsultationProcedure model

#### CreateConsultationProcedureCharge (Listener)
- Automatically creates charge for procedure
- Uses procedure type pricing
- Charge type: 'procedure' (major) or 'minor_procedure' (minor)
- Registered in EventServiceProvider

### Routes
- `POST /consultation/{consultation}/procedures` - Document procedure
- `DELETE /consultation/{consultation}/procedures/{procedure}` - Remove procedure

### Policies

#### ConsultationProcedurePolicy (New)
- Follows consultation access patterns
- Department-based access for viewing
- Only documenting doctor or admin can delete

## Frontend Implementation

### Configuration Page
- **File**: `resources/js/pages/MinorProcedure/Configuration/Index.tsx`
- **Changes**:
  - Added tabs: All, Minor, Major/Theatre
  - Shows procedure type badges
  - Filters procedures by type
  - Updated stats to show minor/major counts

### Procedure Type Modal
- **File**: `resources/js/pages/MinorProcedure/Configuration/ProcedureTypeModal.tsx`
- **Changes**:
  - Added type selector (Minor/Major)
  - Defaults to 'minor'
  - Explains difference between types

### Consultation Show Page
- **File**: `resources/js/pages/Consultation/Show.tsx`
- **Changes**:
  - Added 6th tab: "Theatre"
  - Loads available procedures and consultation procedures
  - Updated interfaces for procedure types

### Theatre Procedures Tab (New)
- **File**: `resources/js/components/Consultation/TheatreProceduresTab.tsx`
- **Features**:
  - Select any procedure (minor or major)
  - Add optional comments
  - View documented procedures table
  - Delete procedures
  - Shows procedure type badges
  - Displays pricing
  - Automatic billing on add

## Key Features

### Unified Procedure Catalog
- Single `minor_procedure_types` table for all procedures
- `type` field distinguishes minor vs major
- Shared pricing and configuration

### Separate Workflows

#### Minor Procedures (Nurses at OPD)
- Access via "Minor Procedures" menu → Queue
- Filtered to show only `type='minor'`
- Existing workflow unchanged

#### Theatre Procedures (Doctors in Consultation)
- Access via Consultation → Theatre tab
- Shows ALL procedures (minor + major)
- Doctors can document any procedure type
- Integrated with consultation workflow

### Automatic Billing
- Event-driven charge creation
- Uses procedure type pricing
- Separate charge types for reporting
- Links to patient check-in for billing

### Access Control
- Configuration: Admin only (existing permission)
- Minor procedure queue: Nurses
- Theatre tab: Doctors with consultation access
- Department-based filtering applies

## Configuration

### Adding Procedures
1. Navigate to: Minor Procedures → Configuration
2. Click "Add Procedure Type"
3. Select type: Minor or Major
4. Fill in details and pricing
5. Procedure available immediately

### Filtering
- **Configuration page**: Tabs filter by type
- **Theatre tab**: Shows all procedures
- **Minor procedure queue**: Shows minor only

## Billing Integration

### Charge Creation
- Automatic via `ConsultationProcedurePerformed` event
- Only creates charge if price > 0
- Charge details:
  - Service type: 'procedure'
  - Service code: procedure code
  - Description: includes procedure type
  - Charge type: 'procedure' or 'minor_procedure'

### Insurance Claims
- Procedures appear as separate line items
- Can be included in insurance claims
- Uses procedure code for tariff lookup

## Testing Checklist

- [ ] Migrate fresh database
- [ ] Create minor procedure types
- [ ] Create major procedure types
- [ ] Filter procedures in configuration
- [ ] Start consultation
- [ ] Document minor procedure in theatre tab
- [ ] Document major procedure in theatre tab
- [ ] Verify charges created
- [ ] Delete procedure
- [ ] Check authorization (different user roles)
- [ ] Verify procedure appears in patient history

## Migration Instructions

### For Development
```bash
php artisan migrate:fresh --seed
```

### For Production
Since we modified the original migration, production will need:
1. Add column migration: `ALTER TABLE minor_procedure_types ADD COLUMN type ENUM('minor', 'major') DEFAULT 'minor' AFTER category`
2. Add index: `CREATE INDEX idx_type_active ON minor_procedure_types(type, is_active)`
3. Update existing records: `UPDATE minor_procedure_types SET type = 'minor'`

## Future Enhancements

### Potential Additions
- Procedure duration tracking
- Anaesthesia type for major procedures
- Surgeon team (primary, assistant)
- Theatre room assignment
- Pre-op and post-op notes
- Complications tracking
- Procedure templates
- Consent form integration

### Reporting
- Procedure volume by type
- Theatre utilization
- Procedure-specific outcomes
- Revenue by procedure type

## Notes

- Procedure types are shared between minor and major
- Configuration page accessible via "Minor Procedures" menu (admin understands)
- Theatre tab shows all procedures for flexibility
- Billing automatically handles both types
- Department-based access control applies throughout
