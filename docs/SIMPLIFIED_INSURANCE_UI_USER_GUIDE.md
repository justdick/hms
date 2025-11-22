# Insurance Management System - User Guide

## Table of Contents
1. [Introduction](#introduction)
2. [Getting Started](#getting-started)
3. [Setting Up a New Insurance Plan](#setting-up-a-new-insurance-plan)
4. [Using Coverage Presets](#using-coverage-presets)
5. [Managing Coverage](#managing-coverage)
6. [Adding Coverage Exceptions with Tariffs](#adding-coverage-exceptions-with-tariffs)
7. [Bulk Import Guide](#bulk-import-guide)
8. [Viewing Insurance Analytics](#viewing-insurance-analytics)
9. [Vetting Insurance Claims](#vetting-insurance-claims)
10. [Troubleshooting](#troubleshooting)

---

## Introduction

The Insurance Management System provides a streamlined interface for configuring insurance plans, managing coverage rules, processing claims, and viewing analytics. This guide covers all essential workflows.

**Key Features:**
- Quick plan setup with smart defaults (80% coverage auto-created)
- Unified coverage management with inline editing
- Integrated tariff pricing within exception workflow
- Quick actions from Plans list for faster navigation
- Slide-over claims vetting panel for efficient review
- Analytics dashboard with expandable report widgets
- Simplified navigation with fewer clicks

---

## Getting Started

### Accessing the Insurance Management System

1. Log in to the admin panel
2. Navigate to **Insurance** in the main menu
3. You'll see five main sections:
   - **Providers** - Manage insurance companies
   - **Plans** - View and configure insurance plans
   - **Coverage** - Manage coverage rules (accessed via Plans)
   - **Claims** - Process and vet insurance claims
   - **Analytics** - View reports and metrics

### Quick Navigation from Plans List

The Plans list provides quick action buttons for common tasks:

- **Manage Coverage** - Jump directly to coverage management for a plan
- **View Claims** - See all claims for a specific plan
- **Edit** - Modify plan details

This reduces navigation from 5 clicks to 3 clicks for most workflows.

---

## Setting Up a New Insurance Plan

### Step 1: Create New Plan

1. Click **Create New Plan** button
2. You'll enter the **Plan Setup Wizard**

### Step 2: Enter Plan Details

Fill in the basic information:
- **Plan Name**: e.g., "Gold Corporate Plan"
- **Plan Code**: e.g., "CORP-GOLD-001"
- **Provider**: Select the insurance provider
- **Description**: Optional details about the plan

Click **Next** to continue.

### Step 3: Smart Defaults Applied Automatically

**NEW:** The system automatically creates default coverage rules at 80% for all six categories:
- Consultation: 80%
- Drugs: 80%
- Labs: 80%
- Procedures: 80%
- Ward: 80%
- Nursing: 80%

You can adjust these percentages during plan creation or modify them later in Coverage Management.

### Step 4: Review and Create

1. Review all plan details and default coverage percentages
2. Adjust any percentages if needed
3. Click **Create Plan**
4. The system creates:
   - The insurance plan
   - 6 default coverage rules (one per category)
5. Success message confirms: "Plan created with default 80% coverage for all categories"
6. You're redirected to the Plans list

**Time to Complete:** Under 2 minutes for a standard plan!

### Optional: Coverage Presets

If you prefer different starting values, you can still use presets:

**NHIS Standard** - 70-90% coverage across categories
**Corporate Premium** - 90-100% high-end coverage
**Basic Coverage** - 50-80% budget-friendly coverage
**Custom** - Set your own percentages

---

## Using Coverage Presets

### What Are Presets?

Presets are pre-configured coverage templates for common insurance plan types. They save time by automatically filling in typical coverage percentages.

### When to Use Each Preset

**NHIS Standard**
- Government-mandated insurance plans
- Standard national health coverage
- Balanced coverage across all categories

**Corporate Premium**
- High-end corporate insurance
- Executive health plans
- Maximum coverage for employees

**Basic Coverage**
- Budget-friendly plans
- Minimal coverage requirements
- Cost-conscious options

**Custom**
- Unique plan requirements
- Non-standard coverage structures
- When none of the presets fit

### Modifying Preset Values

You can always modify preset values:
1. Select a preset to pre-fill the fields
2. Click on any percentage field
3. Enter your desired value
4. The preset serves as a starting point, not a restriction

---

## Managing Coverage

### Accessing Coverage Management

**From Plans List (Recommended):**
1. Navigate to **Insurance** → **Plans**
2. Find the plan you want to manage
3. Click **Manage Coverage** button
4. You're taken directly to Coverage Management

**Direct Navigation:**
1. Navigate to **Insurance** → **Plans**
2. Click on a plan name
3. Click **Coverage** tab

### Understanding Coverage Management

The unified interface shows all six coverage categories as visual cards:

```
┌──────────────┐  ┌──────────────┐  ┌──────────────┐
│  💊 Drugs    │  │  🔬 Labs     │  │  👨‍⚕️ Consult  │
│     80%      │  │     90%      │  │     70%      │
│  3 exceptions│  │  1 exception │  │  No exceptions│
└──────────────┘  └──────────────┘  └──────────────┘
```

### Color Coding

Cards use consistent color indicators:

- **Green (80-100%)**: High coverage - excellent for patients
- **Yellow (50-79%)**: Medium coverage - moderate patient cost
- **Red (1-49%)**: Low coverage - high patient cost
- **Gray**: Not configured - needs setup

### Card Information

Each card displays:
- **Category icon and name**
- **Default coverage percentage**
- **Number of exceptions** (items with different coverage)
- **Exception count badge**

### Expanding Cards

**Click a card** to expand and see:
- Unified table showing all coverage rules and exceptions
- Tariff prices for items with custom pricing
- Quick actions (Add Exception, Edit Default)

### Inline Editing

To quickly change a coverage percentage:

1. **Click** on the percentage number
2. The field becomes **editable**
3. **Type** the new value (0-100)
4. **Press Enter** or **click away** to save
5. A **green checkmark** confirms the save
6. If there's an error, the value **reverts** with a red shake animation

**Example:**
- Click "80%" → Type "85" → Press Enter → Saved!

### Global Search

Use the search bar at the top to find items across all categories:

1. Type item name or code
2. Results appear in real-time
3. Matching items are highlighted in expanded cards
4. Search results count is displayed

---

## Adding Coverage Exceptions with Tariffs

### What Are Exceptions?

Exceptions are specific items that have different coverage than the default for their category. They can also have custom tariff pricing.

**Example:**
- Default drug coverage: 80%
- Paracetamol exception: 100% (fully covered)
- Expensive cancer drug exception: 50% (lower coverage) + custom tariff

### When to Add Exceptions

Add exceptions when:
- Specific items should be fully covered
- Expensive items need lower coverage
- Certain items should be excluded
- Special pricing (tariffs) applies to particular items

### Adding an Exception with Tariff

1. **Open the category** by clicking its card
2. Click **Add Exception** button
3. The exception modal appears

### Exception Modal Fields

**1. Search for Item**
- Type the item name or code
- Results appear as you type
- Select the item from the list
- Items with existing exceptions show a badge

**2. Choose Coverage Type**

Four options:

- **Percentage** (most common)
  - Enter a percentage (0-100)
  - System calculates copay automatically
  
- **Fixed Amount**
  - Enter the amount insurance pays
  - Patient pays the difference
  
- **Fully Covered**
  - Insurance pays 100%
  - Patient pays nothing
  
- **Not Covered**
  - Insurance pays 0%
  - Patient pays full price

**3. Set Tariff Pricing (NEW)**

Choose pricing option:

- **Use Standard Price** (default)
  - Uses the hospital's standard price
  - No custom tariff needed
  
- **Set Custom Tariff**
  - Enter a custom price for this item
  - Insurance pays from this tariff amount
  - Useful for negotiated rates

**Example:**
```
Item: Advanced MRI Scan
Standard Price: GHS 850.00
Custom Tariff: GHS 700.00 (negotiated rate)
Coverage: 90%

Result:
- Insurance pays: GHS 630.00 (90% of GHS 700)
- Patient pays: GHS 70.00 (10% of GHS 700)
```

**4. Add Notes (Optional)**
- Explain why this exception exists
- Document special circumstances
- Note negotiated rates or special terms

### Coverage Preview

The modal shows a real-time preview:

```
Preview:
Standard Price: GHS 45.00
Tariff Price: GHS 40.00 (custom)
Insurance pays: GHS 40.00 (100%)
Patient pays: GHS 0.00 (0%)
```

### Viewing Tariffs in Exception List

When you expand a category, the exceptions table shows:
- Item code and description
- Coverage percentage
- **Tariff price** (if custom tariff set)
- Indicator for items with custom tariffs

You can filter to show only items with custom tariffs.

### Saving the Exception

1. Review the preview
2. Click **Add Exception**
3. The modal closes
4. The exception appears in the category's exception list
5. The exception count badge updates
6. Custom tariff is saved and displayed

### Editing Exceptions

1. Expand the category card
2. Find the exception in the list
3. Click the **Edit** icon
4. Modify coverage or tariff values
5. Click **Save**

### Deleting Exceptions

1. Expand the category card
2. Find the exception in the list
3. Click the **Delete** icon
4. Confirm the deletion
5. The item reverts to using the default coverage
6. Custom tariff is removed

---

## Bulk Import Guide

### When to Use Bulk Import

Use bulk import when you need to:
- Add many exceptions at once (10+)
- Import coverage from a spreadsheet
- Migrate data from another system
- Update multiple items efficiently

### Step 1: Download the Template

1. Open the category you want to import exceptions for
2. Click **Import Exceptions**
3. Click **Download Template**
4. An Excel file downloads to your computer

### Step 2: Understand the Template

The template contains two sheets:

**Instructions Sheet:**
- How to fill out the template
- Field descriptions
- Examples
- Common mistakes to avoid

**Data Sheet:**
- Column headers
- Example rows (delete these before importing)
- Pre-formatted for easy data entry

### Step 3: Fill Out the Template

**Required Columns:**

| Column | Description | Example |
|--------|-------------|---------|
| item_code | The unique code for the item | "PARA-500" |
| item_name | The item's name | "Paracetamol 500mg" |
| coverage_percentage | Coverage % (0-100) | "100" |
| notes | Optional explanation | "Essential medication" |

**Tips for Filling:**
- Use exact item codes from your system
- Double-check percentages (0-100 range)
- Add notes for unusual coverage
- Remove example rows before importing
- Save as Excel format (.xlsx)

### Step 4: Upload the Template

1. Click **Import Exceptions** again
2. Click **Choose File** or drag and drop
3. Select your filled template
4. Click **Upload**

### Step 5: Review and Validate

The system validates your file and shows:

**Valid Rows (Green)**
- Will be imported successfully
- Shows item name, code, and coverage

**Invalid Rows (Red)**
- Have errors that must be fixed
- Shows specific error messages
- Must be corrected before import

**Common Errors:**
- Item code not found in system
- Coverage percentage out of range (not 0-100)
- Missing required fields
- Duplicate item codes

### Step 6: Fix Errors (If Any)

If there are errors:

1. Note the error messages
2. Click **Cancel**
3. Fix the errors in your Excel file
4. Upload again

### Step 7: Confirm Import

1. Review the summary:
   - Total rows: 50
   - Valid: 45
   - Invalid: 5
2. Decide whether to:
   - **Import Valid Rows**: Imports only the valid ones
   - **Cancel**: Fix all errors first
3. Click **Import Valid Rows**

### Step 8: Import Complete

The system shows a summary:
- "45 exceptions added successfully"
- "5 rows skipped due to errors"

The exceptions now appear in the category's exception list.

### Best Practices

- **Start small**: Test with 5-10 rows first
- **Verify codes**: Ensure item codes match your system
- **Use notes**: Document why exceptions exist
- **Keep backups**: Save your Excel file for future reference
- **Review after import**: Check a few exceptions to confirm accuracy

---

## Viewing Insurance Analytics

### Accessing the Analytics Dashboard

1. Navigate to **Insurance** → **Analytics**
2. You'll see the Analytics Dashboard with six report widgets

### Analytics Dashboard Overview

The dashboard displays all insurance reports in a single view with expandable widgets:

```
┌─────────────────────────────────────────────────────────┐
│ Insurance Analytics Dashboard                           │
│ Date Range: [From: 01/01/25] [To: 31/01/25] [Apply]   │
├─────────────────────────────────────────────────────────┤
│ ┌──────────────┐  ┌──────────────┐  ┌──────────────┐  │
│ │ Claims       │  │ Revenue      │  │ Outstanding  │  │
│ │ Summary      │  │ Analysis     │  │ Claims       │  │
│ │ 245 claims   │  │ GHS 125,000  │  │ 23 pending   │  │
│ │ [Expand ▼]   │  │ [Expand ▼]   │  │ [Expand ▼]   │  │
│ └──────────────┘  └──────────────┘  └──────────────┘  │
│                                                         │
│ ┌──────────────┐  ┌──────────────┐  ┌──────────────┐  │
│ │ Vetting      │  │ Utilization  │  │ Rejection    │  │
│ │ Performance  │  │ Report       │  │ Analysis     │  │
│ │ 95% approved │  │ 78% avg      │  │ 5% rejected  │  │
│ │ [Expand ▼]   │  │ [Expand ▼]   │  │ [Expand ▼]   │  │
│ └──────────────┘  └──────────────┘  └──────────────┘  │
└─────────────────────────────────────────────────────────┘
```

### Using the Date Range Filter

The date range filter at the top affects all widgets:

1. Select **From** date
2. Select **To** date
3. Click **Apply**
4. All widgets refresh with new data

### Expanding Widgets

To see detailed information:

1. Click **Expand** on any widget
2. The widget expands inline to show details
3. Detailed data loads (lazy loaded for performance)
4. Click **Collapse** to minimize

### Available Reports

**1. Claims Summary**
- Total claims count
- Total claim amount
- Claims by status
- Claims by provider

**2. Revenue Analysis**
- Total revenue from insurance
- Revenue by provider
- Revenue by month
- Top revenue-generating services

**3. Outstanding Claims**
- Claims pending vetting
- Claims pending submission
- Claims pending payment
- Aging analysis

**4. Vetting Performance**
- Approval rate
- Rejection rate
- Average vetting time
- Vetting officer performance

**5. Utilization Report**
- Average coverage percentage
- Most utilized services
- Utilization by category
- Utilization trends

**6. Rejection Analysis**
- Rejection reasons
- Rejection rate by provider
- Rejection trends
- Common rejection patterns

### Benefits of Widget-Based Analytics

- **Single Page View** - All reports in one place
- **Shared Filters** - Date range applies to all widgets
- **Lazy Loading** - Details load only when expanded
- **Fast Navigation** - No page changes required
- **Responsive Design** - Works on all screen sizes

---

## Vetting Insurance Claims

### Accessing Claims for Vetting

1. Navigate to **Insurance** → **Claims**
2. You'll see the Claims list with all insurance claims
3. Use filters to find claims needing vetting:
   - Status: Pending Vetting
   - Insurance provider
   - Date range
   - Search by patient name or claim code

### Claims List View

The list shows key information:
- Claim Check Code (CCC)
- Patient name
- Insurance plan
- Visit date
- Total amount
- Status
- **Review** button

### Opening the Vetting Panel

**NEW:** Claims are now vetted using a slide-over panel:

1. Click **Review** button on any claim
2. A panel slides in from the right
3. You stay on the Claims list (no navigation)
4. Review claim details in the panel

### Vetting Panel Contents

The panel displays:

**Patient Information**
- Patient name
- Membership ID
- Insurance plan
- Visit date

**Claim Items Table**
- Service/item description
- Quantity
- Unit tariff
- Coverage percentage
- Insurance pays amount
- Patient pays amount

**Diagnosis Information**
- Primary diagnosis code and description
- Secondary diagnoses (if any)

**Financial Summary**
- Total claim amount
- Insurance covered amount
- Patient copay amount

### Vetting Actions

**Approve Claim:**
1. Review all claim items
2. Verify services match diagnosis
3. Click **Approve** button
4. Claim status changes to "Vetted"
5. Panel closes automatically
6. Claims list refreshes

**Reject Claim:**
1. Review claim items
2. Identify issues
3. Click **Reject** button
4. Enter rejection reason (required)
5. Click **Confirm Rejection**
6. Claim status changes to "Rejected"
7. Panel closes automatically

**Close Panel:**
- Click **Close** button
- Press **Escape** key
- Click outside the panel

### Keyboard Shortcuts in Vetting Panel

- **Escape** - Close panel
- **Ctrl+Enter** - Approve claim (quick approval)
- **Tab** - Navigate between fields

### Benefits of Slide-Over Vetting

- **No Navigation** - Stay on Claims list
- **Faster Workflow** - Review multiple claims quickly
- **Context Preserved** - See list while reviewing
- **Keyboard Support** - Efficient for power users

---

## Troubleshooting

### Common Issues and Solutions

#### Issue: "Item code not found" error

**Solution:**
- Verify the item code exists in your system
- Check for typos in the code
- Ensure you're searching in the correct category
- Contact your system administrator if the item should exist

#### Issue: Inline edit doesn't save

**Solution:**
- Check your internet connection
- Ensure the percentage is between 0 and 100
- Try refreshing the page
- Check browser console for errors

#### Issue: Bulk import fails

**Solution:**
- Download a fresh template
- Verify all item codes exist
- Check percentage values are 0-100
- Remove any example rows
- Ensure file is in Excel format (.xlsx)

#### Issue: Exception count doesn't update

**Solution:**
- Refresh the page
- Clear browser cache
- Check if the exception was actually saved
- Try adding the exception again

#### Issue: Can't access Coverage Management from Plans list

**Solution:**
- Ensure you have permission to manage coverage
- Check that the plan is active
- Try clicking the plan name first, then the Coverage tab
- Contact your administrator if the button is missing

#### Issue: Vetting panel won't open

**Solution:**
- Check your internet connection
- Ensure the claim exists and is in correct status
- Try refreshing the Claims list
- Check browser console for errors

#### Issue: Analytics widgets won't expand

**Solution:**
- Check your internet connection
- Ensure date range is valid
- Try refreshing the page
- Check if you have permission to view reports

#### Issue: Tariff price not saving

**Solution:**
- Ensure you selected "Set Custom Tariff" option
- Enter a valid price (greater than 0)
- Check that the exception was saved successfully
- Verify you have permission to set tariffs

#### Issue: Color coding seems wrong

**Solution:**
- Colors are based on coverage percentage:
  - Green: 80-100%
  - Yellow: 50-79%
  - Red: 1-49%
  - Gray: Not configured
- If colors still seem incorrect, report to your administrator

#### Issue: Global search not finding items

**Solution:**
- Check spelling of search term
- Try searching by item code instead of name
- Ensure the item exists in the system
- Expand categories to see if item appears
- Clear search and try again

### Getting Help

If you encounter issues not covered here:

1. **Check the help tooltips**: Hover over fields for guidance
2. **Review this guide**: Search for your specific issue
3. **Contact support**: Reach out to your system administrator
4. **Report bugs**: Use the feedback form in the admin panel

### Best Practices

- **Save frequently**: Don't rely on auto-save for critical changes
- **Review before saving**: Double-check percentages and calculations
- **Use notes**: Document why exceptions exist
- **Regular monitoring**: Check recent items weekly
- **Test imports**: Start with small bulk imports
- **Keep backups**: Export coverage rules regularly

---

## Appendix: Coverage Calculation Examples

### Example 1: Percentage Coverage

**Scenario:**
- Item: Paracetamol 500mg
- Price: $10.00
- Coverage: 80%

**Calculation:**
- Insurance pays: $10.00 × 80% = $8.00
- Patient pays: $10.00 × 20% = $2.00

### Example 2: Fixed Amount Coverage

**Scenario:**
- Item: Consultation
- Price: $50.00
- Coverage: Fixed $30.00

**Calculation:**
- Insurance pays: $30.00
- Patient pays: $50.00 - $30.00 = $20.00

### Example 3: Fully Covered

**Scenario:**
- Item: Essential vaccine
- Price: $75.00
- Coverage: Fully covered

**Calculation:**
- Insurance pays: $75.00 (100%)
- Patient pays: $0.00

### Example 4: Not Covered

**Scenario:**
- Item: Cosmetic procedure
- Price: $200.00
- Coverage: Not covered

**Calculation:**
- Insurance pays: $0.00
- Patient pays: $200.00 (100%)

---

## Quick Reference Card

### Setup Checklist
- [ ] Create new plan (auto-creates 80% default coverage)
- [ ] Review and adjust default percentages if needed
- [ ] Add exceptions for specific items
- [ ] Set custom tariffs where applicable
- [ ] Test coverage calculations

### Daily Tasks
- [ ] Vet pending claims using slide-over panel
- [ ] Review claims list for new submissions
- [ ] Respond to claim rejections
- [ ] Monitor claim status changes

### Weekly Tasks
- [ ] Review all coverage categories
- [ ] Check exception lists and tariffs
- [ ] View Analytics Dashboard for trends
- [ ] Export coverage rules (backup)
- [ ] Review vetting performance metrics

### Monthly Tasks
- [ ] Audit all insurance plans
- [ ] Review utilization reports
- [ ] Update coverage rules as needed
- [ ] Analyze rejection patterns
- [ ] Train new administrators on simplified workflows

---

**Need More Help?**

Contact your system administrator or refer to the technical documentation for advanced features and troubleshooting.
