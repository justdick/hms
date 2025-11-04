# Requirements Document

## Introduction

The current bulk import feature for insurance coverage rules requires administrators to manually enter item codes, names, and coverage percentages into a CSV template. This is time-consuming, error-prone, and only supports percentage-based coverage. 

This feature will enhance the bulk import system to:
1. **Auto-populate templates** with all drugs/services from the system inventory
2. **Support all coverage types** (percentage, fixed amount, full coverage, excluded) - matching the UI capabilities

**Key Benefits**:
- **Zero manual data entry** - all items pre-filled from system
- **No typos or invalid codes** - guaranteed valid item codes
- **Faster configuration** - just edit coverage values, not enter data
- **Full coverage flexibility** - support all coverage types like the UI

## Glossary

- **Pre-populated Template**: An Excel/CSV file that contains all drugs or services from the system inventory, ready for administrators to edit coverage settings
- **Coverage Type**: The method of coverage calculation (percentage, fixed_amount, full, excluded)
- **Coverage Value**: The numeric value for coverage (percentage or dollar amount depending on type)
- **System Inventory**: The existing drugs, lab tests, and services already configured in the system
- **Bulk Import**: The process of uploading a file to create or update multiple coverage rules at once
- **Item Code**: A unique identifier for a drug, lab test, or service in the system

## Requirements

### Requirement 1: Pre-populated Template Generation

**User Story:** As an insurance administrator, I want to download a template that already contains all drugs or services from my system, so that I don't have to manually type item codes and can focus on setting coverage values.

#### Acceptance Criteria

1. WHEN the administrator requests a template download for a category, THE System SHALL query all items from the system inventory for that category
2. WHEN generating the template, THE System SHALL include item_code, item_name, and current_price for each item
3. WHEN an item already has a specific coverage rule, THE System SHALL pre-fill the coverage_type and coverage_value with existing values
4. WHERE no specific coverage rule exists for an item, THE System SHALL pre-fill with the general rule values for that category
5. WHERE no general rule exists, THE System SHALL pre-fill with default values (percentage, 80)
6. WHEN the template is generated, THE System SHALL include all items sorted alphabetically by name

### Requirement 2: Support All Coverage Types in Template

**User Story:** As an insurance administrator, I want to set different coverage types (percentage, fixed amount, full, excluded) in the bulk import template, so that I have the same flexibility as the manual UI.

#### Acceptance Criteria

1. WHEN the template is generated, THE System SHALL include a coverage_type column with valid options documented
2. WHEN the template is generated, THE System SHALL include a coverage_value column for the numeric value
3. WHEN processing an import, THE System SHALL validate that coverage_type is one of: percentage, fixed_amount, full, excluded
4. WHEN coverage_type is "percentage", THE System SHALL interpret coverage_value as a percentage (0-100)
5. WHEN coverage_type is "fixed_amount", THE System SHALL interpret coverage_value as a dollar amount
6. WHEN coverage_type is "full", THE System SHALL set 100% coverage regardless of coverage_value
7. WHEN coverage_type is "excluded", THE System SHALL set 0% coverage regardless of coverage_value

### Requirement 3: Enhanced Template Instructions

**User Story:** As an insurance administrator, I want clear instructions in the template explaining how to use the pre-populated data and coverage types, so that I can confidently edit and import the file.

#### Acceptance Criteria

1. WHEN the template is generated, THE System SHALL include an Instructions sheet as the first sheet
2. WHEN displaying instructions, THE System SHALL explain that data is pre-populated and only coverage settings need editing
3. WHEN displaying instructions, THE System SHALL provide examples for each coverage type with real-world scenarios
4. WHEN displaying instructions, THE System SHALL warn against modifying item_code, item_name, or current_price columns
5. WHEN displaying instructions, THE System SHALL explain that rows can be deleted for items that should use the general rule

### Requirement 4: Import Processing for All Coverage Types

**User Story:** As a system, I want to correctly process all coverage types during import, so that coverage rules are created accurately based on the administrator's configuration.

#### Acceptance Criteria

1. WHEN processing an import row with coverage_type "percentage", THE System SHALL calculate patient_copay_percentage as (100 - coverage_value)
2. WHEN processing an import row with coverage_type "fixed_amount", THE System SHALL set patient_copay_percentage to 0 and store the fixed amount in coverage_value
3. WHEN processing an import row with coverage_type "full", THE System SHALL set coverage_value to 100 and patient_copay_percentage to 0
4. WHEN processing an import row with coverage_type "excluded", THE System SHALL set is_covered to false and patient_copay_percentage to 100
5. WHEN an invalid coverage_type is encountered, THE System SHALL skip that row and report the error with the row number

### Requirement 5: Template Download Endpoint

**User Story:** As an insurance administrator, I want to download a pre-populated template for a specific insurance plan and category, so that I can edit coverage rules for that plan.

#### Acceptance Criteria

1. WHEN the administrator requests a template download, THE System SHALL require the insurance plan ID and category as parameters
2. WHEN generating the template, THE System SHALL use the specified insurance plan's existing rules to pre-fill coverage values
3. WHEN the download is complete, THE System SHALL provide a filename that includes the category and current date
4. WHEN the administrator lacks permission to manage the plan, THE System SHALL deny the download request
5. WHEN the category is invalid, THE System SHALL return an error message

### Requirement 6: Import Validation and Error Reporting

**User Story:** As an insurance administrator, I want detailed error messages when my import fails, so that I can quickly fix issues and re-import.

#### Acceptance Criteria

1. WHEN an import row has an invalid coverage_type, THE System SHALL report "Invalid coverage_type: {value}. Must be: percentage, fixed_amount, full, or excluded"
2. WHEN an import row has an item_code not in the system, THE System SHALL report "Item code {code} not found in system"
3. WHEN an import row has invalid data, THE System SHALL include the row number in the error message
4. WHEN the import completes, THE System SHALL provide a summary with counts of created, updated, and skipped rows
5. WHERE errors occurred, THE System SHALL provide a detailed error list with row numbers and specific error messages

### Requirement 7: Backward Compatibility

**User Story:** As a system administrator, I want existing import functionality to continue working, so that any existing import processes or documentation remain valid during the transition.

#### Acceptance Criteria

1. WHEN an old-format CSV is uploaded (with coverage_percentage column), THE System SHALL still process it correctly
2. WHEN processing old-format data, THE System SHALL convert coverage_percentage to coverage_type "percentage" and coverage_value
3. WHEN both old and new format columns exist, THE System SHALL prioritize the new format (coverage_type and coverage_value)
4. WHEN the import completes, THE System SHALL work identically for both old and new formats
5. WHERE administrators use the new template, THE System SHALL provide better functionality without breaking existing workflows

### Requirement 8: Frontend Template Download Integration

**User Story:** As an insurance administrator, I want to easily download the pre-populated template from the bulk import modal, so that I can quickly start editing coverage rules.

#### Acceptance Criteria

1. WHEN the bulk import modal is opened, THE System SHALL display a "Download Pre-populated Template" button
2. WHEN the download button is clicked, THE System SHALL request the template for the current plan and category
3. WHEN the template is downloading, THE System SHALL show a loading indicator
4. WHEN the download completes, THE System SHALL provide user feedback
5. WHEN the modal displays instructions, THE System SHALL explain the new pre-populated template structure

## Non-Functional Requirements

### Performance
- Template generation for 1000+ items should complete within 5 seconds
- Import processing should handle 1000+ rows within 30 seconds

### Usability
- Instructions should be clear enough for non-technical administrators
- Error messages should be actionable and specific

### Data Integrity
- Pre-populated data must exactly match system inventory
- Import must not create rules for non-existent items
