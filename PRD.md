📑 Product Requirements Document (PRD)

Hospital Management System (HMS)
Tech Stack: Laravel 12 (backend), React 19 (frontend), Inertia.js (SPA)

1. Overview

This HMS is an in-house system for managing hospital operations: patient registration, consultations, billing, pharmacy, laboratory, inpatient (IPD) & outpatient (OPD) management, ward management, and administrative controls.

No mobile apps.

No online patient booking (walk-ins only).

File uploads stored on server (not S3).

Permissions managed via Spatie Roles & Permissions.

2. Functional Modules
2.1 Patient Registration

Auto-generated hospital number (unique ID).

Multi-visit history under one patient ID.

Capture demographic details, next of kin, insurance info.

OPD & IPD registration supported.

ICD-10/ICD-11 coding for diagnoses.

2.2 Appointments & Reception

Walk-in appointments: Same-day, anytime, join queue.

Future appointments: Booked at reception.

Patients checked into specific departments (General OPD, ENT, Eye, Dental, etc.).

Queue management per department.

Receptionists cannot handle payments.

2.3 Consultations

Doctors access patient history + vitals.

View consultation history (modal or side panel).

Create new consultation encounter:

Symptoms, diagnosis (ICD-10/11), notes.

Request labs.

Prescribe medications.

Decide admission to ward (if required).

Department restrictions:

Doctor must belong to patient’s department (unless admin grants override).

Warning if doctor consults outside assigned department.

Admin can assign multiple departments to doctors.

2.4 Vitals & Charting

Captured before consultation (for OPD) and periodically in wards (IPD).

Standard vitals: Temperature, BP, Pulse, Respiration, SpO₂, Weight, Height, BMI, Pain score.

Stored as time-series.

Graphical charts for trend visualization.

2.5 Laboratory Management

Doctors order lab tests during consultation.

Support lab categories (hematology, microbiology, biochemistry, etc.).

Lab staff record results:

Manual entry.

File upload (PDF, images).

Results linked to consultation & patient history.

Billing auto-updated when labs are taken out.

2.6 Pharmacy Management

Prescriptions from consultations flow to pharmacy.

Pharmacy dispenses drugs → updates inventory + billing.

Integration with drug inventory (stock, expiry, reorder alerts).

Supports retail & in-patient supply.

2.7 Consumables Management (Separate Module)

Non-drug items: syringes, gloves, IV sets, bandages, catheters, fluids, etc.

Pharmacy/Store: Master stock.

Restocking: Pharmacy issues consumables → wards/departments (Injection Room, Dressing Room, IPD wards).

Usage Logging: Nurses log consumables when performing procedures (linked to patient).

Billing: Auto-applied to patient’s bill on usage.

Reports: Issued vs used, wastage, consumption per department.

2.8 Billing & Revenue

Itemized Billing:

Each service (consultation, registration, lab, drug, consumable, ward charges, nursing procedures) generates a separate billing item.

Status: Pending, Paid, Exempted.

Payment Handling at Cashier/Revenue Point:

Full Payment: Patient settles all pending items at once.

Selective Payment: Cashier can select specific bill items to be settled (e.g., lab test fee before performing lab).

Partial Payment: Cashier can accept a partial amount against the total bill. Remaining balance stays due.

Advance Deposit:

Patient can deposit funds upfront.

Future services auto-deduct from the deposit until exhausted.

Exemptions: Items can be marked cleared without payment (admin approval required). Logged for auditing.

Service Blocking Rules:

Configurable by admin:

Strict Mode: Certain items (e.g., labs, drugs) must be paid before service continues.

Flexible Mode: Services can proceed with unpaid items, but system ensures all bills are cleared before discharge.

Audit & Reporting:

Track balances (per patient, per department, hospital-wide).

Daily revenue collection reports.

Pending bills & aging balances.

Exemptions and approvals.

2.9 Admission & Ward Management (IPD)

Doctor can admit patient from consultation.

Assign ward (nurse handles actual bed allocation).

Transfers: Between wards, with audit trail.

Ward-specific permissions (e.g., specialist wards).

Bed status (occupied, available, reserved).

Discharge initiated by doctor/admin.

2.10 Nursing Services
IPD (Inpatient)

Medication Administration Record (MAR):

Pharmacy dispenses → nurse administers → record in MAR.

Time, dose, route, nurse, remarks logged.

Nursing Procedures: IV insertion, catheter care, dressing, vital follow-ups.

OPD (Outpatient)

Injection Room:

After pharmacy issues injection drugs → nurse confirms pending items → administers.

Prevents duplicate administration.

Logged in MAR.

Wound Dressing / Minor Procedures Room:

Separate department with queue.

Doctor referral or direct registration at reception.

Consumables logged.

Billing integrated.

Audit & Safety:

All administrations linked to prescription/order.

Nurse identity & timestamp recorded.

2.11 User Roles & Permissions

Managed via Spatie.

Roles: Admin, Receptionist, Doctor, Nurse, Pharmacist, Lab Staff, Cashier, Accountant.

Fine-grained permissions:

Receptionist: register, queue patients.

Doctor: consultations, admissions, prescriptions.

Nurse: vitals, bed assignment, drug administration, minor procedures.

Pharmacist: dispense, manage drug/consumable stock.

Lab staff: manage tests.

Cashier: handle payments.

Admin: assign doctors to multiple departments/wards.

2.12 Reporting & Audit

Financial: revenue, exemptions, pending balances.

Clinical: patient visits, admissions, ward occupancy, lab reports.

Pharmacy: drug stock, usage, expiry, consumption trends.

Consumables: issued vs used, departmental consumption.

Audit trail: who did what, when.

3. Non-Functional Requirements

Secure authentication & authorization.

Data integrity: prevent duplicate administration, billing mismatches.

Performance: optimized for hospital LAN.

Scalability: modular design for future expansion (insurance integration, mobile apps).

Data privacy: patient confidentiality maintained.