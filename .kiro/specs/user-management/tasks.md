# Implementation Plan

- [x] 1. Database and Model Setup






  - [x] 1.1 Create migration to add `is_active` and `must_change_password` columns to users table

    - Add `is_active` boolean column with default true
    - Add `must_change_password` boolean column with default false
    - _Requirements: 4.1, 7.3_

  - [x] 1.2 Update User model with new fields, casts, and scopes

    - Add `is_active` and `must_change_password` to fillable
    - Add boolean casts for new fields
    - Add `scopeActive()` query scope
    - _Requirements: 4.1, 4.4_

  - [x] 1.3 Update UserFactory with new states

    - Add `inactive()` state for deactivated users
    - Add `mustChangePassword()` state for users requiring password change
    - _Requirements: 4.1, 7.3_

- [x] 2. User Service and Authorization





  - [x] 2.1 Create UserService with password and session management


    - Implement `generateTemporaryPassword()` method
    - Implement `invalidateUserSessions(User $user)` method
    - Implement `createUser(array $data)` method
    - Implement `updateUser(User $user, array $data)` method
    - _Requirements: 2.2, 5.5, 7.1, 7.4_
  - [x] 2.2 Write property test for session invalidation


    - **Property 8: Successful password change invalidates other sessions**
    - **Validates: Requirements 5.5, 7.4**
  - [x] 2.3 Create UserPolicy for authorization



    - Implement `viewAny()` - requires `users.view-all` permission
    - Implement `create()` - requires `users.create` permission
    - Implement `update()` - requires `users.update` permission
    - Implement `toggleActive()` - requires `users.update` and not self
    - Implement `resetPassword()` - requires `users.reset-password` permission
    - _Requirements: 4.3_

  - [x] 2.4 Write property test for self-deactivation prevention

    - **Property 4: Self-deactivation prevention**
    - **Validates: Requirements 4.3**

- [x] 3. User Management Backend





  - [x] 3.1 Create StoreUserRequest and UpdateUserRequest form requests


    - Validate name, email (unique), roles (required, min 1), departments
    - UpdateUserRequest excludes password field entirely
    - _Requirements: 2.3, 2.4, 3.2, 3.3, 3.4_
  - [x] 3.2 Write property test for email uniqueness


    - **Property 2: Email uniqueness constraint**
    - **Validates: Requirements 2.3, 3.4**
  - [x] 3.3 Write property test for role requirement

    - **Property 1: User creation assigns at least one role**
    - **Validates: Requirements 2.4**
  - [x] 3.4 Create UserController with CRUD operations


    - `index()` - List users with pagination, search, and filters
    - `create()` - Show create form with roles and departments
    - `store()` - Create user with temp password, return password once
    - `edit()` - Show edit form (no password field)
    - `update()` - Update user details (no password)
    - `toggleActive()` - Activate/deactivate user (prevent self)
    - `resetPassword()` - Generate temp password, set must_change_password
    - _Requirements: 1.1, 1.2, 1.3, 1.4, 2.1, 2.2, 3.1, 4.1, 4.2, 7.1, 7.2_
  - [x] 3.5 Write property test for deactivated user authentication


    - **Property 3: Deactivated users cannot authenticate**
    - **Validates: Requirements 4.1, 4.4**

- [x] 4. Checkpoint - Ensure all tests pass





  - Ensure all tests pass, ask the user if questions arise.

- [x] 5. Role Management Backend






  - [x] 5.1 Create StoreRoleRequest and UpdateRoleRequest form requests

    - Validate role name (unique), permissions array
    - _Requirements: 6.2, 6.3_

  - [x] 5.2 Create RolePolicy for authorization

    - Implement `viewAny()`, `create()`, `update()`, `delete()` methods
    - Delete requires checking no users assigned
    - _Requirements: 6.4_

  - [x] 5.3 Write property test for role deletion protection

    - **Property 7: Role deletion blocked when users assigned**
    - **Validates: Requirements 6.4**

  - [x] 5.4 Create RoleController with CRUD operations

    - `index()` - List roles with permission counts and user counts
    - `create()` - Show create form with available permissions
    - `store()` - Create role with permissions
    - `edit()` - Show edit form
    - `update()` - Update role name and permissions
    - `destroy()` - Delete role (only if no users assigned)
    - _Requirements: 6.1, 6.2, 6.3, 6.4_

- [x] 6. Password Change Backend





  - [x] 6.1 Create UpdatePasswordRequest form request


    - Validate current_password (required, matches current)
    - Validate password (required, confirmed, min 8, complexity rules)
    - _Requirements: 5.3, 5.4_
  - [x] 6.2 Write property test for password validation


    - **Property 5: Password change requires correct current password**
    - **Validates: Requirements 5.3**

  - [x] 6.3 Add password change methods to ProfileController

    - `showPasswordForm()` - Display password change form
    - `updatePassword()` - Validate and update password, invalidate sessions
    - _Requirements: 5.1, 5.2, 5.5_
  - [x] 6.4 Write property test for must_change_password redirect


    - **Property 6: Password reset sets must_change_password flag**
    - **Validates: Requirements 7.3**

- [x] 7. Authentication Middleware Updates





  - [x] 7.1 Create middleware to check is_active on login


    - Reject login attempts for inactive users with appropriate message
    - _Requirements: 4.1, 4.4_
  - [x] 7.2 Create middleware to enforce password change


    - Redirect users with must_change_password to password change page
    - Allow only password change and logout routes
    - _Requirements: 7.3_

- [x] 8. Checkpoint - Ensure all tests pass





  - Ensure all tests pass, ask the user if questions arise.

- [x] 9. User Management Frontend





  - [x] 9.1 Create Users/Index.tsx page


    - Display paginated user list with DataTable
    - Include search input for name/email
    - Include role and department filter dropdowns
    - Show active/inactive status badges
    - Action buttons for edit, toggle active, reset password
    - _Requirements: 1.1, 1.2, 1.3, 1.4_

  - [x] 9.2 Create Users/Create.tsx page

    - Form with name, email fields
    - Multi-select for roles (required)
    - Multi-select for departments
    - Display generated password in modal after creation
    - _Requirements: 2.1, 2.2, 2.4_

  - [x] 9.3 Create Users/Edit.tsx page

    - Form with name, email fields (no password)
    - Multi-select for roles (required)
    - Multi-select for departments
    - _Requirements: 3.1, 3.2, 3.3_

- [x] 10. Role Management Frontend






  - [x] 10.1 Create Roles/Index.tsx page

    - Display role list with permission counts and user counts
    - Action buttons for edit, delete
    - Delete confirmation with user count warning
    - _Requirements: 6.1, 6.4_
  - [x] 10.2 Create Roles/Create.tsx and Roles/Edit.tsx pages

    - Form with role name field
    - Checkbox list for permissions grouped by category
    - _Requirements: 6.2, 6.3_

- [x] 11. Profile Password Change Frontend





  - [x] 11.1 Create Profile/Password.tsx page


    - Form with current password, new password, confirm password
    - Password strength indicator
    - Clear validation error messages
    - _Requirements: 5.1, 5.2, 5.3, 5.4_

  - [x] 11.2 Create forced password change layout/page

    - Minimal layout for must_change_password users
    - Only shows password change form and logout option
    - _Requirements: 7.3_



- [x] 12. Routes and Navigation



  - [x] 12.1 Add routes for user management


    - `GET /admin/users` - index
    - `GET /admin/users/create` - create form
    - `POST /admin/users` - store
    - `GET /admin/users/{user}/edit` - edit form
    - `PUT /admin/users/{user}` - update
    - `POST /admin/users/{user}/toggle-active` - toggle active
    - `POST /admin/users/{user}/reset-password` - reset password
    - _Requirements: 1.1, 2.1, 3.1, 4.1, 4.2, 7.1_
  - [x] 12.2 Add routes for role management


    - `GET /admin/roles` - index
    - `GET /admin/roles/create` - create form
    - `POST /admin/roles` - store
    - `GET /admin/roles/{role}/edit` - edit form
    - `PUT /admin/roles/{role}` - update
    - `DELETE /admin/roles/{role}` - destroy
    - _Requirements: 6.1, 6.2, 6.3, 6.4_
  - [x] 12.3 Add routes for profile password change


    - `GET /profile/password` - show form
    - `PUT /profile/password` - update password
    - _Requirements: 5.1, 5.2_
  - [x] 12.4 Add navigation links to admin sidebar


    - User Management link (visible to users with users.view-all)
    - Role Management link (visible to users with roles.view-all)
    - _Requirements: 1.1, 6.1_

- [x] 13. Permissions Seeder






  - [x] 13.1 Create or update permissions seeder

    - Add `users.view-all`, `users.create`, `users.update`, `users.reset-password`
    - Add `roles.view-all`, `roles.create`, `roles.update`, `roles.delete`
    - Assign permissions to Admin role
    - _Requirements: All_

- [ ] 14. Final Checkpoint - Ensure all tests pass



  - Ensure all tests pass, ask the user if questions arise.
