# Requirements Document

## Introduction

This feature establishes a professional, healthcare-appropriate UI theming system for the Hospital Management System. The system will provide a cohesive visual design through CSS variables, improved component variants (including compact stat cards), and lay the foundation for future admin-controlled theme customization. The goal is to transform the default Laravel styling into a modern, professional medical application interface.

## Glossary

- **Theme**: A collection of CSS variables defining colors, typography, spacing, and other visual properties
- **Design Token**: A named CSS variable representing a design decision (e.g., `--color-primary`)
- **Stat Card**: A compact card component displaying a single metric with label, value, and optional trend indicator
- **Color Palette**: The set of colors used throughout the application
- **Density Mode**: A setting controlling the compactness of UI elements (compact vs comfortable)
- **Theme Provider**: A React context that supplies theme values to all components

## Requirements

### Requirement 1: Healthcare Color Palette

**User Story:** As a hospital administrator, I want the application to use professional healthcare-appropriate colors, so that the system looks trustworthy and suitable for a medical environment.

#### Acceptance Criteria

1. WHEN the application loads THEN the System SHALL display a primary color palette based on healthcare-appropriate blues/teals
2. WHEN viewing any page THEN the System SHALL use consistent semantic colors for success (green), warning (amber), error (red), and info (blue) states
3. WHEN dark mode is enabled THEN the System SHALL adjust all colors to maintain readability and appropriate contrast ratios
4. WHEN displaying status indicators THEN the System SHALL use distinct colors for different patient/order statuses

### Requirement 2: CSS Variable-Based Theming

**User Story:** As a developer, I want all visual properties defined as CSS variables, so that theme changes propagate automatically throughout the application.

#### Acceptance Criteria

1. WHEN a CSS variable value changes THEN the System SHALL update all components using that variable without code changes
2. WHEN the application initializes THEN the System SHALL load theme variables from a centralized configuration
3. WHEN defining component styles THEN the System SHALL reference CSS variables instead of hardcoded color values
4. WHEN a new color is needed THEN the System SHALL provide a semantic variable name following the established naming convention

### Requirement 3: Compact Stat Card Component

**User Story:** As a user viewing dashboards, I want stat cards to be compact and information-dense, so that I can see more metrics at a glance without excessive scrolling.

#### Acceptance Criteria

1. WHEN displaying a stat card THEN the System SHALL render it with reduced padding and height compared to standard cards
2. WHEN a stat card shows a metric THEN the System SHALL display the label, value, and optional icon in a space-efficient layout
3. WHEN a stat card includes a trend THEN the System SHALL show a small trend indicator (up/down arrow with percentage)
4. WHEN multiple stat cards are displayed THEN the System SHALL arrange them in a responsive grid that adapts to screen size

### Requirement 4: Typography Scale

**User Story:** As a user, I want consistent and readable typography throughout the application, so that information is easy to scan and understand.

#### Acceptance Criteria

1. WHEN displaying text THEN the System SHALL use a defined typography scale with consistent font sizes
2. WHEN rendering headings THEN the System SHALL apply appropriate font weights and sizes based on heading level
3. WHEN displaying body text THEN the System SHALL use a font size and line height optimized for readability
4. WHEN showing data in tables THEN the System SHALL use a slightly smaller font size to increase information density

### Requirement 5: Component Density Variants

**User Story:** As a power user, I want compact UI elements, so that I can see more information on screen without scrolling.

#### Acceptance Criteria

1. WHEN rendering form inputs THEN the System SHALL use compact padding appropriate for data-dense interfaces
2. WHEN displaying tables THEN the System SHALL use reduced row padding for higher information density
3. WHEN showing buttons THEN the System SHALL provide size variants (sm, default, lg) with appropriate padding
4. WHEN rendering cards THEN the System SHALL use consistent but compact internal spacing

### Requirement 6: Theme Provider Infrastructure

**User Story:** As a developer, I want a theme provider that manages theme state, so that future admin customization can be easily implemented.

#### Acceptance Criteria

1. WHEN the application loads THEN the System SHALL initialize the theme provider with default values
2. WHEN theme values are updated THEN the System SHALL persist them and apply changes immediately
3. WHEN a component needs theme values THEN the System SHALL provide access through a React hook
4. WHEN the theme provider receives new configuration THEN the System SHALL validate the values before applying

### Requirement 7: Improved Table Styling

**User Story:** As a user viewing data tables, I want tables to look modern and be easy to scan, so that I can quickly find the information I need.

#### Acceptance Criteria

1. WHEN displaying a table THEN the System SHALL use subtle row striping or hover states for better readability
2. WHEN a table row is hovered THEN the System SHALL highlight it with a subtle background change
3. WHEN displaying table headers THEN the System SHALL use a distinct but not overpowering style
4. WHEN a table has actions THEN the System SHALL align action buttons consistently in the last column

### Requirement 8: Status Badge Improvements

**User Story:** As a user, I want status badges to be visually distinct and meaningful, so that I can quickly understand the state of items.

#### Acceptance Criteria

1. WHEN displaying a status badge THEN the System SHALL use semantic colors matching the status meaning
2. WHEN rendering badges THEN the System SHALL use consistent sizing and border radius
3. WHEN a badge represents a critical status THEN the System SHALL use a more prominent visual treatment
4. WHEN multiple badges appear together THEN the System SHALL maintain consistent spacing

### Requirement 9: Admin Theme Configuration UI

**User Story:** As a hospital administrator, I want to customize the application's colors and branding through a settings interface, so that the system reflects our hospital's identity.

#### Acceptance Criteria

1. WHEN an admin accesses the theme settings page THEN the System SHALL display current theme values with preview
2. WHEN an admin selects a primary color THEN the System SHALL show a color picker and update the preview in real-time
3. WHEN an admin uploads a logo THEN the System SHALL validate the image dimensions and display it in the sidebar/header
4. WHEN an admin saves theme changes THEN the System SHALL persist the configuration to the database and apply it immediately
5. WHEN an admin clicks reset THEN the System SHALL restore the default healthcare theme values

### Requirement 10: Theme Persistence and Loading

**User Story:** As a user, I want theme customizations to persist across sessions and page loads, so that the branding remains consistent.

#### Acceptance Criteria

1. WHEN the application loads THEN the System SHALL fetch saved theme configuration from the database
2. WHEN theme configuration exists THEN the System SHALL apply custom values over defaults
3. WHEN no custom configuration exists THEN the System SHALL use the default healthcare theme
4. WHEN theme values are invalid or corrupted THEN the System SHALL fall back to defaults gracefully

### Requirement 11: Currency Formatting Consistency

**User Story:** As a user viewing financial information, I want all currency values displayed consistently with the Ghana Cedi symbol, so that I can easily understand monetary amounts.

#### Acceptance Criteria

1. WHEN displaying any monetary value THEN the System SHALL use the Ghana Cedi symbol (GH₵) consistently
2. WHEN formatting currency THEN the System SHALL use a centralized currency formatter utility
3. WHEN rendering prices, charges, or payments THEN the System SHALL display values with 2 decimal places
4. WHEN the currency format is needed THEN the System SHALL provide a reusable formatCurrency function
