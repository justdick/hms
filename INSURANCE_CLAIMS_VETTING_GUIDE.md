# Insurance Claims Vetting Guide

## Overview
Insurance claim vetting is a critical process where authorized personnel review and verify insurance claims before they are submitted to insurance providers. This ensures accuracy, completeness, and compliance with insurance requirements.

## Vetting Workflow

### Step 1: Navigate to Insurance Claims
1. Log in to the HMS system
2. Click on **Insurance** in the sidebar
3. Select **Claims** (route: `/admin/insurance/claims`)

### Step 2: View Claims List
The claims list page displays:
- **Total Claims** with status counts (Draft, Pending Vetting, Vetted, Submitted)
- **Filters** to search by:
  - Status
  - Insurance Provider
  - Date Range (From/To)
  - Search (by claim code, patient name, or membership ID)
- **Claims Table** showing:
  - Claim Code
  - Patient Name
  - Provider/Plan
  - Date of Attendance
  - Total Claimed Amount
  - Status Badge
  - View Button (eye icon)

### Step 3: View Claim Details
1. Click the **eye icon** in the actions column
2. This opens the claim detail page showing:
   - **Patient Information**: Name, DOB, Gender, Membership ID
   - **Visit Details**: Date of attendance, type of service, specialty
   - **Diagnosis**: ICD-10/11 codes, primary and secondary diagnoses
   - **Claim Items**: Breakdown of services, medications, procedures
   - **Financial Summary**: Total claimed, approved amounts, co-pays
   - **Workflow Status**: Current status, vetting history, submission history

### Step 4: Vet the Claim
On the claim detail page, if the claim is in **"Draft" or "Pending Vetting"** status:

1. **Review Claim Items**:
   - Verify each service, medication, or procedure is properly documented
   - Check that billing codes are correct
   - Ensure quantities and amounts are accurate
   - Confirm diagnosis codes match the services

2. **Check Coverage Rules**:
   - Verify services are covered under the patient's insurance plan
   - Check if pre-authorization was obtained (if required)
   - Ensure services don't exceed annual limits
   - Verify patient copay amounts are correct

3. **Validate Documentation**:
   - Ensure all required referrals are attached
   - Check that prescriber information is complete
   - Verify dates of service

4. **Take Action**:

   **Option A: Approve for Submission**
   - Click **"Vet & Approve"** button
   - The system records:
     - Vetted By: Your user ID
     - Vetted At: Current timestamp
   - Status changes to **"Vetted"**
   - Claim is now ready for submission to the insurance provider

   **Option B: Reject at Vetting**
   - Click **"Reject"** button
   - Enter **Rejection Reason** (required):
     - "Incomplete documentation"
     - "Diagnosis code mismatch"
     - "Missing required referral"
     - "Service not covered under plan"
     - "Incorrect billing codes"
     - Or custom reason
   - Status changes to **"Rejected"**
   - The claim returns to draft for corrections

### Step 5: Post-Vetting Actions

After vetting, the claim workflow continues:

1. **Vetted Claims** (Status: "Vetted"):
   - Ready for submission by authorized staff
   - Can be bulk submitted to insurance providers
   - Submission creates batch files in required formats

2. **Rejected Claims** (Status: "Rejected"):
   - Can be corrected and resubmitted for vetting
   - Maintain audit trail of rejection reasons
   - Track in Rejection Analysis report

## Vetting API Endpoint

**POST** `/admin/insurance/claims/{claim}/vet`

### Request Body:
```json
{
    "action": "approve",  // or "reject"
    "rejection_reason": "Optional reason if rejecting",
    "notes": "Optional vetting notes"
}
```

### Response:
```json
{
    "message": "Claim vetted successfully",
    "claim": {
        "id": 123,
        "status": "vetted",
        "vetted_by": 2,
        "vetted_at": "2025-10-30T23:00:00.000000Z"
    }
}
```

## Performance Metrics

Vetting performance is tracked in the **Vetting Performance Report**:
- Claims Vetted per Officer
- Average Turnaround Time
- Approval Rate
- Top Performer
- Fastest Turnaround

## Common Rejection Reasons

Based on system data, the most common rejection reasons are:
1. **Diagnosis code mismatch** (15.07%)
2. **Missing required referral** (12.33%)
3. **Patient coverage expired** (10.96%)
4. **Incomplete documentation** (10.96%)
5. **Treatment not medically necessary** (9.59%)
6. **Service not covered under plan** (8.22%)
7. **Exceeds annual limit** (8.22%)
8. **Pre-authorization not obtained** (8.22%)
9. **Incorrect billing codes** (8.22%)
10. **Duplicate claim submission** (8.22%)

## Best Practices

1. **Verify Patient Coverage**: Always confirm the patient's insurance is active at the time of service
2. **Check Pre-authorization**: For high-cost services, ensure pre-auth was obtained
3. **Review Diagnosis Codes**: Ensure ICD-10/11 codes are specific and support the services billed
4. **Validate Billing Codes**: Cross-reference service codes with insurance tariff schedules
5. **Document Thoroughly**: Add notes explaining complex cases or unusual circumstances
6. **Turnaround Time**: Aim to vet claims within 24-48 hours of submission
7. **Quality Over Speed**: A thorough vetting process reduces claim rejections later

## Permissions Required

To vet insurance claims, users must have:
- **Role**: Vetting Officer, Insurance Administrator, or Admin
- **Permission**: `vet-insurance-claims`

## Reporting

Monitor vetting performance using these reports:
- **Vetting Performance Report**: Officer productivity and turnaround times
- **Rejection Analysis**: Patterns and trends in claim rejections
- **Outstanding Claims**: Track claims pending vetting
- **Claims Summary**: Overall claim status breakdown

---

## Quick Reference

| Status | Description | Next Action |
|--------|-------------|-------------|
| Draft | Newly created, not ready for vetting | Complete documentation |
| Pending Vetting | Ready for review | Vet claim (approve/reject) |
| Vetted | Approved by vetting officer | Submit to insurance |
| Submitted | Sent to insurance provider | Await approval |
| Approved | Insurance approved claim | Process payment |
| Rejected | Rejected by vetting or insurance | Correct and resubmit |
| Paid | Payment received from insurance | Close claim |
| Partial | Partially paid by insurance | Follow up on balance |

## Support

For questions or issues with the vetting process:
- Contact: Insurance Department
- Email: insurance@hms.com
- Internal: Extension 2500
