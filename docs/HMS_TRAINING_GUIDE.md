# HMS Training Guide

A comprehensive guide to all features in the Hospital Management System, organized by menu sections.

---

## 1. Dashboard

**Location:** Main landing page after login

### Features:
- **Overview Statistics**: Quick view of today's key metrics
  - Total patients checked in today
  - Pending consultations
  - Lab orders awaiting results
  - Pending prescriptions to dispense
  - Revenue collected today

- **Role-Based Widgets**: Different dashboards based on user role
  - **Receptionist**: Check-in queue, patient search
  - **Doctor**: Consultation queue, pending patients
  - **Nurse**: Vitals queue, medication administration due
  - **Pharmacist**: Dispensing queue, low stock alerts
  - **Lab Technician**: Pending lab orders, samples to collect
  - **Billing Officer**: Pending payments, daily collections

- **Quick Actions**: Fast access to common tasks
  - New patient registration
  - Quick check-in
  - Search patient

---

## 2. Check-in

**Location:** Sidebar → Check-in

### Purpose:
Manages the patient check-in process for outpatient visits.

### Features:

#### 2.1 Patient Check-in
- **Search Patient**: Find existing patients by name, phone, or patient number
- **Register New Patient**: Quick registration for new patients
- **Select Department**: Choose which department the patient is visiting
- **Insurance Selection**: Select patient's active insurance (if applicable)
- **Generate Queue Number**: Automatic queue assignment

#### 2.2 Today's Queue
- **View All Check-ins**: List of all patients checked in today
- **Filter by Department**: View queue for specific departments
- **Filter by Status**: 
  - `Checked In` - Waiting for vitals
  - `Vitals Taken` - Ready for consultation
  - `Awaiting Consultation` - In queue for doctor
  - `In Consultation` - Currently with doctor
  - `Completed` - Visit finished
  - `Admitted` - Transferred to ward

#### 2.3 Vitals Recording
- **Record Vitals**: Enter patient vital signs
  - Blood Pressure (Systolic/Diastolic)
  - Pulse Rate
  - Temperature
  - Respiratory Rate
  - Weight
  - Height
  - BMI (auto-calculated)
  - Oxygen Saturation (SpO2)
- **Vitals History**: View previous vitals for comparison

---

## 3. Patients

**Location:** Sidebar → Patients

### Purpose:
Central patient registry and medical records management.

### Features:

#### 3.1 Patient List
- **Search & Filter**: Find patients by multiple criteria
  - Patient number
  - Name
  - Phone number
  - National ID
- **Pagination**: Navigate through large patient lists
- **Quick Actions**: View, edit, or check-in patient

#### 3.2 Patient Registration
- **Demographics**:
  - First name, Last name, Other names
  - Date of birth
  - Gender
  - Phone number
  - Email (optional)
  - National ID
  - Address
- **Emergency Contact**: Name and phone number
- **Auto-Generated Patient Number**: Format: PAT2025000001

#### 3.3 Patient Profile
- **Personal Information**: View/edit demographics
- **Insurance Information**: Active insurance policies
- **Visit History**: All previous visits with dates
- **Medical History**: 
  - Past diagnoses
  - Allergies
  - Chronic conditions
- **Billing Summary**: Outstanding balances, payment history

---

## 4. Consultation

**Location:** Sidebar → Consultation

### Purpose:
Doctor's workspace for patient consultations and clinical documentation.

### Features:

#### 4.1 Consultation Queue
- **Pending Patients**: List of patients waiting for consultation
- **Filter by Department**: View only your department's patients
- **Patient Details Preview**: Quick view of patient info and vitals
- **Start Consultation**: Begin documenting the visit

#### 4.2 Consultation Interface
- **Patient Summary**: Demographics, vitals, insurance status
- **SOAP Notes**:
  - **Subjective**: Patient's complaints and history
  - **Objective**: Examination findings
  - **Assessment**: Clinical assessment and diagnosis
  - **Plan**: Treatment plan

#### 4.3 Diagnosis Management
- **Search Diagnoses**: ICD-10 code search
- **Add Multiple Diagnoses**: Primary and secondary diagnoses
- **Custom Diagnoses**: Add diagnoses not in the system
- **Diagnosis History**: View patient's previous diagnoses

#### 4.4 Lab Orders
- **Order Lab Tests**: Search and select lab tests
- **Set Priority**: Normal, Urgent, STAT
- **Add Clinical Notes**: Instructions for lab technician
- **View Results**: See completed lab results

#### 4.5 Prescriptions
- **Drug Search**: Find drugs by name or code
- **Smart Prescription Input**: Natural language parsing
  - Example: "Paracetamol 500mg 1x3 for 5 days"
- **Prescription Details**:
  - Drug name and strength
  - Dosage instructions
  - Frequency (OD, BD, TDS, QID, etc.)
  - Duration
  - Quantity (auto-calculated)
- **Refill Prescriptions**: Copy from previous visits

#### 4.6 Procedures
- **Order Minor Procedures**: Request procedures during consultation
- **View Procedure History**: Past procedures performed

#### 4.7 Admission
- **Admit Patient**: Transfer to inpatient care
- **Select Ward**: Choose appropriate ward
- **Admission Reason**: Document reason for admission

---

## 5. Wards

**Location:** Sidebar → Wards

### Purpose:
Inpatient ward management, bed allocation, and patient care.

### Features:

#### 5.1 Ward List
- **View All Wards**: List of hospital wards
- **Ward Details**: Name, type, total beds, available beds
- **Occupancy Status**: Visual indicator of bed availability

#### 5.2 Ward Management
- **Create Ward**: Add new ward with bed count
- **Edit Ward**: Modify ward details
- **Bed Management**: Track individual bed status

#### 5.3 Ward View (Individual Ward)
- **Bed Grid**: Visual layout of all beds
- **Patient Assignment**: See which patient is in each bed
- **Bed Status**: Available, Occupied, Reserved, Maintenance
- **Assign Bed**: Place admitted patient in specific bed

#### 5.4 Admitted Patients
- **Patient List**: All patients in the ward
- **Patient Details**: Admission date, diagnosis, attending doctor
- **Quick Actions**: View patient, start ward round

#### 5.5 Ward Rounds
- **Create Ward Round**: Document daily rounds
- **SOAP Documentation**: Same format as consultation
- **Add Diagnoses**: Update patient diagnoses
- **Order Labs**: Request lab tests
- **Prescriptions**: Add or modify medications
- **Procedures**: Order ward procedures
- **Nursing Instructions**: Notes for nursing staff

#### 5.6 Patient Ward View
- **Admission Summary**: Reason, date, attending doctor
- **Vitals Chart**: Graphical vitals trends
- **Medication Administration Record (MAR)**:
  - Scheduled medications
  - Administration times
  - Given/Missed/Held status
- **Nursing Notes**: Shift-by-shift documentation
- **Ward Round History**: All rounds documented
- **Discharge**: Complete discharge process

---

## 6. Laboratory

**Location:** Sidebar → Laboratory

### Purpose:
Lab test management, sample collection, and result entry.

### Features:

#### 6.1 Lab Dashboard
- **Pending Orders**: Tests waiting to be processed
- **Filter by Priority**: Normal, Urgent, STAT
- **Filter by Status**: Pending, Sample Collected, In Progress, Completed
- **Search**: Find orders by patient name or order number

#### 6.2 Order Processing
- **View Order Details**: Patient info, tests ordered, clinical notes
- **Collect Sample**: Mark sample as collected
- **Enter Results**: Input test results
  - Numeric values with units
  - Text results
  - Normal range indicators
  - Abnormal flagging

#### 6.3 Result Entry
- **Dynamic Forms**: Based on test parameters
- **Reference Ranges**: Auto-display normal ranges
- **Abnormal Highlighting**: Flag out-of-range values
- **Add Comments**: Lab technician notes

#### 6.4 Lab Configuration
- **Test Catalog**: Manage available lab tests
- **Create Test**: Add new lab test
  - Test name and code
  - Category (Hematology, Chemistry, Microbiology, etc.)
  - Sample type
  - Price
  - Turn-around time
- **Test Parameters**: Configure result fields
  - Parameter name
  - Type (numeric, text, select)
  - Unit of measurement
  - Normal range (min/max)
- **Edit/Delete Tests**: Modify existing tests

---

## 7. Minor Procedures

**Location:** Sidebar → Minor Procedures

### Purpose:
Manage minor medical procedures performed outside the operating theatre.

### Features:

#### 7.1 Procedure Queue
- **Pending Procedures**: Patients waiting for procedures
- **Filter by Type**: Wound care, Suturing, Dressing, etc.
- **Start Procedure**: Begin documentation

#### 7.2 Procedure Documentation
- **Patient Information**: Demographics and vitals
- **Procedure Type**: Select from configured types
- **Diagnosis**: Associate diagnosis with procedure
- **Procedure Notes**: Document what was done
- **Supplies Used**: Record materials consumed
- **Outcome**: Document procedure outcome

#### 7.3 Procedure Configuration
- **Procedure Types**: Manage available procedure types
- **Create Type**: Add new procedure type
  - Name and description
  - Base price
  - Category
- **Edit/Delete Types**: Modify existing types

---

## 8. Pharmacy

**Location:** Sidebar → Pharmacy

### Purpose:
Drug inventory management and prescription dispensing.

### Features:

#### 8.1 Pharmacy Dashboard
- **Quick Stats**: Pending prescriptions, low stock alerts
- **Navigation**: Access to Inventory and Dispensing

#### 8.2 Inventory Management
- **Drug List**: All drugs in the system
- **Search Drugs**: Find by name, code, or category
- **Stock Levels**: Current quantity available
- **Add Drug**: Register new drug
  - Drug name and code
  - Generic name
  - Strength and form
  - Unit price
  - Reorder level
  - Bottle size (for liquids)

#### 8.3 Drug Batches
- **View Batches**: All batches for a drug
- **Add Batch**: Record new stock receipt
  - Batch number
  - Quantity received
  - Expiry date
  - Supplier
  - Cost price
- **Batch Tracking**: FIFO dispensing by expiry

#### 8.4 Low Stock Alerts
- **Below Reorder Level**: Drugs needing restock
- **Export List**: Generate reorder report

#### 8.5 Expiring Stock
- **Expiring Soon**: Drugs expiring within 90 days
- **Expired**: Already expired batches
- **Filter by Date Range**: Custom expiry window

#### 8.6 Dispensing
- **Prescription Queue**: Pending prescriptions to dispense
- **Filter Options**:
  - By status (Pending, Partially Dispensed)
  - By source (OPD, Ward)
  - By date
- **Search**: Find by patient name or prescription ID

#### 8.7 Dispense Prescription
- **Prescription Details**: Drug, dosage, quantity ordered
- **Available Stock**: Batches with quantities
- **Select Batch**: Choose which batch to dispense from
- **Quantity to Dispense**: Enter amount (partial allowed)
- **Print Label**: Generate medication label
- **Complete Dispensing**: Mark as dispensed

---

## 9. Billing

**Location:** Sidebar → Billing

### Purpose:
Financial management, payment collection, and revenue tracking.

### Features:

#### 9.1 Payments
- **Patient Search**: Find patient to collect payment
- **Outstanding Charges**: View unpaid items
- **Charge Details**:
  - Service type (Consultation, Lab, Medication, etc.)
  - Description
  - Amount
  - Insurance coverage (if applicable)
  - Patient responsibility
- **Collect Payment**:
  - Select charges to pay
  - Payment method (Cash, Card, Mobile Money, etc.)
  - Amount received
  - Change calculation
- **Print Receipt**: Generate payment receipt

#### 9.2 Dashboard
- **Daily Summary**: Today's collections by category
- **Payment Methods**: Breakdown by payment type
- **Outstanding Balances**: Total unpaid amounts
- **Trends**: Revenue charts

#### 9.3 Reconciliation
- **Daily Reconciliation**: Match collections to deposits
- **Create Reconciliation**: Record bank deposit
- **Variance Report**: Identify discrepancies
- **Approval Workflow**: Manager sign-off

#### 9.4 History
- **Payment History**: All payments received
- **Filter by Date**: Custom date ranges
- **Filter by Method**: Cash, Card, etc.
- **Void Payment**: Cancel erroneous payment
- **Refund**: Process refunds

#### 9.5 Reports
- **Outstanding Report**: All unpaid charges
- **Revenue Report**: Income by period
- **Export Options**: PDF, Excel

#### 9.6 Patient Accounts
- **Account List**: Patients with prepaid balances or credit
- **Account Details**: Transaction history
- **Deposit**: Add funds to patient account
- **Withdraw**: Use account balance for payment
- **Credit Management**: Manage credit limits

#### 9.7 Configuration
- **Payment Methods**: Configure accepted payment types
- **Service Prices**: Set prices for services
- **Department Billing**: Configure department-specific fees
- **Charge Rules**: Automatic charge generation rules

#### 9.8 Pricing Dashboard
- **Centralized Pricing**: View/edit all prices in one place
- **Bulk Edit**: Update multiple prices at once
- **Price History**: Track price changes
- **Categories**: Filter by service type

---

## 10. Insurance

**Location:** Sidebar → Insurance

### Purpose:
Insurance provider management, coverage rules, and claims processing.

### Features:

#### 10.1 Providers
- **Provider List**: All insurance companies
- **Add Provider**: Register new insurance company
  - Name and code
  - Contact information
  - NHIS status (for national insurance)
- **Edit Provider**: Update provider details

#### 10.2 Plans
- **Plan List**: Insurance plans by provider
- **Add Plan**: Create new insurance plan
  - Plan name and code
  - Provider association
  - Coverage percentages by category
  - Require explicit approval setting
- **Category Defaults**: Set default coverage for:
  - Consultations
  - Medications
  - Laboratory
  - Procedures
  - Diagnostics
  - Consumables

#### 10.3 Coverage Rules
- **View Coverage**: See all coverage rules for a plan
- **Category Defaults**: Base coverage percentages
- **Item Exceptions**: Override coverage for specific items
  - Higher or lower than category default
  - Custom tariff pricing
  - Copay amounts
- **Add Exception**: Create item-specific rule
- **Bulk Import**: Upload coverage rules from Excel

#### 10.4 Claims
- **Claims List**: All insurance claims
- **Filter by Status**:
  - Draft
  - Pending Vetting
  - Vetted
  - Submitted
  - Approved
  - Rejected
  - Paid
- **Create Claim**: Generate claim from patient visit
- **Claim Details**: View all claim items

#### 10.5 Claim Vetting
- **Review Items**: Check each claim item
- **Verify Coverage**: Confirm coverage rules applied correctly
- **Adjust Amounts**: Modify if needed
- **Add Notes**: Document vetting decisions
- **Approve/Reject Items**: Individual item decisions
- **Submit Claim**: Send to insurance

#### 10.6 Batches
- **Batch List**: Grouped claims for submission
- **Create Batch**: Group claims by provider/period
- **Batch Status**: Track submission status
- **Export Batch**: Generate submission file (XML for NHIS)

#### 10.7 Analytics
- **Claims Summary**: Total claims by status
- **Provider Analysis**: Claims by insurance company
- **Rejection Analysis**: Common rejection reasons
- **Revenue Tracking**: Expected vs received payments
- **Aging Report**: Outstanding claims by age

#### 10.8 NHIS Tariffs
- **Tariff List**: National Health Insurance tariffs
- **Search Tariffs**: Find by code or description
- **Import Tariffs**: Bulk upload from NHIS file
- **View Details**: Price and category information

#### 10.9 NHIS Mappings
- **Mapping List**: Link local items to NHIS codes
- **Create Mapping**: Associate drug/service with NHIS tariff
- **Bulk Mapping**: Import mappings from Excel
- **Unmapped Items**: Identify items needing mapping

#### 10.10 G-DRG Tariffs
- **G-DRG List**: Ghana Diagnosis Related Groups tariffs
- **Search**: Find by code or description
- **Import**: Bulk upload G-DRG data

---

## 11. Departments

**Location:** Sidebar → Departments

### Purpose:
Hospital department configuration and management.

### Features:

#### 11.1 Department List
- **View All**: List of hospital departments
- **Department Details**: Name, code, type
- **Staff Count**: Users assigned to department

#### 11.2 Department Management
- **Create Department**: Add new department
  - Name
  - Code
  - Type (OPD, Ward, Lab, Pharmacy, etc.)
  - Description
- **Edit Department**: Modify details
- **Delete Department**: Remove unused departments

#### 11.3 Department Billing
- **Consultation Fee**: Set department consultation price
- **Service Fees**: Configure department-specific charges

---

## 12. Backups

**Location:** Sidebar → Backups (Admin only)

### Purpose:
Database backup and recovery management.

### Features:

#### 12.1 Backup List
- **View Backups**: All system backups
- **Backup Details**: Date, size, type, status
- **Download**: Get backup file

#### 12.2 Create Backup
- **Manual Backup**: Trigger immediate backup
- **Backup Type**: Full or incremental
- **Storage Location**: Local or cloud

#### 12.3 Restore
- **Select Backup**: Choose backup to restore
- **Confirmation**: Verify before restore
- **Restore Progress**: Track restoration status

#### 12.4 Settings
- **Schedule**: Configure automatic backups
- **Retention**: How long to keep backups
- **Storage**: Configure backup destination
- **Notifications**: Alert on backup success/failure

---

## 13. Administration

**Location:** Sidebar → Administration (Admin only)

### Purpose:
System administration and user management.

### Features:

#### 13.1 Users
- **User List**: All system users
- **Search Users**: Find by name or email
- **Create User**: Add new user
  - Name and email
  - Password
  - Role assignment
  - Department assignment
  - Status (Active/Inactive)
- **Edit User**: Modify user details
- **Reset Password**: Force password change
- **Deactivate User**: Disable account

#### 13.2 Roles
- **Role List**: All system roles
- **Create Role**: Define new role
  - Role name
  - Description
  - Permission assignment
- **Edit Role**: Modify permissions
- **Delete Role**: Remove unused roles

#### 13.3 Permissions
- **Permission Categories**:
  - Patients (view, create, edit, delete)
  - Check-in (view, create)
  - Consultation (view own, view department, view all, create)
  - Wards (view, manage)
  - Laboratory (view, process, configure)
  - Pharmacy (view, dispense, manage inventory)
  - Billing (view, collect, reconcile, configure)
  - Insurance (view, manage, vet claims)
  - Administration (users, roles, settings)

#### 13.4 Theme Settings
- **Color Scheme**: Primary and accent colors
- **Logo**: Upload custom logo
- **Facility Name**: Display name in header
- **Dark Mode**: Enable/disable dark theme

#### 13.5 NHIS Settings
- **Facility Code**: NHIS facility identifier
- **API Credentials**: NHIS integration settings
- **Submission Settings**: Claim submission configuration

---

## 14. Settings (User Profile)

**Location:** User menu → Settings

### Purpose:
Personal user settings and preferences.

### Features:

#### 14.1 Profile
- **Personal Information**: Name, email
- **Contact Details**: Phone number
- **Profile Picture**: Upload avatar

#### 14.2 Password
- **Change Password**: Update login password
- **Password Requirements**: Minimum length, complexity

#### 14.3 Two-Factor Authentication
- **Enable 2FA**: Add extra security
- **Setup**: Scan QR code with authenticator app
- **Recovery Codes**: Backup codes for account recovery

#### 14.4 Appearance
- **Theme**: Light or dark mode
- **Language**: Interface language (if available)

#### 14.5 Vitals Alerts
- **Configure Alerts**: Set thresholds for vital sign alerts
- **Sound Notifications**: Enable/disable alert sounds

---

## Quick Reference: Common Workflows

### New Patient Visit (OPD)
1. **Check-in** → Register/search patient → Select department
2. **Vitals** → Record vital signs
3. **Consultation** → Doctor sees patient → SOAP notes
4. **Diagnosis** → Add diagnoses
5. **Lab Orders** → Order tests (if needed)
6. **Prescriptions** → Write prescriptions
7. **Billing** → Collect payment
8. **Pharmacy** → Dispense medications

### Patient Admission
1. **Consultation** → Doctor decides to admit
2. **Admit** → Select ward → Document reason
3. **Bed Assignment** → Nurse assigns bed
4. **Ward Rounds** → Daily documentation
5. **MAR** → Medication administration
6. **Discharge** → Complete discharge process

### Insurance Claim Processing
1. **Patient Visit** → Services rendered
2. **Charges Created** → Automatic via events
3. **Claim Generated** → From patient visit
4. **Vetting** → Review and verify items
5. **Submission** → Send to insurance
6. **Follow-up** → Track approval/payment

### Lab Test Processing
1. **Order Received** → From consultation/ward round
2. **Sample Collection** → Mark sample collected
3. **Processing** → Run tests
4. **Result Entry** → Enter values
5. **Completion** → Results available to doctor

---

## Tips for Trainers

1. **Start with Check-in**: It's the entry point for most workflows
2. **Use Test Patients**: Create dummy patients for training
3. **Follow Complete Workflows**: Don't skip steps
4. **Highlight Keyboard Shortcuts**: Many forms support quick entry
5. **Explain Insurance Early**: It affects many other modules
6. **Practice Error Scenarios**: Show what happens with invalid data
7. **Demonstrate Reports**: Show how data flows to reports

---

*Document Version: 1.0*
*Last Updated: December 2025*
