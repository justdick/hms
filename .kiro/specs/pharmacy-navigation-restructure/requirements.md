# Requirements Document

## Introduction

This feature restructures the Pharmacy navigation in the sidebar to provide direct access to Inventory and Dispensing sections through a collapsible menu, eliminating the unnecessary intermediate step of navigating through the pharmacy dashboard. The menu items will be permission-aware, showing only the sections that the authenticated user has access to.

## Glossary

- **Sidebar**: The main navigation panel on the left side of the application interface
- **NavItem**: A navigation menu item that can be a single link or a collapsible parent with sub-items
- **Collapsible Menu**: A navigation item that expands to reveal sub-items when clicked
- **Permission Gate**: Laravel's authorization mechanism that determines if a user can perform specific actions
- **Pharmacy System**: The module responsible for drug inventory management and prescription dispensing

## Requirements

### Requirement 1

**User Story:** As a pharmacy staff member, I want to access Inventory and Dispensing directly from the sidebar, so that I can navigate to my work area in one click instead of two.

#### Acceptance Criteria

1. WHEN THE Pharmacy System navigation item is rendered, THE Sidebar SHALL display it as a collapsible menu with sub-items
2. THE Sidebar SHALL include an Inventory sub-item under the Pharmacy menu that links to `/pharmacy/inventory`
3. THE Sidebar SHALL include a Dispensing sub-item under the Pharmacy menu that links to `/pharmacy/dispensing`
4. WHEN a user clicks on the Pharmacy menu item, THE Sidebar SHALL expand to reveal the Inventory and Dispensing sub-items
5. WHEN the current page URL starts with `/pharmacy/inventory` or `/pharmacy/dispensing`, THE Sidebar SHALL display the Pharmacy menu in its expanded state by default

### Requirement 2

**User Story:** As a system administrator, I want the sidebar menu items to respect user permissions, so that users only see navigation options they are authorized to access.

#### Acceptance Criteria

1. WHEN the authenticated user lacks the `inventory.view` permission, THE Sidebar SHALL NOT display the Inventory sub-item
2. WHEN the authenticated user lacks the `dispensing.view` permission, THE Sidebar SHALL NOT display the Dispensing sub-item
3. WHEN the authenticated user has the `inventory.view` permission, THE Sidebar SHALL display the Inventory sub-item
4. WHEN the authenticated user has the `dispensing.view` permission, THE Sidebar SHALL display the Dispensing sub-item
5. WHEN the authenticated user has neither `inventory.view` nor `dispensing.view` permissions, THE Sidebar SHALL display the Pharmacy item as a single non-collapsible link to `/pharmacy`

### Requirement 3

**User Story:** As a pharmacy staff member, I want appropriate icons for each menu item, so that I can quickly identify navigation options visually.

#### Acceptance Criteria

1. THE Sidebar SHALL display the Pill icon for the main Pharmacy menu item
2. THE Sidebar SHALL display the Package icon for the Inventory sub-item
3. THE Sidebar SHALL display the ClipboardList icon for the Dispensing sub-item
4. WHEN the sidebar is collapsed to icon-only mode, THE Sidebar SHALL display tooltips showing the item titles on hover

### Requirement 4

**User Story:** As a developer, I want the navigation structure to follow existing patterns in the codebase, so that the implementation is consistent and maintainable.

#### Acceptance Criteria

1. THE Sidebar SHALL use the same NavItem type structure as other collapsible menu items like Insurance
2. THE Sidebar SHALL use the existing NavMain component without modifications to its core logic
3. THE Sidebar SHALL maintain the same visual styling and behavior as other collapsible menu items
4. THE Sidebar SHALL use Inertia's Link component with prefetch enabled for navigation
5. THE Sidebar SHALL use the existing `isItemActive` logic to determine active states based on URL matching
