# Implementation Plan

- [x] 1. Set up healthcare color palette and CSS variables






  - [x] 1.1 Update app.css with healthcare-appropriate color palette

    - Replace neutral grays with professional blues/teals
    - Add semantic color variables (success, warning, error, info)
    - Update dark mode colors for proper contrast
    - _Requirements: 1.1, 1.2, 1.3_


  - [x] 1.2 Add typography scale CSS variables


    - Define font size scale (xs, sm, base, lg, xl, 2xl)
    - Add line-height variables for readability
    - _Requirements: 4.1_

- [x] 2. Create compact StatCard component






  - [x] 2.1 Create StatCard component with compact styling

    - Build component with label, value, icon props
    - Add trend indicator (up/down arrow with percentage)
    - Use reduced padding for compact appearance
    - Support variant prop for semantic colors
    - _Requirements: 3.1, 3.2, 3.3_

  - [x] 2.2 Write property test for stat card trend indicator

    - **Property 1: Stat Card Trend Indicator Consistency**
    - **Validates: Requirements 3.3**

- [x] 3. Enhance Badge component with semantic variants





  - [x] 3.1 Update Badge component with status variants


    - Add success, warning, error, info, secondary variants
    - Map variants to semantic color CSS variables
    - _Requirements: 8.1, 8.2_

  - [x] 3.2 Write property test for badge color mapping

    - **Property 4: Badge Status Color Mapping**
    - **Validates: Requirements 8.1**

- [x] 4. Improve Table component styling






  - [x] 4.1 Update Table component with compact styling

    - Add subtle row hover states
    - Reduce row padding for density
    - Style headers with distinct but subtle appearance
    - _Requirements: 7.1, 7.2, 7.3, 7.4_

- [x] 5. Create theme infrastructure





  - [x] 5.1 Create ThemeSetting model and migration


    - Create migration for theme_settings table
    - Create ThemeSetting model with JSON casting
    - Add ThemeSettingService for CRUD operations
    - _Requirements: 10.1, 10.2, 10.3_

  - [x] 5.2 Create ThemeProvider React context

    - Build context with theme state and update methods
    - Create useTheme hook for component access
    - Implement theme loading from backend
    - Add CSS variable injection logic
    - _Requirements: 6.1, 6.2, 6.3_

  - [x] 5.3 Write property test for theme validation

    - **Property 3: Theme Validation**
    - **Validates: Requirements 6.4**

  - [x] 5.4 Write property test for invalid config fallback

    - **Property 8: Invalid Config Fallback**
    - **Validates: Requirements 10.4**

- [x] 6. Create theme settings API





  - [x] 6.1 Create ThemeSettingController with CRUD endpoints


    - GET /api/settings/theme - fetch current theme
    - PUT /api/settings/theme - update theme
    - POST /api/settings/theme/reset - reset to defaults
    - Add StoreThemeSettingRequest for validation
    - _Requirements: 9.4, 9.5, 10.1_

  - [x] 6.2 Create logo upload endpoint
    - POST /api/settings/theme/logo - upload logo
    - Validate file type (PNG, JPG, SVG)
    - Validate file size (max 2MB)
    - Store in public storage
    - _Requirements: 9.3_
  - [x] 6.3 Write property test for theme update persistence


    - **Property 2: Theme Update Persistence**
    - **Validates: Requirements 6.2, 9.4**
  - [x] 6.4 Write property test for logo upload validation


    - **Property 5: Logo Upload Validation**
    - **Validates: Requirements 9.3**

- [x] 7. Checkpoint - Ensure all tests pass

  - Ensure all tests pass, ask the user if questions arise.

- [x] 8. Create theme settings admin page





  - [x] 8.1 Create ThemeSettings page component


    - Build settings form with color pickers
    - Add logo upload with preview
    - Implement live preview of changes
    - Add save and reset buttons
    - _Requirements: 9.1, 9.2, 9.5_

  - [x] 8.2 Add theme settings route and navigation

    - Add route to admin routes
    - Add link in admin sidebar/settings menu
    - Add ThemeSettingPolicy for authorization
    - _Requirements: 9.1_

  - [x] 8.3 Write property test for theme reset

    - **Property 6: Theme Reset Restores Defaults**
    - **Validates: Requirements 9.5**

  - [x] 8.4 Write property test for custom config override

    - **Property 7: Custom Config Override**
    - **Validates: Requirements 10.2**

- [x] 9. Integrate theme provider into application






  - [x] 9.1 Wrap application with ThemeProvider

    - Add ThemeProvider to app layout
    - Pass initial theme from backend via Inertia shared data
    - _Requirements: 6.1, 10.2, 10.3_

  - [x] 9.2 Update existing components to use theme variables

    - Verify Card, Button, Input use CSS variables
    - Update any hardcoded colors to use variables
    - _Requirements: 2.1, 2.3_

- [x] 10. Create centralized currency formatting utility






  - [x] 10.1 Create formatCurrency utility function

    - Create utility in lib/utils.ts or lib/currency.ts
    - Use Ghana Cedi symbol (GH₵) consistently
    - Format with 2 decimal places
    - Handle null/undefined values gracefully
    - _Requirements: 11.1, 11.2, 11.3, 11.4_

  - [x] 10.2 Update existing currency displays to use formatter

    - Search for hardcoded currency symbols
    - Replace with formatCurrency utility calls
    - _Requirements: 11.1, 11.2_

- [x] 11. Update dashboard pages to use StatCard component




  - [x] 11.1 Update Wards Index page stats section
    - Replace Card-based stats with StatCard components
    - Apply appropriate semantic variants
    - _Requirements: 3.1, 3.2, 3.4_
  - [x] 11.2 Update Pharmacy dashboard stats


    - Replace existing stat cards with StatCard component
    - _Requirements: 3.1, 3.2_

  - [x] 11.3 Update Laboratory dashboard stats

    - Replace existing stat cards with StatCard component
    - Apply semantic color variants based on status
    - _Requirements: 3.1, 3.2_

  - [x] 11.4 Update Billing/Finance dashboard stats

    - Replace existing stat cards with StatCard component
    - _Requirements: 3.1, 3.2_

  - [x] 11.5 Update Ward Show page stats section

    - Replace bed status cards with StatCard component
    - _Requirements: 3.1, 3.2_


- [x] 12. Final Checkpoint - Ensure all tests pass




  - Ensure all tests pass, ask the user if questions arise.
