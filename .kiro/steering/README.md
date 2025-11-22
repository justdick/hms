---
inclusion: always
---

# HMS Steering Documentation

This directory contains comprehensive steering documentation for the Hospital Management System (HMS). These documents guide development work and ensure consistency across the codebase.

## Available Steering Documents

### 1. HMS Architecture (`hms-architecture.md`)
**Inclusion**: Always

Core architectural patterns and conventions for the HMS system.

**Topics Covered**:
- Project overview and core modules
- Domain-driven controller structure
- Event-driven billing system
- Service layer pattern
- Policy-based authorization
- Database conventions and patterns
- Permission naming conventions
- Business rules and workflows
- Common pitfalls to avoid
- Performance considerations
- Security best practices

**When to Reference**: 
- Starting any new feature
- Understanding system architecture
- Implementing authorization
- Working with events and billing
- Setting up database relationships

### 2. HMS Insurance Module (`hms-insurance.md`)
**Inclusion**: Manual (use `#hms-insurance` in chat)

Detailed guide for the insurance management module.

**Topics Covered**:
- Insurance hierarchy (Provider → Plan → Coverage Rules)
- Coverage determination logic
- Tariff system
- Database schema
- Coverage management (category defaults vs exceptions)
- Claims processing workflow (creation, vetting, submission)
- Frontend patterns for insurance UI
- Reporting
- Common scenarios and calculations
- Best practices
- Troubleshooting

**When to Reference**:
- Working on insurance features
- Implementing coverage rules
- Processing insurance claims
- Calculating insurance coverage
- Debugging coverage issues

### 3. HMS Frontend Development (`hms-frontend.md`)
**Inclusion**: Manual (use `#hms-frontend` in chat)

Complete guide for frontend development with React, Inertia.js, and Tailwind CSS.

**Topics Covered**:
- Tech stack overview
- Project structure
- Inertia.js patterns (pages, navigation, forms)
- Component patterns (reusable forms, modals, slide-overs)
- Styling with Tailwind CSS v4
- TypeScript types and interfaces
- State management
- Performance optimization
- Accessibility guidelines
- Common UI patterns
- Testing components
- Best practices

**When to Reference**:
- Building new UI components
- Creating Inertia pages
- Implementing forms
- Styling with Tailwind
- Adding TypeScript types
- Optimizing performance

### 4. HMS Testing Guide (`hms-testing.md`)
**Inclusion**: Manual (use `#hms-testing` in chat)

Comprehensive testing guide using Pest v4.

**Topics Covered**:
- Testing philosophy
- Test organization
- Writing feature tests
- Writing unit tests
- Browser testing with Pest v4
- Using factories
- Testing authorization
- Testing validation
- Testing workflows
- Testing event-driven billing
- Testing insurance coverage
- Best practices
- Running tests
- CI/CD integration
- Debugging tests

**When to Reference**:
- Writing tests for new features
- Testing complex workflows
- Setting up browser tests
- Creating factories
- Debugging test failures
- Setting up CI/CD

### 5. Laravel Boost Guidelines (`laravel-boost.md`)
**Inclusion**: Always

Laravel-specific guidelines and best practices from Laravel maintainers.

**Topics Covered**:
- Laravel 12 conventions
- Inertia.js v2 patterns
- Pest v4 testing
- Laravel Pint formatting
- Database patterns
- Authorization with policies
- Form handling
- Validation
- Events and listeners
- Queue jobs

**When to Reference**:
- Following Laravel conventions
- Using Laravel features
- Writing tests
- Formatting code
- Working with Inertia

## How to Use Steering Documents

### Always Included

These documents are automatically included in every AI conversation:
- `hms-architecture.md` - Core architecture and patterns
- `laravel-boost.md` - Laravel conventions
- `README.md` (this file) - Index and overview

### Manually Included

These documents are included only when explicitly referenced:
- `hms-insurance.md` - Include with `#hms-insurance`
- `hms-frontend.md` - Include with `#hms-frontend`
- `hms-testing.md` - Include with `#hms-testing`

### Example Usage

```
# Include insurance documentation
"I need to implement coverage rules for a new insurance plan #hms-insurance"

# Include frontend documentation
"Create a new patient registration form #hms-frontend"

# Include testing documentation
"Write tests for the consultation workflow #hms-testing"

# Include multiple documents
"Build and test the claims vetting UI #hms-insurance #hms-frontend #hms-testing"
```

## Quick Reference

### Common Commands

```bash
# Run tests
php artisan test
php artisan test --filter=ConsultationTest

# Format code
vendor/bin/pint

# Create models
php artisan make:model PatientAdmission -mf

# Create controllers
php artisan make:controller Patient/PatientController

# Create policies
php artisan make:policy ConsultationPolicy --model=Consultation

# Create Form Requests
php artisan make:request StoreConsultationRequest

# Create events and listeners
php artisan make:event PrescriptionCreated
php artisan make:listener CreateMedicationCharge --event=PrescriptionCreated
```

### Key Patterns

#### Authorization
```php
// In Controller
$this->authorize('viewAny', PatientCheckin::class);

// In Policy
public function viewAny(User $user): bool
{
    return $user->can('checkins.view-all');
}

// Query Scope
$consultations = Consultation::accessibleTo($user)->get();
```

#### Event-Driven Billing
```php
// Dispatch event
event(new PrescriptionCreated($prescription));

// Listener creates charge
class CreateMedicationCharge
{
    public function handle(PrescriptionCreated $event)
    {
        Charge::create([...]);
    }
}
```

#### Inertia Forms
```tsx
// Multi-field form
<Form action="/patients" method="post">
    {({ errors, processing }) => (
        <>
            <Input name="first_name" />
            {errors.first_name && <span>{errors.first_name}</span>}
            <Button disabled={processing}>Submit</Button>
        </>
    )}
</Form>

// Simple action
router.post('/consultation/start', {
    patient_checkin_id: checkin.id
});
```

#### Testing
```php
it('allows receptionist to check in patient', function () {
    // Arrange
    $receptionist = User::factory()->receptionist()->create();
    $patient = Patient::factory()->create();
    
    // Act
    $response = $this->actingAs($receptionist)
        ->post('/checkin', ['patient_id' => $patient->id]);
    
    // Assert
    $response->assertRedirect();
    expect(PatientCheckin::count())->toBe(1);
});
```

## Development Workflow

### 1. Planning
- Review relevant steering docs
- Understand existing patterns
- Check for similar implementations

### 2. Implementation
- Follow architectural patterns
- Use service layer for complex logic
- Implement authorization with policies
- Create Form Requests for validation
- Dispatch events for billing

### 3. Frontend
- Create Inertia pages
- Build reusable components
- Use Shadcn/ui components
- Style with Tailwind CSS
- Add TypeScript types

### 4. Testing
- Write feature tests for workflows
- Write unit tests for services
- Test authorization
- Test validation
- Test event listeners

### 5. Quality Assurance
- Run tests: `php artisan test`
- Format code: `vendor/bin/pint`
- Check diagnostics
- Review code against patterns

### 6. Documentation
- Update steering docs if patterns change
- Document complex business logic
- Add inline comments for clarity

## Module-Specific Guidelines

### Patient Management
- Always use auto-generated patient numbers
- Validate phone numbers and national IDs
- Track patient status (active, inactive, deceased)
- Maintain medical history fields

### Check-in & OPD
- Enforce vitals before consultation
- One active check-in per patient per day
- Department-based queue management
- Status progression tracking

### Consultation
- Use SOAP notes format
- Department-based collaborative access
- Multiple diagnosis support
- Automatic billing via events

### Ward Management
- Doctor selects ward, nurse assigns bed
- Automatic bed count management
- Ward rounds with SOAP documentation
- Medication administration scheduling

### Pharmacy
- FIFO dispensing by expiry date
- Batch tracking for all medications
- Automatic charge creation
- Stock level monitoring

### Laboratory
- Configurable test parameters
- Dynamic result entry forms
- Priority-based ordering
- Sample tracking

### Billing
- Event-driven charge creation
- Multiple payment methods
- Partial payments supported
- Service-specific rules

### Insurance
- Category defaults + item exceptions
- Tariff-based pricing
- Claims vetting workflow
- Batch submission support

## Critical Business Rules

1. **Check-in**: Vitals required before consultation
2. **Consultation**: Department-based access for all doctors
3. **Ward**: Bed count auto-decrements on admission
4. **Pharmacy**: Stock must be available before dispensing
5. **Insurance**: Coverage determined by rules hierarchy
6. **Billing**: Charges created automatically via events

## Security Checklist

- [ ] Authorization via policies
- [ ] Validation via Form Requests
- [ ] Query scopes for data filtering
- [ ] No sensitive data in logs
- [ ] Audit trail for critical operations
- [ ] CSRF protection on forms
- [ ] SQL injection prevention (use Eloquent)
- [ ] XSS prevention (escape output)

## Performance Checklist

- [ ] Eager load relationships
- [ ] Use database indexes
- [ ] Cache configuration data
- [ ] Optimize N+1 queries
- [ ] Use query scopes efficiently
- [ ] Lazy load heavy components
- [ ] Memoize expensive calculations

## Accessibility Checklist

- [ ] Semantic HTML elements
- [ ] ARIA labels for screen readers
- [ ] Keyboard navigation support
- [ ] Sufficient color contrast
- [ ] Focus indicators visible
- [ ] Form labels associated
- [ ] Error messages clear

## Code Review Checklist

- [ ] Follows architectural patterns
- [ ] Uses policies for authorization
- [ ] Uses Form Requests for validation
- [ ] Events dispatched for billing
- [ ] Query scopes used for filtering
- [ ] Eager loading prevents N+1
- [ ] Tests cover happy and failure paths
- [ ] Inertia responses (not JSON)
- [ ] Code formatted with Pint
- [ ] TypeScript types defined
- [ ] Accessible UI components

## Getting Help

### Documentation Resources
- Laravel 12: https://laravel.com/docs/12.x
- Inertia.js v2: https://inertiajs.com
- React 19: https://react.dev
- Tailwind CSS v4: https://tailwindcss.com
- Shadcn/ui: https://ui.shadcn.com
- Pest v4: https://pestphp.com

### Internal Resources
- Check existing implementations
- Review similar features
- Consult steering documents
- Ask team members

### When Stuck
1. Review relevant steering doc
2. Check existing similar code
3. Search Laravel/Inertia docs
4. Test in isolation
5. Ask for help

---

## Handling Specs That Change Existing Patterns

When you have a spec that plans to change existing documented patterns:

1. **Add a Warning Note** - Add a note at the top of the affected steering doc indicating planned changes
2. **Reference the Spec** - Link to the spec file for details
3. **Document Current State** - Keep documenting the current implementation until changes are made
4. **Update After Implementation** - Once the spec is implemented, update the steering doc to reflect new patterns
5. **Remove Warning** - Remove the warning note after documentation is updated

### Example

```markdown
> **⚠️ NOTE**: This document describes the **current implementation**. 
> A simplification initiative is planned (see `.kiro/specs/insurance-ux-simplification/`) 
> that will change these patterns. Until implemented, follow the patterns below.
```

This approach ensures:
- Current implementation is documented accurately
- Future changes are clearly signaled
- No confusion about which patterns to follow
- Smooth transition when changes are implemented

---

**Remember**: These steering documents are living documents. Update them when patterns change or new best practices emerge. Consistency is key to maintainability.
