# Requirements Document

## Introduction

The current insurance coverage system only supports general coverage rules (e.g., "80% coverage for all drugs"). This creates a limitation where every drug, lab test, and service receives the same coverage percentage, regardless of the specific item. Insurance companies often have different coverage rates for different items - some drugs may be fully covered while others have copays, some lab tests may be excluded while others are covered at different rates.

This feature will enable a **two-tier coverage system**:
1. **General/Default Rules**: Set once per category (e.g., "All drugs: 80% coverage") - applies to all items unless overridden
2. **Item-Specific Override Rules**: Set only for exceptions (e.g., "Paracetamol: 100% coverage") - takes precedence over general rules

**Key Principle**: Administrators do NOT need to set rules for every single drug or service. They set a general rule for the category, then add specific overrides only for items that differ from the default. This makes configuration efficient while allowing precise control where needed.

## Glossary

- **Coverage Rule**: A configuration that defines how much an insurance plan covers for a service category or specific item
- **General Coverage Rule**: A default coverage rule that applies to all items in a category when no specific rule exists (e.g., "80% for all drugs")
- **Item-Specific Coverage Rule**: A coverage rule that applies to a specific drug, lab test, or service (e.g., "Paracetamol 500mg is 100% covered")
- **Coverage Category**: The type of service (consultation, drug, lab, procedure, ward, nursing)
- **Item Code**: A unique identifier for a specific drug, lab test, or service
- **Coverage Hierarchy**: The system of determining which rule applies when both general and specific rules exist
- **Insurance Plan**: A specific insurance product offered by an insurance provider
- **Copay**: The amount the patient must pay out of pocket
- **Coverage Percentage**: The percentage of the cost that insurance covers

## Requirements

### Requirement 1: Support Item-Specific Coverage Rules

**User Story:** As an insurance administrator, I want to define coverage rules for specific drugs, lab tests, and services, so that different items can have different coverage percentages and copay amounts.

#### Acceptance Criteria

1. WHEN the administrator creates a coverage rule, THE System SHALL allow specifying either a general rule (no item code) or an item-specific rule (with item code)
2. WHEN an item-specific coverage rule is created, THE System SHALL store the item code, item description, coverage percentage, and copay amount
3. WHEN multiple coverage rules exist for the same insurance plan and category, THE System SHALL allow both general and item-specific rules to coexist
4. WHERE an item-specific rule exists for an item, THE System SHALL use the item-specific rule instead of the general rule
5. WHERE no item-specific rule exists for an item, THE System SHALL fall back to the general rule for that category

### Requirement 2: Coverage Rule Hierarchy and Lookup

**User Story:** As a system, I want to apply the most specific coverage rule available for each item, so that insurance coverage is calculated accurately according to the insurance plan's configuration.

#### Acceptance Criteria

1. WHEN calculating coverage for a specific item, THE System SHALL first search for an item-specific coverage rule matching the item code
2. IF no item-specific rule is found, THEN THE System SHALL search for a general coverage rule for the category
3. IF no coverage rule is found at all, THEN THE System SHALL treat the item as not covered (0% coverage)
4. WHEN an item-specific rule exists, THE System SHALL use the coverage percentage and copay settings from that specific rule
5. WHEN applying coverage rules, THE System SHALL respect the effective date ranges (effective_from and effective_to)

### Requirement 3: Configuration Workflow and Location

**User Story:** As an insurance administrator, I want to configure all coverage rules (both general and specific) in the Insurance Plan management section, so that all insurance configuration is centralized and not mixed with drug/service inventory management.

#### Acceptance Criteria

1. WHEN configuring coverage rules, THE System SHALL provide the configuration interface within the Insurance Plan management section
2. WHEN adding drugs or services to the system inventory, THE System SHALL NOT require or prompt for insurance coverage configuration
3. WHEN viewing an insurance plan's coverage configuration, THE System SHALL display the general rules first, followed by any item-specific overrides
4. WHERE no coverage rules exist for a category, THE System SHALL prompt the administrator to create a general default rule first
5. WHEN creating item-specific overrides, THE System SHALL only allow selection of items that exist in the system inventory (drugs, lab tests, services)

### Requirement 4: Admin Interface for Item-Specific Rules

**User Story:** As an insurance administrator, I want to easily create and manage both general and item-specific coverage rules through the admin interface, so that I can configure complex insurance plans efficiently without having to set rules for every single item.

#### Acceptance Criteria

1. WHEN viewing coverage rules for an insurance plan, THE System SHALL display both general rules and item-specific override rules grouped by category
2. WHEN creating a new coverage rule, THE System SHALL provide an option to make it general (applies to all items in category) or specific (applies to one item only)
3. WHERE the administrator chooses item-specific, THE System SHALL provide a search interface to find and select the specific drug code, lab test code, or service code from existing system items
4. WHEN editing a coverage rule, THE System SHALL allow changing coverage percentages, copay amounts, and other rule parameters
5. WHEN displaying coverage rules, THE System SHALL clearly indicate which rules are general defaults and which are item-specific overrides
6. WHEN an item-specific override exists, THE System SHALL display it alongside the general rule to show the difference (e.g., "General: 80% | This item: 100%")

### Requirement 5: Bulk Import of Item-Specific Override Rules

**User Story:** As an insurance administrator, I want to import item-specific coverage override rules in bulk from a spreadsheet or CSV file, so that when an insurance company provides a list of exceptions (e.g., "these 50 drugs are fully covered"), I can efficiently configure them without manual entry for each item.

#### Acceptance Criteria

1. WHEN the administrator uploads a coverage rules file, THE System SHALL validate the file format (CSV or Excel) and required columns (item_code, coverage_percentage, copay_percentage)
2. WHEN processing the import file, THE System SHALL create or update item-specific override rules based on the item codes provided
3. IF an item code in the import file does not exist in the system inventory, THEN THE System SHALL report the error and skip that row
4. WHEN the import is complete, THE System SHALL provide a summary showing how many override rules were created, updated, or skipped
5. WHERE import errors occur, THE System SHALL provide a detailed error report with row numbers and error descriptions
6. WHEN importing override rules, THE System SHALL not affect or modify the general default rules for the category

### Requirement 6: Coverage Rule Reporting and Validation

**User Story:** As an insurance administrator, I want to view reports showing which items have specific coverage rules and which use default rules, so that I can identify gaps in coverage configuration.

#### Acceptance Criteria

1. WHEN the administrator requests a coverage report, THE System SHALL generate a list of all items with their applicable coverage rules
2. WHEN displaying the coverage report, THE System SHALL indicate whether each item uses a specific rule or the default rule
3. WHERE items have no coverage (neither specific nor default), THE System SHALL highlight them as uncovered
4. WHEN filtering the coverage report, THE System SHALL allow filtering by category, coverage percentage, and rule type
5. WHEN exporting the coverage report, THE System SHALL provide the data in CSV or Excel format

### Requirement 7: Backward Compatibility with Existing Rules

**User Story:** As a system administrator, I want existing general coverage rules to continue working without modification, so that the current insurance configuration remains functional during and after the upgrade.

#### Acceptance Criteria

1. WHEN the system is upgraded, THE System SHALL preserve all existing coverage rules without data loss
2. WHEN existing general rules are applied, THE System SHALL continue to function exactly as before the upgrade
3. WHERE no item-specific rules have been created, THE System SHALL behave identically to the previous version
4. WHEN new item-specific rules are added, THE System SHALL not affect the behavior of existing general rules for other items
5. WHEN calculating coverage, THE System SHALL maintain the same calculation logic for general rules as before

### Requirement 8: Service Point Coverage Display

**User Story:** As a pharmacy/lab/service provider, I want to see the specific coverage amount for each item I'm dispensing or providing, so that I can inform the patient of their exact copay amount.

#### Acceptance Criteria

1. WHEN a service provider views an item for an insured patient, THE System SHALL display the specific coverage percentage for that item
2. WHEN displaying coverage information, THE System SHALL show both the insurance-covered amount and the patient copay amount
3. WHERE an item has 100% coverage, THE System SHALL clearly indicate "Fully Covered - No Copay"
4. WHERE an item is not covered, THE System SHALL clearly indicate "Not Covered - Full Payment Required"
5. WHEN multiple items are being provided, THE System SHALL display coverage information for each item individually
