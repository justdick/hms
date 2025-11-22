# Minor Procedures Module - Implementation Tasks

## Task Breakdown

### Phase 1: Database & Models ✅

#### Task 1.1: Create Minor Procedures Department ✅
**Estimate**: 5 minutes

- [x] Create seeder for Minor Procedures department
- [x] Add department: name="Minor Procedures", code="MINPROC"
- [x] Create department billing configuration (requires_vitals=false)
- [x] Run seeder

**Note**: Department `consultation_fee` is charged once at check-in (like other departments). Individual procedure prices are additional charges per procedure performed.

**Files**:
- `database/seeders/MinorProceduresDepartmentSeeder.php`

**Acceptance**:
- Department exists in database
- Billing configuration set
- Can select during check-in

---

#### Task 1.2: Create Minor Procedures Migrations ✅
**Estimate**: 10 minutes

- [x] Create migration for `minor_procedures` table
- [x] Add columns: id, patient_checkin_id, nurse_id, minor_procedure_type_id, procedure_notes, performed_at, status, timestamps
- [x] Add foreign keys and indexes
- [x] Create migration for `minor_procedure_diagnoses` pivot table
- [x] Create migration for `minor_procedure_supplies` table
- [x] Create migration for `minor_procedure_types` table (procedure catalog with individual pricing)
- [x] Create seeder for procedure types with standard procedures
- [x] Run migrations

**Note**: Uses `minor_procedure_type_id` FK. Pricing model: consultation_fee charged at check-in + optional procedure-specific charges.

**Files**:
- `database/migrations/2025_11_21_123201_create_minor_procedures_table.php`
- `database/migrations/2025_11_21_123208_create_minor_procedure_diagnoses_table.php`
- `database/migrations/2025_11_21_123215_create_minor_procedure_supplies_table.php`
- `database/migrations/2025_11_21_175413_create_minor_procedure_types_table.php`
- `database/seeders/MinorProcedureTypesSeeder.php`

**Acceptance**:
- Tables created successfully
- Foreign keys and indexes in place
- Procedure types seeded with prices
- No migration errors

---

#### Task 1.3: Create MinorProcedure & MinorProcedureType Models ✅
**Estimate**: 10 minutes

- [x] Create MinorProcedureType model (procedure catalog)
- [x] Define fillable fields for procedure type
- [x] Add casts for procedure type (price as decimal)
- [x] Create MinorProcedure model with factory
- [x] Define fillable fields
- [x] Add casts (performed_at as datetime, status as enum)
- [x] Define relationships: belongsTo PatientCheckin, belongsTo User (nurse), belongsTo MinorProcedureType
- [x] Define relationships: belongsToMany Diagnosis, hasMany MinorProcedureSupply
- [x] Create factory with realistic data

**Files**:
- `app/Models/MinorProcedureType.php`
- `app/Models/MinorProcedure.php`
- `database/factories/MinorProcedureFactory.php`

**Acceptance**:
- Models created with all relationships
- Factory generates valid test data
- Can create model via factory in tinker

---

#### Task 1.4: Create MinorProcedureSupply Model ✅
**Estimate**: 5 minutes

- [x] Create MinorProcedureSupply model with factory
- [x] Define fillable fields
- [x] Add casts (dispensed as boolean, dispensed_at as datetime)
- [x] Define relationships: belongsTo MinorProcedure, belongsTo Drug, belongsTo User (dispenser)
- [x] Create factory

**Files**:
- `app/Models/MinorProcedureSupply.php`
- `database/factories/MinorProcedureSupplyFactory.php`

**Acceptance**:
- Model created with relationships
- Factory works correctly

---

### Phase 2: Authorization ✅

#### Task 2.1: Create Permissions ✅
**Estimate**: 5 minutes

- [x] Create seeder for minor procedures permissions
- [x] Add permissions:
  - `minor-procedures.view-dept` - View department queue
  - `minor-procedures.perform` - Perform procedures
  - `minor-procedures.view-all` - View all procedures (admin)
- [x] Assign to appropriate roles (nurses, admin)
- [x] Run seeder

**Files**:
- `database/seeders/PermissionSeeder.php` (updated)

**Acceptance**:
- Permissions exist in database
- Assigned to correct roles

---

#### Task 2.2: Create MinorProcedurePolicy ✅
**Estimate**: 10 minutes

- [x] Create MinorProcedurePolicy
- [x] Implement `viewAny()` - check `minor-procedures.view-dept` or `view-all`
- [x] Implement `create()` - check `minor-procedures.perform`
- [x] Implement `view()` - check department access or `view-all`
- [x] Implement `update()` - prevent updates to completed procedures
- [x] Register policy in AuthServiceProvider

**Files**:
- `app/Policies/MinorProcedurePolicy.php`
- `tests/Feature/MinorProcedure/MinorProcedurePolicyTest.php`
- `tests/Feature/MinorProcedure/PermissionsTest.php`

**Acceptance**:
- Policy methods return correct authorization
- Tests pass for authorization checks

---

### Phase 3: Events & Listeners ✅

#### Task 3.1: Create MinorProcedurePerformed Event ✅
**Estimate**: 5 minutes

- [x] Create MinorProcedurePerformed event
- [x] Add public MinorProcedure property
- [x] Implement ShouldBroadcast if real-time updates needed

**Files**:
- `app/Events/MinorProcedurePerformed.php`

**Acceptance**:
- Event created with procedure property
- Can be dispatched

---

#### Task 3.2: Create CreateMinorProcedureCharge Listener ✅
**Estimate**: 15 minutes

- [x] Create CreateMinorProcedureCharge listener
- [x] Listen to MinorProcedurePerformed event
- [x] Get procedure fee from MinorProcedureType (additional charge if set)
- [x] Create Charge record with:
  - patient_checkin_id
  - service_type = 'minor_procedure'
  - service_code = procedure_type_id
  - description = procedure type name
  - amount = procedure fee
  - status = 'pending'
- [x] Register listener in EventServiceProvider

**Files**:
- `app/Listeners/CreateMinorProcedureCharge.php`
- `tests/Feature/MinorProcedure/MinorProcedureChargeTest.php`

**Acceptance**:
- Charge created when event dispatched
- Correct amount from procedure type
- Linked to check-in
- Tests passing (4 tests, 14 assertions)

---

#### Task 3.3: Handle Supply Charges ✅
**Estimate**: 10 minutes

- [x] Update existing dispensing logic to handle MinorProcedureSupply
- [x] When supply dispensed, create charge
- [x] Link charge to patient_checkin_id from procedure
- [x] Update supply record: dispensed=true, dispensed_at, dispensed_by
- [x] Create PharmacyBillingService for supply charge calculations

**Files**:
- `app/Services/DispensingService.php`
- `app/Services/PharmacyBillingService.php`
- `tests/Feature/MinorProcedure/SupplyDispensingTest.php`

**Acceptance**:
- Supply charges created on dispensing
- Supply records updated correctly
- Tests passing (4 tests, 17 assertions)

---

### Phase 4: Backend - Controllers & Requests ✅

#### Task 4.1: Create MinorProcedureController ✅
**Estimate**: 20 minutes

- [x] Create MinorProcedureController in `app/Http/Controllers/MinorProcedure/`
- [x] Implement `index()` - show page with queue count
  - Count patients in Minor Procedures department (not completed)
  - Pass count to frontend
  - Authorize with policy
- [x] Implement `search()` - search patients in queue
  - Filter by Minor Procedures department
  - Filter by status (not completed)
  - Search by name, patient_number, phone_number
  - Eager load patient, vitalSigns
  - Return JSON results
- [x] Implement `store()` - create procedure
  - Validate request
  - Create MinorProcedure
  - Attach diagnoses
  - Create supply requests
  - Dispatch event
  - Update check-in status to 'completed'
  - Return redirect with success message
- [x] Implement `show()` - view procedure details (optional)

**Files**:
- `app/Http/Controllers/MinorProcedure/MinorProcedureController.php`
- `tests/Feature/MinorProcedure/MinorProcedureControllerTest.php`

**Acceptance**:
- Queue count accurate ✅
- Search returns only queued patients ✅
- Procedure creation works end-to-end ✅
- Authorization enforced ✅
- All tests passing (23 tests, 53 assertions) ✅

---

#### Task 4.2: Create Form Request ✅
**Estimate**: 10 minutes

- [x] Create StoreMinorProcedureRequest
- [x] Add validation rules:
  - patient_checkin_id: required, exists
  - minor_procedure_type_id: required, exists
  - procedure_notes: required, string, min:10
  - diagnoses: nullable, array
  - diagnoses.*: exists:diagnoses,id
  - supplies: nullable, array
  - supplies.*.drug_id: required, exists:drugs,id
  - supplies.*.quantity: required, numeric, min:0.01
- [x] Add custom error messages

**Files**:
- `app/Http/Requests/StoreMinorProcedureRequest.php`

**Acceptance**:
- Validation rules work correctly ✅
- Error messages clear ✅
- Tested via controller tests ✅

---

#### Task 4.3: Add Routes ✅
**Estimate**: 5 minutes

- [x] Add routes in `routes/minor-procedures.php`
- [x] Group under `/minor-procedures` prefix
- [x] Add middleware: auth, verified
- [x] Routes:
  - GET `/minor-procedures` - index (show page with count)
  - GET `/minor-procedures/search` - search (search patients in queue)
  - POST `/minor-procedures` - store (create procedure)
  - GET `/minor-procedures/{id}` - show (optional)

**Files**:
- `routes/minor-procedures.php`
- `routes/web.php` (includes minor-procedures routes)

**Acceptance**:
- Routes registered ✅
- Accessible with authentication ✅
- All routes tested ✅

---

### Phase 5: Frontend - Pages & Components ✅

#### Task 5.1: Create Minor Procedures Index Page ✅
**Estimate**: 30 minutes

- [x] Create `resources/js/pages/MinorProcedure/Index.tsx`
- [x] Display queue count prominently (e.g., "3 patients waiting")
- [x] Add patient search input
- [x] Implement search with debounce (search as user types)
- [x] Display search results with patient info: name, number, check-in time, vitals status
- [x] Add "Select" button for each search result
- [x] Open procedure form modal/slide-over on select
- [x] Show message if no patients in queue
- [x] Show "No results" if search returns nothing

**Files**:
- `resources/js/pages/MinorProcedure/Index.tsx`

**Acceptance**:
- Queue count displays correctly ✅
- Search works and filters queue only ✅
- Can select patient to start procedure ✅
- Responsive design ✅

---

#### Task 5.2: Create Procedure Form Component ✅
**Estimate**: 45 minutes

- [x] Create `resources/js/components/MinorProcedure/ProcedureForm.tsx`
- [x] Use Inertia `<Form>` component
- [x] Add procedure type dropdown (predefined types)
- [x] Add diagnosis search (integrated into form)
- [x] Add procedure notes textarea
- [x] Add supplies search and selection
- [x] Show selected supplies with quantity inputs
- [x] Handle form submission
- [x] Show validation errors
- [x] Disable submit while processing
- [x] Show success message on completion

**Files**:
- `resources/js/components/MinorProcedure/ProcedureForm.tsx`

**Acceptance**:
- Form works end-to-end ✅
- Validation errors displayed ✅
- Success feedback shown ✅

**Note**: Supply search functionality was integrated directly into the ProcedureForm component using the same pattern as diagnosis search, making Task 5.3 redundant.

---

#### Task 5.3: Create Supply Search Component ✅
**Estimate**: 20 minutes

- [x] Integrated supply search directly into ProcedureForm
- [x] Reuse drug search logic with Command component
- [x] Show drug details (form, strength, price)
- [x] Add quantity input
- [x] Support multiple supplies
- [x] Remove supply button

**Files**:
- `resources/js/components/MinorProcedure/ProcedureForm.tsx` (integrated)

**Acceptance**:
- Can search and add supplies ✅
- Drug details shown ✅
- Quantities editable ✅

---

#### Task 5.4: Add Navigation Menu Item ✅
**Estimate**: 5 minutes

- [x] Add "Minor Procedures" to navigation menu
- [x] Add appropriate icon (Bandage icon)
- [x] Links to /minor-procedures route
- [x] Positioned between Laboratory and Pharmacy

**Files**:
- `resources/js/components/app-sidebar.tsx`

**Acceptance**:
- Menu item visible to all users ✅
- Links to correct page ✅
- Uses appropriate icon ✅

---

### Phase 6: Pharmacy Integration

#### Task 6.1: Update Pharmacy Dispensing View ✅
**Estimate**: 15 minutes

- [x] Update pharmacy dispensing page to show minor procedure supplies
- [x] Add filter/tab for "Minor Procedure Supplies"
- [x] Show procedure type and patient info
- [x] Allow dispensing with same workflow as prescriptions
- [x] Update supply record on dispensing

**Files**:
- `app/Http/Controllers/Pharmacy/DispensingController.php`
- `app/Models/PatientCheckin.php`
- `routes/pharmacy.php`
- `resources/js/pages/Pharmacy/Dispensing/Index.tsx`

**Acceptance**:
- Supplies appear in pharmacy queue ✅
- Can be dispensed ✅
- Charges created correctly ✅

---

### Phase 7: Patient History Integration

#### Task 7.1: Add Procedures to Patient History ✅
**Estimate**: 15 minutes

- [x] Update patient history page/component
- [x] Add "Minor Procedures" section
- [x] Show procedure type, date, nurse, notes
- [x] Show supplies used
- [x] Link to full procedure details (optional)

**Files**:
- `app/Http/Controllers/Consultation/ConsultationController.php`
- `resources/js/components/Consultation/PatientHistorySidebar.tsx`

**Acceptance**:
- Procedures visible in patient history ✅
- Information complete and accurate ✅

---

### Phase 8: Testing ✅

#### Task 8.1: Write Model Tests ✅
**Estimate**: 15 minutes

- [x] Test MinorProcedure relationships
- [x] Test MinorProcedureSupply relationships
- [x] Test factory creates valid models

**Files**:
- `tests/Unit/Models/MinorProcedureTest.php`
- `tests/Unit/Models/MinorProcedureSupplyTest.php`

**Acceptance**:
- All relationship tests pass ✅
- Factories work correctly ✅
- 18 tests passing with 45 assertions ✅

---

#### Task 8.2: Write Authorization Tests ✅
**Estimate**: 20 minutes

- [x] Test policy methods
- [x] Test viewAny with different permissions
- [x] Test create permission
- [x] Test department-based access
- [x] Test admin access to all

**Files**:
- `tests/Feature/MinorProcedure/MinorProcedurePolicyTest.php` (already existed)
- `tests/Feature/MinorProcedure/PermissionsTest.php` (already existed)

**Acceptance**:
- All authorization tests pass ✅
- Correct access control enforced ✅
- 32 tests passing ✅

---

#### Task 8.3: Write Procedure Workflow Tests ✅
**Estimate**: 30 minutes

- [x] Test complete procedure creation workflow
- [x] Test with diagnoses
- [x] Test with supplies
- [x] Test charge creation via event
- [x] Test check-in status update
- [x] Test validation errors
- [x] Test unauthorized access

**Files**:
- `tests/Feature/MinorProcedure/ProcedureWorkflowTest.php`

**Acceptance**:
- All workflow tests pass ✅
- Edge cases covered ✅
- 18 tests passing with 48 assertions ✅

---

#### Task 8.4: Write Supply Dispensing Tests ✅
**Estimate**: 15 minutes

- [x] Test supply request creation
- [x] Test pharmacy dispensing of supplies
- [x] Test charge creation on dispensing
- [x] Test supply record updates

**Files**:
- `tests/Feature/MinorProcedure/SupplyDispensingTest.php` (already existed)

**Acceptance**:
- Supply workflow tests pass ✅
- Charges created correctly ✅
- 4 tests passing with 17 assertions ✅

**Phase 8 Summary**: All 99 tests passing with 217 assertions across all minor procedure test files.

---

### Phase 9: Documentation & Polish ✅

#### Task 9.1: Update Steering Documentation ✅
**Estimate**: 10 minutes

- [x] Add Minor Procedures to HMS architecture doc
- [x] Document workflow and patterns
- [x] Add to module list
- [x] Document permissions

**Files**:
- `.kiro/steering/hms-architecture.md`

**Acceptance**:
- Documentation updated ✅
- Patterns documented ✅
- Added to core modules list ✅
- Event-driven billing documented ✅
- Controller structure documented ✅
- Service layer documented ✅
- Permission naming documented ✅
- Frontend component organization documented ✅
- Business rules documented ✅

---

#### Task 9.2: Code Formatting & Cleanup ✅
**Estimate**: 5 minutes

- [x] Run `vendor/bin/pint` to format PHP code
- [x] Run Prettier on frontend files
- [x] Remove any debug code
- [x] Check for console.log statements

**Acceptance**:
- Code properly formatted ✅
- No debug code remaining ✅
- All 99 PHP files formatted with Pint ✅
- All frontend files formatted with Prettier ✅

---

#### Task 9.3: Final Testing ✅
**Estimate**: 15 minutes

- [x] Run full test suite: `php artisan test`
- [x] Test manually in browser
- [x] Test complete workflow end-to-end
- [x] Test with different user roles
- [x] Verify billing charges created
- [x] Verify patient history updated

**Acceptance**:
- All tests pass ✅
- All 99 minor procedure tests passing (217 assertions) ✅
- Manual testing successful ✅
- No errors in browser console ✅

---

## Task Summary

### Total Estimated Time: ~5-6 hours

**Phase 1**: Database & Models - 30 minutes
**Phase 2**: Authorization - 15 minutes
**Phase 3**: Events & Listeners - 30 minutes
**Phase 4**: Backend - 35 minutes
**Phase 5**: Frontend - 100 minutes
**Phase 6**: Pharmacy Integration - 15 minutes
**Phase 7**: Patient History - 15 minutes
**Phase 8**: Testing - 80 minutes
**Phase 9**: Documentation - 30 minutes

### Dependencies

- Phase 2 depends on Phase 1 (models must exist)
- Phase 3 depends on Phase 1 (models must exist)
- Phase 4 depends on Phases 1, 2, 3
- Phase 5 depends on Phase 4 (routes must exist)
- Phase 6 depends on Phases 1, 3
- Phase 7 depends on Phase 1
- Phase 8 depends on all previous phases
- Phase 9 is final

### Recommended Order

1. Complete Phase 1 (Database & Models)
2. Complete Phase 2 (Authorization)
3. Complete Phase 3 (Events & Listeners)
4. Complete Phase 4 (Backend)
5. Complete Phase 5 (Frontend)
6. Complete Phase 6 (Pharmacy Integration)
7. Complete Phase 7 (Patient History)
8. Complete Phase 8 (Testing)
9. Complete Phase 9 (Documentation)

---

## Implementation Summary

### Completed Features

✅ **Database & Models** - Complete minor procedures data structure with procedure types, supplies, and diagnoses
✅ **Authorization** - Policy-based access control with department filtering
✅ **Event-Driven Billing** - Automatic charge creation for procedures and supplies
✅ **Backend Controllers** - Queue management, patient search, and procedure documentation
✅ **Frontend UI** - React components for procedure workflow with search and forms
✅ **Pharmacy Integration** - Supply dispensing workflow integrated with existing pharmacy
✅ **Patient History** - Procedures visible in patient history sidebar
✅ **Comprehensive Testing** - 99 tests with 217 assertions covering all workflows
✅ **Documentation** - Complete architectural documentation and patterns

### Test Results

- **Unit Tests**: 18 tests passing (45 assertions)
- **Feature Tests**: 81 tests passing (172 assertions)
- **Total**: 99 tests passing (217 assertions)
- **Code Quality**: All code formatted with Pint and Prettier

### Key Achievements

1. **Seamless Integration** - Works with existing HMS modules (check-in, pharmacy, billing, patient history)
2. **Event-Driven Architecture** - Follows established patterns for automatic billing
3. **Flexible Pricing** - Consultation fee at check-in + optional procedure-specific charges
4. **Supply Management** - Integrated with pharmacy for supply requests and dispensing
5. **Department-Based Access** - Consistent with other modules for authorization
6. **Comprehensive Testing** - Full test coverage for all workflows and edge cases

---

**Status**: ✅ Complete
**Created**: 2025-01-21
**Completed**: 2025-01-22
**Total Time**: ~6 hours (as estimated)
