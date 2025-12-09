# Requirements Document

## Introduction

This feature provides comprehensive user management capabilities for the Hospital Management System. Administrators can create, view, edit, and manage staff accounts including role assignments and department associations. Password management is restricted to self-service only - users can only change their own passwords through their profile page.

## Glossary

- **User**: A staff member account in the HMS system (doctors, nurses, receptionists, pharmacists, lab technicians, administrators)
- **Username**: A unique alphanumeric identifier (minimum 4 characters) used for authentication
- **Role**: A named collection of permissions that defines what actions a user can perform (e.g., Doctor, Nurse, Admin)
- **Department**: A hospital unit where users can be assigned to work (e.g., OPD, Emergency, Pharmacy)
- **Permission**: A granular access right for specific system actions (e.g., `patients.view-all`, `consultations.create`)
- **Active Status**: Whether a user account is enabled and can log into the system

## Requirements

### Requirement 1

**User Story:** As an administrator, I want to view a list of all system users, so that I can manage staff accounts efficiently.

#### Acceptance Criteria

1. WHEN an administrator navigates to the user management page THEN the System SHALL display a list of all users with their name, username, roles, departments, and active status using Laravel's paginate method for server-side pagination
2. WHEN an administrator searches for a user by name or username THEN the System SHALL filter the user list to show only matching results
3. WHEN an administrator filters users by role or department THEN the System SHALL display only users matching the selected criteria
4. WHEN an administrator views the user list THEN the System SHALL indicate each user's active or inactive status visually

### Requirement 2

**User Story:** As an administrator, I want to create new user accounts, so that I can onboard new staff members to the system.

#### Acceptance Criteria

1. WHEN an administrator submits a new user form with valid data THEN the System SHALL create the user account with the specified name, username, roles, and departments
2. WHEN an administrator creates a new user THEN the System SHALL generate a temporary password and display it once for the administrator to share with the user
3. WHEN an administrator attempts to create a user with an existing username THEN the System SHALL reject the request and display a validation error
4. WHEN an administrator creates a user THEN the System SHALL require at least one role to be assigned

### Requirement 3

**User Story:** As an administrator, I want to edit user account details, so that I can update staff information and access levels.

#### Acceptance Criteria

1. WHEN an administrator edits a user's profile THEN the System SHALL allow modification of name, username, roles, and department assignments
2. WHEN an administrator edits a user THEN the System SHALL NOT display or allow modification of the user's password
3. WHEN an administrator removes all roles from a user THEN the System SHALL reject the change and require at least one role
4. WHEN an administrator updates a user's username to one that already exists THEN the System SHALL reject the request and display a validation error

### Requirement 4

**User Story:** As an administrator, I want to activate or deactivate user accounts, so that I can control system access without deleting accounts.

#### Acceptance Criteria

1. WHEN an administrator deactivates a user account THEN the System SHALL prevent that user from logging into the system
2. WHEN an administrator activates a previously deactivated account THEN the System SHALL restore the user's ability to log in with existing credentials
3. WHEN an administrator attempts to deactivate their own account THEN the System SHALL reject the request to prevent self-lockout
4. WHEN a deactivated user attempts to log in THEN the System SHALL display a message indicating the account is inactive

### Requirement 5

**User Story:** As a user, I want to update my own password through my profile, so that I can maintain account security.

#### Acceptance Criteria

1. WHEN a user navigates to their profile page THEN the System SHALL display a password change form
2. WHEN a user submits a password change with correct current password and valid new password THEN the System SHALL update the password
3. WHEN a user submits a password change with incorrect current password THEN the System SHALL reject the request and display an error
4. WHEN a user submits a new password that does not meet complexity requirements THEN the System SHALL reject the request and display specific validation errors
5. WHEN a user successfully changes their password THEN the System SHALL invalidate other active sessions for security

### Requirement 6

**User Story:** As an administrator, I want to manage available roles in the system, so that I can define appropriate access levels for different staff types.

#### Acceptance Criteria

1. WHEN an administrator views the roles management page THEN the System SHALL display all roles with their associated permissions count
2. WHEN an administrator creates a new role THEN the System SHALL allow selection of permissions from the available permission list
3. WHEN an administrator edits a role THEN the System SHALL allow modification of the role name and associated permissions
4. WHEN an administrator attempts to delete a role that is assigned to users THEN the System SHALL reject the deletion and display the count of affected users

### Requirement 7

**User Story:** As an administrator, I want to reset a user's password when they are locked out, so that I can help staff regain access to their accounts.

#### Acceptance Criteria

1. WHEN an administrator triggers a password reset for a user THEN the System SHALL generate a new temporary password
2. WHEN a password reset is triggered THEN the System SHALL display the temporary password once for the administrator to communicate to the user
3. WHEN a user logs in with a temporary password THEN the System SHALL require immediate password change before accessing other features
4. WHEN a password reset occurs THEN the System SHALL invalidate all existing sessions for that user
