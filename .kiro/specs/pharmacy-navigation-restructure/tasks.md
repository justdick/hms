# Implementation Plan

- [x] 1. Update TypeScript types to support permissions





  - Extend the `Auth` interface in `resources/js/types/index.d.ts` to include pharmacy permissions structure
  - Add optional `permissions` property with nested `pharmacy` object containing `inventory` and `dispensing` boolean flags
  - _Requirements: 4.1, 4.2_

- [x] 2. Share pharmacy permissions from backend





  - Modify `app/Http/Middleware/HandleInertiaRequests.php` to include pharmacy permissions in the shared `auth` object
  - Use Laravel's `can()` method to check `inventory.view` and `dispensing.view` permissions
  - Implement null-safe checks to handle guest users gracefully
  - _Requirements: 2.1, 2.2, 2.3, 2.4_

- [x] 3. Restructure pharmacy navigation in sidebar






  - [x] 3.1 Import required icons and hooks in `resources/js/components/app-sidebar.tsx`

    - Import `Package` and `ClipboardList` icons from lucide-react
    - Import `usePage` hook from @inertiajs/react with proper typing
    - _Requirements: 3.1, 3.2, 3.3_
  
  - [x] 3.2 Build dynamic pharmacy sub-items array


    - Access permissions from `usePage().props.auth.permissions`
    - Conditionally add Inventory sub-item if user has `inventory.view` permission
    - Conditionally add Dispensing sub-item if user has `dispensing.view` permission
    - _Requirements: 1.2, 1.3, 2.1, 2.2, 2.3, 2.4_
  

  - [x] 3.3 Update pharmacy navigation item configuration

    - Modify the Pharmacy item in `mainNavItems` array to include `items` property
    - Set `items` to the dynamic sub-items array if it has items, otherwise undefined
    - Ensure proper icon assignments for all items
    - _Requirements: 1.1, 1.4, 1.5, 3.1, 3.2, 3.3, 4.1, 4.2, 4.3, 4.4, 4.5_

- [ ] 4. Write backend tests for permission sharing
  - Create test file `tests/Feature/Middleware/HandleInertiaRequestsTest.php` if it doesn't exist
  - Write test to verify pharmacy permissions are correctly shared for authenticated users with various permission combinations
  - Write test to verify guest users receive false values for all permissions without errors
  - Use Laravel's Gate facade to mock permission checks in tests
  - _Requirements: 2.1, 2.2, 2.3, 2.4, 2.5_

- [ ] 5. Manual testing and verification
  - Test user with both inventory and dispensing permissions sees both sub-items
  - Test user with only inventory permission sees only Inventory sub-item
  - Test user with only dispensing permission sees only Dispensing sub-item
  - Test user with no pharmacy sub-permissions sees single Pharmacy link to dashboard
  - Verify clicking sub-items navigates to correct URLs
  - Verify active state highlighting works correctly on inventory and dispensing pages
  - Verify collapsible menu auto-expands when on a pharmacy sub-page
  - Test sidebar collapsed mode shows proper icons and tooltips
  - _Requirements: 1.1, 1.2, 1.3, 1.4, 1.5, 2.1, 2.2, 2.3, 2.4, 2.5, 3.1, 3.2, 3.3, 3.4, 4.1, 4.2, 4.3, 4.4, 4.5_
