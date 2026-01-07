# Design Document

## Overview

This design enhances the existing Theatre Procedures tab in consultations to support comprehensive operative documentation including procedure-specific templates with inline dropdown selections. The system uses a JSON-based template definition that allows administrators to create templates for any procedure type.

## Architecture

### Component Overview

```
┌─────────────────────────────────────────────────────────────┐
│                    Frontend (React)                          │
├─────────────────────────────────────────────────────────────┤
│  TheatreProceduresTab                                        │
│  ├── AsyncProcedureSearch (searchable dropdown)             │
│  ├── ProcedureForm (basic fields)                           │
│  ├── ObstetricFields (conditional for C-Section)            │
│  └── TemplateRenderer (inline dropdowns in text)            │
└─────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────┐
│                    Backend (Laravel)                         │
├─────────────────────────────────────────────────────────────┤
│  ConsultationProcedureController                             │
│  ├── store() - saves procedure with template data           │
│  └── searchProcedures() - async procedure search            │
│                                                              │
│  ProcedureTemplate Model                                     │
│  └── getTemplateForProcedure()                              │
└─────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────┐
│                    Database                                  │
├─────────────────────────────────────────────────────────────┤
│  consultation_procedures (enhanced)                          │
│  procedure_templates (new)                                   │
└─────────────────────────────────────────────────────────────┘
```

## Components and Interfaces

### Database Schema Changes

#### consultation_procedures table (modify existing)

```sql
-- Add new columns
ALTER TABLE consultation_procedures ADD COLUMN indication TEXT NULL;
ALTER TABLE consultation_procedures ADD COLUMN estimated_gestational_age VARCHAR(50) NULL;
ALTER TABLE consultation_procedures ADD COLUMN parity VARCHAR(50) NULL;
ALTER TABLE consultation_procedures ADD COLUMN procedure_subtype VARCHAR(100) NULL;
ALTER TABLE consultation_procedures ADD COLUMN template_selections JSON NULL;

-- Rename column
ALTER TABLE consultation_procedures CHANGE post_op_plan plan TEXT NULL;
```

#### procedure_templates table (new)

```sql
CREATE TABLE procedure_templates (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    minor_procedure_type_id BIGINT NULL,
    procedure_code VARCHAR(50) NULL,
    name VARCHAR(255) NOT NULL,
    template_text TEXT NOT NULL,
    variables JSON NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    
    FOREIGN KEY (minor_procedure_type_id) REFERENCES minor_procedure_types(id),
    INDEX idx_procedure_code (procedure_code),
    INDEX idx_active (is_active)
);
```

### Template Variable JSON Structure

```json
{
  "variables": [
    {
      "key": "incision_type",
      "label": "Incision Type",
      "options": ["PFANNENSTIEL", "MIDLINE"]
    },
    {
      "key": "bladder_flap",
      "label": "Bladder Flap",
      "options": ["was created", "was not created"]
    },
    {
      "key": "uterine_layers",
      "label": "Number of Layers",
      "options": ["ONE LAYER", "TWO LAYERS"]
    },
    {
      "key": "uterine_suture",
      "label": "Uterine Suture",
      "options": ["CHROMIC 0", "CHROMIC 1", "CHROMIC 2", "VICRYL 0", "VICRYL 1", "VICRYL 2"]
    }
  ]
}
```

### Template Text Format

Template text uses `{{variable_key}}` placeholders:

```
The patient was prepped and draped in the usual sterile fashion in the dorsal supine position with a left-ward tilt. A {{incision_type}} skin incision was made with the scalpel and carried through to the underlying layer of fascia. The fascia was incised and extended laterally. The superior and inferior aspects of the fascial incision was elevated and the underlying rectus muscles were dissected off bluntly in the midline. The peritoneum was bluntly dissected, entered, and extended superiorly and inferiorly with good visualization of the bladder. A bladder flap {{bladder_flap}}. The lower uterine segment was incised in a transverse fashion using the scalpel and extended using manual traction. The {{delivery_method}} subsequently delivered atraumatically. The nose and mouth were bulb suctioned. The cord was clamped and cut. The subsequently handed to the awaiting midwife. The placenta was removed {{placenta_removal}}. The uterus cleared of all clots and debris. The uterine incision was repaired in {{uterine_layers}} using {{uterine_suture}} suture. The uterine incision was reexamined and was noted to be hemostatic. The rectus muscles were reapproximated in the midline. The fascia was closed with {{fascia_suture}}, the subcutaneous layer was closed with {{subcutaneous_suture}}, and the skin was closed with {{skin_suture}}. Sponge and instrument counts were correct twice.
```

### API Endpoints

#### Search Procedures
```
GET /api/procedures/search?q={query}

Response:
{
  "procedures": [
    {
      "id": 1,
      "name": "Caesarean Section",
      "code": "OBGY32A",
      "type": "major",
      "category": "obstetrics",
      "has_template": true
    }
  ]
}
```

#### Get Procedure Template
```
GET /api/procedures/{id}/template

Response:
{
  "template": {
    "id": 1,
    "name": "Caesarean Section Template",
    "template_text": "The patient was prepped...",
    "variables": [...],
    "extra_fields": ["estimated_gestational_age", "parity", "procedure_subtype"]
  }
}
```

### Frontend Components

#### AsyncProcedureSearch Component
- Debounced search input
- Displays procedure name and code
- Shows badge for procedures with templates
- No pricing displayed

#### TemplateRenderer Component
- Parses template text for `{{variable}}` placeholders
- Renders inline Select components for each variable
- Maintains state of all selections
- Generates final composed text

#### ObstetricFields Component
- Conditional rendering when procedure is C-Section
- Fields: gestational_age, parity, procedure_subtype
- Procedure subtype options hardcoded

## Data Models

### ConsultationProcedure Model (updated)

```php
protected $fillable = [
    'consultation_id',
    'doctor_id',
    'minor_procedure_type_id',
    'indication',
    'assistant',
    'anaesthetist',
    'anaesthesia_type',
    'estimated_gestational_age',
    'parity',
    'procedure_subtype',
    'procedure_steps',
    'template_selections',
    'findings',
    'plan',
    'comments',
    'performed_at',
];

protected function casts(): array
{
    return [
        'performed_at' => 'datetime',
        'template_selections' => 'array',
    ];
}
```

### ProcedureTemplate Model (new)

```php
class ProcedureTemplate extends Model
{
    protected $fillable = [
        'minor_procedure_type_id',
        'procedure_code',
        'name',
        'template_text',
        'variables',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'variables' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function procedureType(): BelongsTo
    {
        return $this->belongsTo(MinorProcedureType::class, 'minor_procedure_type_id');
    }

    public static function getForProcedure(int $procedureTypeId): ?self
    {
        return static::where('minor_procedure_type_id', $procedureTypeId)
            ->where('is_active', true)
            ->first();
    }
}
```

## Correctness Properties

*A property is a characteristic or behavior that should hold true across all valid executions of a system-essentially, a formal statement about what the system should do. Properties serve as the bridge between human-readable specifications and machine-verifiable correctness guarantees.*

### Property 1: Procedure Search Returns Matching Results
*For any* search query, all returned procedures should contain the query string in either the name or code field (case-insensitive).
**Validates: Requirements 2.1, 2.2**

### Property 2: Template Variable Completeness
*For any* procedure template, all variables referenced in the template_text as `{{key}}` should have a corresponding entry in the variables array.
**Validates: Requirements 3.2**

### Property 3: Template Selection Persistence
*For any* consultation procedure saved with template selections, retrieving the procedure should return the same template_selections JSON that was saved.
**Validates: Requirements 3.4**

### Property 4: Obstetric Fields Conditional Display
*For any* procedure selection, obstetric-specific fields (gestational_age, parity, procedure_subtype) should only be displayed when the selected procedure is a Caesarean Section.
**Validates: Requirements 4.1**

## Error Handling

### Search Errors
- Empty search returns empty array (not error)
- Database errors return 500 with generic message
- Invalid characters sanitized before search

### Template Errors
- Missing template returns null (procedure can still be documented without template)
- Invalid variable in template_selections ignored during save
- Malformed JSON in template_selections rejected with validation error

### Form Validation
- Required fields: minor_procedure_type_id, performed_at
- Optional fields: all others
- anaesthesia_type must be one of: spinal, local, general, regional, sedation
- procedure_subtype must be valid C-Section subtype if provided

## Testing Strategy

### Unit Tests
- ProcedureTemplate model: getForProcedure() returns correct template
- Template variable parsing: correctly identifies all {{variables}}
- Search query sanitization

### Feature Tests
- Procedure search endpoint returns filtered results
- Procedure creation with template selections
- Procedure creation without template (general procedure)
- C-Section specific fields saved correctly
- Template retrieval for procedure

### Property-Based Tests
- Search results always match query (Property 1)
- Template variables are complete (Property 2)

### Integration Tests
- Full flow: search → select → fill template → save → retrieve
- C-Section flow with all obstetric fields
