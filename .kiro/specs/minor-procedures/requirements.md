# Minor Procedures Module - Requirements

## Overview

Add a Minor Procedures module for nurses to perform and document nursing procedures (wound dressing, catheter changes, suture removal, etc.) with proper tracking, billing, and supply management.

## Business Context

### Problem
Nurses perform various minor procedures that currently have no dedicated workflow for:
- Documentation and tracking
- Supply usage tracking
- Proper billing
- Clinical record keeping

### Solution
Create a dedicated Minor Procedures module that:
- Uses existing check-in infrastructure (Minor Procedures as a department)
- Provides nurse-focused interface for procedure documentation
- Tracks supplies used and integrates with pharmacy
- Supports diagnosis coding for insurance claims
- Auto-generates charges via events

## User Roles

### Primary Users
- **Nurses**: Perform and document procedures
- **Front Desk**: Check in patients to Minor Procedures department

### Secondary Users
- **Pharmacy**: Dispense supplies requested for procedures
- **Billing**: Process procedure charges
- **Admin**: Configure procedure types and pricing

## Core Requirements

### 1. Check-in Integration

**AC1.1**: Front desk can check in patients to "Minor Procedures" department
- Uses existing check-in workflow
- No changes to current check-in page
- Patient enters Minor Procedures queue

**AC1.2**: Department configuration for Minor Procedures
- Department name: "Minor Procedures"
- Department code: "MINPROC"
- Vitals: Optional (configurable)
- Has billing configuration

### 2. Minor Procedures Queue

**AC2.1**: Queue count display
- Show count of patients waiting in Minor Procedures queue
- Display prominently on page (e.g., "3 patients waiting")
- Updates when procedures completed

**AC2.2**: Patient search within queue
- Search bar to find patient in Minor Procedures queue
- Search by: patient name, patient number, phone number
- Only shows patients currently in Minor Procedures queue
- Prevents selecting patients not in queue
- Similar to consultation search pattern

### 3. Procedure Documentation

**AC3.1**: Procedure type selection
- Dropdown with predefined procedure types:
  - Wound Dressing
  - Catheter Change (Urinary)
  - Catheter Change (IV)
  - Suture Removal
  - Dressing Change
  - Injection (IM/IV/SC)
  - Nebulization
  - Other (with text input)
- Required field

**AC3.2**: Diagnosis support
- Search and add ICD-10 diagnoses (optional)
- Support multiple diagnoses
- Reuse existing diagnosis search component
- Same diagnosis table as consultations

**AC3.3**: Procedure notes
- Free text area for documentation
- Required field
- Support for detailed clinical notes

**AC3.4**: Supplies request
- Search supplies from drugs/inventory
- Add multiple supplies with quantities
- Shows available stock
- Optional (not all procedures need supplies)

### 4. Supply Management

**AC4.1**: Supply tracking
- Track which supplies used for each procedure
- Link to drugs/inventory table
- Record quantities requested
- Track dispensing status

**AC4.2**: Pharmacy integration
- Supplies appear in pharmacy dispensing queue
- Pharmacy can dispense supplies
- Auto-creates charges on dispensing
- Uses existing dispensing workflow

### 5. Billing Integration

**AC5.1**: Automatic charge creation
- Department consultation fee charged at check-in (if set)
- Additional procedure charges created via event (if procedure price set)
- Uses department billing configuration + procedure type pricing
- Charges linked to check-in and procedure

**AC5.2**: Supply charges
- Separate charges for each supply item
- Created when pharmacy dispenses
- Uses drug pricing from inventory

**AC5.3**: Insurance support
- Procedures can be covered by insurance
- Uses "procedures" coverage category
- Diagnosis codes support claims

### 6. Completion Workflow

**AC6.1**: Complete procedure
- Mark procedure as completed
- Update check-in status to "completed"
- Remove from queue
- Show success message

**AC6.2**: Procedure history
- View past procedures for patient
- Show on patient history page
- Include procedure type, date, nurse, notes

### 7. Authorization

**AC7.1**: Permissions
- `minor-procedures.view-dept` - View department queue
- `minor-procedures.perform` - Perform procedures
- `minor-procedures.view-all` - View all procedures (admin)

**AC7.2**: Department-based access
- Nurses assigned to Minor Procedures department
- Can only see their department's queue
- Admin can see all

### 8. Reporting

**AC8.1**: Procedure statistics
- Count by procedure type
- Count by nurse
- Revenue by procedure type
- Supply usage tracking

## Data Model

### Tables Required

#### 1. minor_procedures
```
- id
- patient_checkin_id (FK)
- nurse_id (FK to users)
- procedure_type (enum or string)
- procedure_notes (text)
- performed_at (timestamp)
- status (in_progress, completed)
- created_at
- updated_at
```

#### 2. minor_procedure_diagnoses (pivot)
```
- minor_procedure_id (FK)
- diagnosis_id (FK)
```

#### 3. minor_procedure_supplies
```
- id
- minor_procedure_id (FK)
- drug_id (FK)
- quantity (decimal)
- dispensed (boolean)
- dispensed_at (timestamp)
- dispensed_by (FK to users, nullable)
- created_at
- updated_at
```

### Relationships

- MinorProcedure belongsTo PatientCheckin
- MinorProcedure belongsTo User (nurse)
- MinorProcedure belongsToMany Diagnosis
- MinorProcedure hasMany MinorProcedureSupply
- MinorProcedureSupply belongsTo Drug
- MinorProcedureSupply belongsTo User (dispenser)

## Events & Listeners

### Events
1. **MinorProcedurePerformed**
   - Payload: MinorProcedure
   - Triggered: When procedure completed

2. **MinorProcedureSupplyRequested**
   - Payload: MinorProcedureSupply
   - Triggered: When supplies added to procedure

### Listeners
1. **CreateMinorProcedureCharge**
   - Listens: MinorProcedurePerformed
   - Action: Create additional charge for procedure (if procedure price > 0)

2. **CreateSupplyCharge** (existing)
   - Listens: Supply dispensed event
   - Action: Create charge for supplies

## UI/UX Requirements

### Navigation
- Add "Minor Procedures" menu item
- Icon: Medical cross or bandage icon
- Accessible to nurses with permission

### Minor Procedures Page Layout

```
┌─────────────────────────────────────────────────────┐
│ Minor Procedures                                    │
├─────────────────────────────────────────────────────┤
│                                                     │
│ 3 patients waiting in queue                         │
│                                                     │
│ Search Patient in Queue:                            │
│ ┌─────────────────────────────────────────────┐   │
│ │ 🔍 Search by name, number, or phone...      │   │
│ └─────────────────────────────────────────────┘   │
│                                                     │
│ // Search results appear here when typing          │
│ ┌─────────────────────────────────────────────┐   │
│ │ John Doe (PAT2025000123)                    │   │
│ │ Checked in: 10:30 AM | Vitals: Recorded    │   │
│ │ [Select]                                    │   │
│ └─────────────────────────────────────────────┘   │
│                                                     │
│ ┌─────────────────────────────────────────────┐   │
│ │ Jane Smith (PAT2025000124)                  │   │
│ │ Checked in: 10:45 AM | Vitals: Not recorded│   │
│ │ [Select]                                    │   │
│ └─────────────────────────────────────────────┘   │
│                                                     │
└─────────────────────────────────────────────────────┘
```

### Procedure Form (Modal or Slide-over)

```
┌─────────────────────────────────────────────────────┐
│ Perform Procedure - John Doe (PAT2025000123)       │
├─────────────────────────────────────────────────────┤
│                                                     │
│ Procedure Type: *                                   │
│ [Wound Dressing ▼]                                  │
│                                                     │
│ Diagnosis: (optional)                               │
│ [Search ICD-10...] [+ Add]                         │
│ • T14.1 - Open wound of unspecified region [×]     │
│                                                     │
│ Procedure Notes: *                                  │
│ ┌─────────────────────────────────────────────┐   │
│ │ Cleaned wound with normal saline,           │   │
│ │ applied antiseptic, dressed with            │   │
│ │ sterile gauze...                            │   │
│ └─────────────────────────────────────────────┘   │
│                                                     │
│ Supplies Needed:                                    │
│ [Search supplies...] [+ Add]                       │
│ • Spirit (100ml) - Qty: [1] [×]                    │
│ • Gauze (pack) - Qty: [2] [×]                      │
│ • Bandage (roll) - Qty: [1] [×]                    │
│                                                     │
│ [Complete Procedure] [Cancel]                      │
└─────────────────────────────────────────────────────┘
```

## Technical Constraints

### Must Follow
- Use existing check-in infrastructure
- Follow event-driven billing pattern
- Use policy-based authorization
- Reuse diagnosis search component
- Integrate with existing pharmacy dispensing
- Follow HMS architecture patterns

### Performance
- Queue should load quickly (<500ms)
- Support concurrent procedures by multiple nurses
- Real-time queue updates preferred

## Success Criteria

### Functional
- ✅ Nurses can document procedures efficiently
- ✅ Supplies tracked and billed correctly
- ✅ Procedures appear in patient history
- ✅ Charges created automatically
- ✅ Insurance claims supported with diagnosis codes

### Non-Functional
- ✅ Fast workflow (< 2 minutes per procedure)
- ✅ No data loss on form errors
- ✅ Mobile-responsive interface
- ✅ Accessible UI (keyboard navigation, screen readers)

## Out of Scope (Future Enhancements)

- Procedure templates with pre-filled notes
- Photo/image upload for wound documentation
- Procedure scheduling/appointments
- Consent form management
- Procedure-specific vital signs requirements
- Integration with medical devices
- Procedure outcome tracking
- Follow-up scheduling

## Dependencies

### Existing Features Required
- Patient check-in system
- Department management
- Diagnosis search and ICD-10 codes
- Pharmacy inventory and dispensing
- Billing and charges system
- Event-driven architecture
- Permission system

### New Infrastructure
- Minor Procedures department (data)
- New database tables
- New permissions
- New events and listeners

## Risks & Mitigations

### Risk 1: Supply stock availability
**Mitigation**: Show available stock when selecting supplies, prevent over-requesting

### Risk 2: Concurrent access to same patient
**Mitigation**: Lock check-in when procedure started, show "in progress" status

### Risk 3: Incomplete documentation
**Mitigation**: Make critical fields required, validate before submission

### Risk 4: Billing errors
**Mitigation**: Use existing event-driven pattern, comprehensive tests

## Testing Requirements

### Unit Tests
- MinorProcedure model relationships
- Supply tracking logic
- Charge calculation

### Feature Tests
- Complete procedure workflow
- Supply request and dispensing
- Charge creation via events
- Authorization checks
- Queue management

### Browser Tests (Optional)
- End-to-end procedure documentation
- Supply search and selection
- Form validation

## Acceptance Criteria Summary

1. ✅ Patients can be checked into Minor Procedures department
2. ✅ Nurses see queue of waiting patients
3. ✅ Nurses can document procedures with type, diagnosis, notes
4. ✅ Nurses can request supplies from pharmacy
5. ✅ Supplies are tracked and dispensed by pharmacy
6. ✅ Procedure charges created automatically
7. ✅ Supply charges created on dispensing
8. ✅ Procedures appear in patient history
9. ✅ Authorization via permissions and policies
10. ✅ All workflows tested

## Questions for Clarification

1. Should vitals be required for minor procedures? (Suggested: No)
2. Should we support procedure cancellation? (Suggested: Yes, with reason)
3. Should supplies be dispensed immediately or queued? (Suggested: Queued, pharmacy dispenses)
4. Should we track procedure duration/time? (Suggested: Future enhancement)
5. Should nurses be able to edit completed procedures? (Suggested: No, only view)

---

**Status**: Draft - Ready for Review
**Created**: 2025-01-21
**Last Updated**: 2025-01-21
