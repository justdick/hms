# Design Document

## Overview

This design restructures the Pharmacy navigation in the sidebar from a single link to a collapsible menu with Inventory and Dispensing sub-items. The implementation will leverage Laravel's Gate authorization system to conditionally display menu items based on user permissions, with permissions shared to the frontend via Inertia's HandleInertiaRequests middleware.

## Architecture

### High-Level Flow

```
User Request → HandleInertiaRequests Middleware → Share Permissions
                                                         ↓
                                    Frontend receives permissions in page props
                                                         ↓
                                    AppSidebar component filters NavItems
                                                         ↓
                                    NavMain renders collapsible menu
```

### Components Involved

1. **HandleInertiaRequests Middleware** - Shares user permissions with frontend
2. **AppSidebar Component** - Defines navigation structure and filters based on permissions
3. **NavMain Component** - Renders navigation (no changes needed)
4. **TypeScript Types** - Extended to include permissions in NavItem structure

## Components and Interfaces

### Backend: Permission Sharing

**File:** `app/Http/Middleware/HandleInertiaRequests.php`

The middleware will be extended to share pharmacy-related permissions with every Inertia response:

```php
public function share(Request $request): array
{
    return [
        ...parent::share($request),
        // ... existing shared data
        'auth' => [
            'user' => $request->user(),
            'permissions' => [
                'pharmacy' => [
                    'inventory' => $request->user()?->can('inventory.view') ?? false,
                    'dispensing' => $request->user()?->can('dispensing.view') ?? false,
                ],
            ],
        ],
    ];
}
```

**Rationale:** 
- Uses Laravel's built-in `can()` method which checks Gates/Policies
- Permissions are shared globally so they're available to all components
- Null-safe operator ensures no errors for guest users
- Nested structure keeps permissions organized by module

### Frontend: Type Definitions

**File:** `resources/js/types/index.d.ts`

Extend the `Auth` interface to include permissions:

```typescript
export interface Auth {
    user: User;
    permissions?: {
        pharmacy?: {
            inventory: boolean;
            dispensing: boolean;
        };
    };
}
```

**Rationale:**
- Optional chaining allows backward compatibility
- Typed permissions provide IDE autocomplete and type safety
- Structure mirrors backend permission organization

### Frontend: Navigation Configuration

**File:** `resources/js/components/app-sidebar.tsx`

Update the `mainNavItems` array to use a collapsible structure with conditional sub-items:

```typescript
import { usePage } from '@inertiajs/react';
import { Package, ClipboardList } from 'lucide-react';

export function AppSidebar() {
    const { auth } = usePage<SharedData>().props;
    
    // Build pharmacy sub-items based on permissions
    const pharmacyItems: NavItem[] = [];
    
    if (auth.permissions?.pharmacy?.inventory) {
        pharmacyItems.push({
            title: 'Inventory',
            href: '/pharmacy/inventory',
            icon: Package,
        });
    }
    
    if (auth.permissions?.pharmacy?.dispensing) {
        pharmacyItems.push({
            title: 'Dispensing',
            href: '/pharmacy/dispensing',
            icon: ClipboardList,
        });
    }
    
    const mainNavItems: NavItem[] = [
        // ... other items
        {
            title: 'Pharmacy',
            href: '/pharmacy',
            icon: Pill,
            items: pharmacyItems.length > 0 ? pharmacyItems : undefined,
        },
        // ... other items
    ];
    
    return (
        // ... existing sidebar structure
    );
}
```

**Rationale:**
- Dynamic sub-items array built based on permissions
- If no permissions, `items` is undefined, making it a single link (fallback behavior)
- Follows existing pattern used by Insurance menu
- Icons chosen for semantic meaning (Package for inventory, ClipboardList for dispensing)

## Data Models

### Permission Structure

The permission keys align with existing middleware in `routes/pharmacy.php`:

- `inventory.view` - Controls access to inventory pages
- `dispensing.view` - Controls access to dispensing pages

These permissions are already enforced on the backend routes, so we're simply exposing them to the frontend for UI consistency.

## Error Handling

### Missing Permissions

**Scenario:** User has no pharmacy permissions
**Behavior:** Pharmacy menu item displays as a single link to `/pharmacy` (dashboard)
**Rationale:** Provides graceful degradation; user can still access pharmacy dashboard if they have `pharmacy.view` permission

### Undefined Permission Object

**Scenario:** `auth.permissions` is undefined (e.g., guest user)
**Behavior:** Optional chaining prevents errors; no sub-items are shown
**Rationale:** Defensive programming prevents runtime errors

### Route Protection

**Scenario:** User manually navigates to a route they don't have permission for
**Behavior:** Backend middleware (`can:inventory.view`, `can:dispensing.view`) blocks access
**Rationale:** Frontend permission checks are for UX only; security is enforced server-side

## Testing Strategy

### Backend Tests

**File:** `tests/Feature/Middleware/HandleInertiaRequestsTest.php`

Test that permissions are correctly shared:

```php
it('shares pharmacy permissions for authenticated users', function () {
    $user = User::factory()->create();
    Gate::define('inventory.view', fn () => true);
    Gate::define('dispensing.view', fn () => false);
    
    $response = $this->actingAs($user)->get('/dashboard');
    
    expect($response->viewData('page')['props']['auth']['permissions']['pharmacy'])
        ->toHaveKey('inventory', true)
        ->toHaveKey('dispensing', false);
});

it('handles guest users without errors', function () {
    $response = $this->get('/');
    
    expect($response->viewData('page')['props']['auth']['permissions']['pharmacy'])
        ->toHaveKey('inventory', false)
        ->toHaveKey('dispensing', false);
});
```

### Frontend Tests

**Approach:** Manual testing in browser
**Test Cases:**
1. User with both permissions sees both sub-items
2. User with only inventory permission sees only Inventory sub-item
3. User with only dispensing permission sees only Dispensing sub-item
4. User with no permissions sees single Pharmacy link
5. Clicking sub-items navigates to correct pages
6. Active state highlights correctly when on inventory/dispensing pages
7. Collapsible menu expands when on a pharmacy sub-page

**Rationale:** React component testing for navigation is better suited to manual/E2E testing rather than unit tests

## Visual Design

### Icon Selection

- **Pharmacy (parent):** `Pill` - Existing icon, represents pharmacy module
- **Inventory:** `Package` - Represents stock/inventory management
- **Dispensing:** `ClipboardList` - Represents prescription processing workflow

### Menu Behavior

- **Collapsed sidebar:** Shows only icons with tooltips on hover
- **Expanded sidebar:** Shows icons + text labels
- **Active state:** Highlights when URL starts with the item's href
- **Default open:** Expands automatically when on a pharmacy sub-page

## Implementation Notes

### No Changes to NavMain

The existing `NavMain` component already supports collapsible menus with sub-items (as demonstrated by the Insurance menu). No modifications are needed.

### Backward Compatibility

If permissions are not defined in the shared data (e.g., during development or migration), the optional chaining ensures the app doesn't break. The pharmacy menu will simply display as a single link.

### Performance Considerations

- Permission checks happen once per request in middleware (minimal overhead)
- Frontend filtering is synchronous and fast (2-3 items max)
- No additional API calls needed

## Alternative Approaches Considered

### 1. Server-Side Menu Generation

**Approach:** Generate the entire navigation structure in PHP and pass it to frontend
**Rejected because:** 
- Mixes concerns (navigation structure should be frontend responsibility)
- Harder to maintain (navigation logic split between backend and frontend)
- Less flexible for frontend-specific features (animations, state management)

### 2. Separate Permission Check Hook

**Approach:** Create a `usePermissions()` hook to check permissions
**Rejected because:**
- Adds unnecessary abstraction for simple permission checks
- Permissions are already available in `usePage().props`
- Would require additional imports in every component

### 3. Hide Entire Pharmacy Menu

**Approach:** Don't show Pharmacy menu at all if user has no sub-permissions
**Rejected because:**
- User might still have access to pharmacy dashboard (`pharmacy.view`)
- Inconsistent with other modules (e.g., Insurance always shows)
- Reduces discoverability
