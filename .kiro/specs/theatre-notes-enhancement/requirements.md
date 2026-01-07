# Requirements Document

## Introduction

Enhancement of the Theatre/Operation Notes system to include comprehensive procedure documentation with support for procedure-specific templates containing inline dropdown selections. This enables standardized documentation for complex procedures like Caesarean Sections while maintaining flexibility for general procedures.

## Glossary

- **Theatre_Notes_System**: The system for documenting surgical/theatre procedures performed during consultations
- **Procedure_Template**: A predefined text template with embedded dropdown placeholders for specific procedure types
- **Template_Variable**: A placeholder within a template that renders as a dropdown selection
- **Procedure_Type**: A surgical procedure from the minor_procedure_types table (includes both minor and major procedures)

## Requirements

### Requirement 1: Basic Theatre Note Fields

**User Story:** As a surgeon, I want to document essential procedure information, so that I have a complete operative record.

#### Acceptance Criteria

1. THE Theatre_Notes_System SHALL include an indication field for documenting why the procedure is being performed
2. THE Theatre_Notes_System SHALL include an assistant field for documenting the assistant surgeon name
3. THE Theatre_Notes_System SHALL include an anaesthetist field for documenting the anaesthetist name
4. THE Theatre_Notes_System SHALL include an anaesthesia_type dropdown with options: Spinal, Local, General, Regional, Sedation
5. THE Theatre_Notes_System SHALL include a procedure_steps field for documenting the procedure performed
6. THE Theatre_Notes_System SHALL include a findings field for documenting intraoperative findings
7. THE Theatre_Notes_System SHALL include a plan field for documenting post-operative plan
8. THE Theatre_Notes_System SHALL include a comments field for additional notes

### Requirement 2: Searchable Procedure Selection

**User Story:** As a surgeon, I want to search for procedures by name or code, so that I can quickly find the correct procedure from 300+ options.

#### Acceptance Criteria

1. WHEN a user types in the procedure selection field, THE Theatre_Notes_System SHALL filter procedures matching the search term
2. THE Theatre_Notes_System SHALL search both procedure name and code fields
3. THE Theatre_Notes_System SHALL display matching procedures without showing pricing information
4. THE Theatre_Notes_System SHALL support async search similar to the lab order search functionality

### Requirement 3: Procedure Templates

**User Story:** As a surgeon, I want predefined templates for common procedures like C-Sections, so that I can document standardized information efficiently.

#### Acceptance Criteria

1. WHEN a procedure has an associated template, THE Theatre_Notes_System SHALL display the template in the procedure_steps field
2. THE Theatre_Notes_System SHALL render template variables as inline dropdown selections within the text
3. WHEN a user selects a value from a template dropdown, THE Theatre_Notes_System SHALL update the displayed text with the selection
4. THE Theatre_Notes_System SHALL store the final composed text with all selections in the procedure_steps field
5. THE Theatre_Notes_System SHALL allow administrators to create and manage procedure templates

### Requirement 4: C-Section Specific Fields

**User Story:** As an obstetrician, I want to document obstetric-specific information for C-Sections, so that I have complete maternal records.

#### Acceptance Criteria

1. WHEN the selected procedure is a Caesarean Section, THE Theatre_Notes_System SHALL display additional obstetric fields
2. THE Theatre_Notes_System SHALL include an estimated_gestational_age field for C-Sections
3. THE Theatre_Notes_System SHALL include a parity field for C-Sections
4. THE Theatre_Notes_System SHALL include a procedure_subtype dropdown for C-Sections with options: Elective C/S, Elective C/S + Sterilization, Elective C/S + Hysterectomy, Emergency C/S, Emergency C/S + Sterilization, Emergency C/S + Hysterectomy

### Requirement 5: C-Section Template Variables

**User Story:** As an obstetrician, I want inline selections in the C-Section template, so that I can quickly document surgical details.

#### Acceptance Criteria

1. THE C-Section template SHALL include an incision_type variable with options: PFANNENSTIEL, MIDLINE
2. THE C-Section template SHALL include a bladder_flap variable with options: was created, was not created
3. THE C-Section template SHALL include a delivery_method variable for baby delivery
4. THE C-Section template SHALL include a placenta_removal variable for placenta removal method
5. THE C-Section template SHALL include a uterine_layers variable with options: ONE LAYER, TWO LAYERS
6. THE C-Section template SHALL include suture_type variables for uterine, fascia, subcutaneous, and skin closure
7. THE Theatre_Notes_System SHALL provide common suture options: CHROMIC 0, CHROMIC 1, CHROMIC 2, VICRYL 0, VICRYL 1, VICRYL 2, NYLON, SILK

### Requirement 6: Template Management

**User Story:** As an administrator, I want to manage procedure templates, so that I can add templates for other procedures in the future.

#### Acceptance Criteria

1. THE Theatre_Notes_System SHALL store templates in a dedicated procedure_templates table
2. THE Theatre_Notes_System SHALL link templates to specific procedure types
3. THE Theatre_Notes_System SHALL store template variables as JSON configuration
4. WHEN a template is updated, THE Theatre_Notes_System SHALL apply the new template to future procedures only
