# Requirements Document

## Introduction

The current insurance coverage configuration system is functionally complete but has a complex user interface that confuses administrators. The system supports both general default rules (e.g., "80% coverage for all drugs") and item-specific overrides (e.g., "Paracetamol is 100% covered"), but the UI presents too many options, unclear terminology, and a workflow that requires multiple steps for simple tasks.

This feature will redesign the insurance configuration interface to be intuitive, streamlined, and task-focused, making it easy for administrators to:
- Quickly set up a new insurance plan with sensible defaults
- Understand at a glance what's covered and what's not
- Make common changes (like adjusting a coverage percentage) in seconds
- Add exceptions for specific items without confusion

**Key Principle**: The UI should guide users through the most common workflows first, with advanced options available but not prominent. Most administrators just want to say "cover drugs at 80%, labs at 90%, consultations at 70%" and be done.

## Glossary

- **Insurance Plan**: A specific insurance product (e.g., "NHIS Gold Plan", "VET Insurance Standard")
- **Coverage Category**: Type of service - consultation, drug, lab, procedure, ward, nursing
- **Default Coverage**: The standard coverage percentage that applies to all items in a category
- **Coverage Exception**: A specific item that has different coverage than the default
- **Copay**: The percentage or amount the patient must pay
- **Coverage Type**: How coverage is calculated - percentage, fixed amount, full coverage, or excluded

## Requirements

### Requirement 1: Simplified Plan Setup Wizard

**User Story:** As an insurance administrator setting up a new plan, I want a simple wizard that asks me the essential questions first, so that I can configure basic coverage in under 2 minutes.

#### Acceptance Criteria

1. WHEN the administrator creates a new insurance plan, THE System SHALL present a setup wizard with three clear steps: Plan Details, Default Coverage, and Review
2. WHEN setting default coverage, THE System SHALL display all six categories (consultation, drug, lab, procedure, ward, nursing) in a single view with simple percentage inputs
3. WHEN the administrator enters a coverage percentage, THE System SHALL automatically calculate and display the patient copay percentage
4. WHERE the administrator wants the same coverage for all categories, THE System SHALL provide a "Copy to All" button
5. WHEN the wizard is completed, THE System SHALL create the plan and all default coverage rules in one transaction

### Requirement 2: Visual Coverage Dashboard

**User Story:** As an insurance administrator, I want to see all coverage rules for a plan at a glance in a visual dashboard, so that I can quickly understand what's covered without reading through lists.

#### Acceptance Criteria

1. WHEN viewing an insurance plan, THE System SHALL display a coverage dashboard showing all six categories as cards with large, clear percentages
2. WHEN displaying each category card, THE System SHALL show the default coverage percentage, number of exceptions, and a visual indicator (color-coded: green for high coverage, yellow for medium, red for low/none)
3. WHEN the administrator clicks on a category card, THE System SHALL expand to show the default rule and any item-specific exceptions
4. WHERE no default rule exists for a category, THE System SHALL display "Not Configured" with a prominent "Set Default" button
5. WHEN hovering over a category card, THE System SHALL show a quick summary tooltip (e.g., "80% covered, 20% copay, 3 exceptions")

### Requirement 3: Inline Quick Edit

**User Story:** As an insurance administrator, I want to change a coverage percentage directly on the dashboard without opening a modal, so that I can make quick adjustments efficiently.

#### Acceptance Criteria

1. WHEN the administrator clicks on a coverage percentage, THE System SHALL make it editable inline
2. WHEN the administrator changes the percentage and presses Enter or clicks away, THE System SHALL save the change immediately
3. WHEN saving an inline edit, THE System SHALL show a brief success indicator (e.g., green checkmark animation)
4. IF the save fails, THEN THE System SHALL revert to the previous value and show an error message
5. WHEN editing a percentage, THE System SHALL validate that it is between 0 and 100

### Requirement 4: Simplified Exception Management

**User Story:** As an insurance administrator, I want to add coverage exceptions for specific items using a streamlined interface, so that I don't have to navigate through complex forms.

#### Acceptance Criteria

1. WHEN adding an exception, THE System SHALL show a simplified modal with only three fields: Item Search, Coverage Percentage, and Notes (optional)
2. WHEN searching for an item, THE System SHALL show results with item name, code, and current price in a clean list
3. WHEN the administrator selects an item and enters a percentage, THE System SHALL automatically calculate copay and show a preview
4. WHERE an item already has an exception, THE System SHALL indicate this in the search results with a badge
5. WHEN saving an exception, THE System SHALL close the modal and immediately show the new exception in the category's exception list

### Requirement 5: Smart Defaults and Presets

**User Story:** As an insurance administrator, I want to use common coverage presets (like "Standard NHIS" or "Premium Corporate"), so that I don't have to manually configure every category for common plan types.

#### Acceptance Criteria

1. WHEN creating a new plan, THE System SHALL offer preset templates (e.g., "NHIS Standard", "Corporate Premium", "Basic Coverage", "Custom")
2. WHEN the administrator selects a preset, THE System SHALL pre-fill all category coverage percentages with typical values
3. WHEN using a preset, THE System SHALL still allow the administrator to modify any percentage before saving
4. WHERE the administrator chooses "Custom", THE System SHALL start with blank fields
5. WHEN viewing the preset options, THE System SHALL show a preview of what each preset includes (e.g., "Consultation: 70%, Drugs: 80%, Labs: 90%...")

### Requirement 6: Bulk Exception Import with Template

**User Story:** As an insurance administrator, I want to download a pre-formatted Excel template and upload my exceptions, so that I can efficiently add multiple item-specific rules without manual entry.

#### Acceptance Criteria

1. WHEN the administrator clicks "Import Exceptions", THE System SHALL offer to download a template file first
2. WHEN downloading the template, THE System SHALL provide an Excel file with clear column headers, example rows, and instructions
3. WHEN uploading a completed template, THE System SHALL validate all rows and show a preview of what will be imported
4. WHERE errors exist in the upload, THE System SHALL highlight the problematic rows and explain what's wrong
5. WHEN the administrator confirms the import, THE System SHALL create all valid exceptions and provide a summary (e.g., "45 added, 2 skipped due to errors")

### Requirement 7: Coverage Type Simplification

**User Story:** As an insurance administrator, I want to work with coverage in simple terms (percentage, fixed amount, fully covered, not covered), so that I don't have to understand complex insurance terminology.

#### Acceptance Criteria

1. WHEN setting coverage, THE System SHALL present four clear options with icons: "Percentage" (most common), "Fixed Amount", "Fully Covered", "Not Covered"
2. WHEN "Percentage" is selected, THE System SHALL show a single percentage input with automatic copay calculation
3. WHEN "Fixed Amount" is selected, THE System SHALL show a currency input for the amount insurance will pay
4. WHEN "Fully Covered" is selected, THE System SHALL automatically set coverage to 100% and copay to 0%
5. WHEN "Not Covered" is selected, THE System SHALL automatically set coverage to 0% and copay to 100%

### Requirement 8: Clear Visual Hierarchy

**User Story:** As an insurance administrator, I want the interface to clearly distinguish between default rules and exceptions, so that I understand which rule applies to which items.

#### Acceptance Criteria

1. WHEN viewing a category's coverage, THE System SHALL display the default rule in a prominent, highlighted section at the top
2. WHEN displaying exceptions, THE System SHALL show them in a separate, clearly labeled section below the default
3. WHEN an exception is shown, THE System SHALL display a comparison (e.g., "Default: 80% → This item: 100%")
4. WHERE no exceptions exist, THE System SHALL show a friendly message: "All items in this category use the default coverage"
5. WHEN viewing the exception list, THE System SHALL provide filters (search by name, show only fully covered, show only excluded)

### Requirement 9: Contextual Help and Guidance

**User Story:** As an insurance administrator who is new to the system, I want helpful tooltips and guidance text, so that I can configure insurance plans correctly without external training.

#### Acceptance Criteria

1. WHEN the administrator hovers over any coverage field, THE System SHALL show a tooltip explaining what it means
2. WHEN viewing an empty plan, THE System SHALL show a helpful message: "Start by setting default coverage for each category"
3. WHEN adding an exception, THE System SHALL show an example: "Example: Set Paracetamol to 100% if it should be fully covered"
4. WHERE the administrator makes an unusual configuration (e.g., 0% coverage for consultations), THE System SHALL show a warning: "Are you sure? This means patients will pay full price"
5. WHEN the administrator completes a major action, THE System SHALL show a success message with next steps

### Requirement 10: Mobile-Friendly Responsive Design

**User Story:** As an insurance administrator who sometimes works on a tablet, I want the interface to work well on smaller screens, so that I can configure insurance plans from any device.

#### Acceptance Criteria

1. WHEN viewing the coverage dashboard on a tablet, THE System SHALL stack category cards vertically with full width
2. WHEN using the interface on mobile, THE System SHALL hide less important information and show only essentials
3. WHEN adding an exception on mobile, THE System SHALL use a full-screen modal for better usability
4. WHERE inline editing is not practical on mobile, THE System SHALL provide a tap-to-edit button instead
5. WHEN viewing exception lists on mobile, THE System SHALL use a card layout instead of a table

### Requirement 11: Audit Trail and Change History

**User Story:** As an insurance administrator, I want to see who changed coverage rules and when, so that I can track changes and understand the history of a plan.

#### Acceptance Criteria

1. WHEN viewing a coverage rule, THE System SHALL provide a "View History" link
2. WHEN viewing history, THE System SHALL show all changes with date, time, user, old value, and new value
3. WHEN a rule is created, THE System SHALL record who created it and when
4. WHERE multiple changes happen in quick succession, THE System SHALL group them as a single "batch update"
5. WHEN exporting coverage rules, THE System SHALL include an option to include change history

### Requirement 12: Validation and Error Prevention

**User Story:** As an insurance administrator, I want the system to prevent me from making configuration mistakes, so that I don't accidentally create invalid coverage rules.

#### Acceptance Criteria

1. WHEN entering a coverage percentage, THE System SHALL validate it is between 0 and 100
2. WHEN entering a fixed amount, THE System SHALL validate it is a positive number
3. WHERE the administrator tries to create a duplicate exception, THE System SHALL show an error: "This item already has an exception"
4. WHEN saving a rule, THE System SHALL validate that required fields are filled
5. WHERE coverage percentages don't add up correctly (e.g., coverage + copay ≠ 100%), THE System SHALL show a warning

### Requirement 13: Quick Actions and Shortcuts

**User Story:** As an insurance administrator who manages multiple plans, I want keyboard shortcuts and quick actions, so that I can work more efficiently.

#### Acceptance Criteria

1. WHEN viewing the coverage dashboard, THE System SHALL provide a "Quick Actions" menu with common tasks
2. WHEN the administrator presses "N", THE System SHALL open the "Add Exception" modal
3. WHEN the administrator presses "E", THE System SHALL enable inline editing mode for the focused category
4. WHERE the administrator wants to copy coverage from another plan, THE System SHALL provide a "Copy from Plan" action
5. WHEN using quick actions, THE System SHALL show keyboard shortcut hints in tooltips

### Requirement 14: New Item Coverage Alerts

**User Story:** As an insurance administrator, I want to be notified when new items are added to the system that will be covered by default rules, so that I can review and add exceptions if needed.

#### Acceptance Criteria

1. WHEN a new lab service, drug, or other item is added to the system, THE System SHALL check which insurance plans have default coverage for that category
2. WHEN default coverage exists, THE System SHALL create a notification for insurance administrators: "New lab service 'Advanced MRI' added - will be covered at 90% by default"
3. WHEN viewing the notification, THE System SHALL provide a quick action: "Add Exception" or "Keep Default"
4. WHERE the administrator wants to exclude expensive items by default, THE System SHALL provide a plan setting: "Require explicit approval for new items"
5. WHEN "Require explicit approval" is enabled, THE System SHALL treat new items as not covered until an administrator reviews them

### Requirement 15: Coverage Review Dashboard

**User Story:** As an insurance administrator, I want to see a list of recently added items and their coverage status, so that I can ensure expensive or unusual items are configured correctly.

#### Acceptance Criteria

1. WHEN viewing an insurance plan, THE System SHALL provide a "Recent Items" section showing items added in the last 30 days
2. WHEN displaying recent items, THE System SHALL show the item name, price, and whether it uses default coverage or has an exception
3. WHEN an item is expensive (above a threshold like $500), THE System SHALL highlight it with a warning icon
4. WHERE the administrator wants to review an item, THE System SHALL provide a one-click action to add an exception
5. WHEN all recent items have been reviewed, THE System SHALL show a success message: "All recent items reviewed"

### Requirement 16: Backward Compatibility

**User Story:** As a system administrator, I want existing coverage rules to continue working without modification, so that the UI redesign doesn't break existing configurations.

#### Acceptance Criteria

1. WHEN the new UI is deployed, THE System SHALL display all existing coverage rules correctly
2. WHEN viewing old rules in the new UI, THE System SHALL show them with the same data and behavior
3. WHERE old rules use advanced features not in the simplified UI, THE System SHALL still display them with a note: "Advanced configuration"
4. WHEN editing an old rule, THE System SHALL preserve all existing fields even if not shown in the simplified UI
5. WHEN the new UI saves a rule, THE System SHALL use the same database structure as before
