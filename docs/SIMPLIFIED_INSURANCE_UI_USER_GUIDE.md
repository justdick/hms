# Simplified Insurance UI - User Guide

## Table of Contents
1. [Introduction](#introduction)
2. [Getting Started](#getting-started)
3. [Setting Up a New Insurance Plan](#setting-up-a-new-insurance-plan)
4. [Using Coverage Presets](#using-coverage-presets)
5. [Managing Coverage with the Dashboard](#managing-coverage-with-the-dashboard)
6. [Adding Coverage Exceptions](#adding-coverage-exceptions)
7. [Bulk Import Guide](#bulk-import-guide)
8. [Monitoring Recent Items](#monitoring-recent-items)
9. [Keyboard Shortcuts](#keyboard-shortcuts)
10. [Troubleshooting](#troubleshooting)

---

## Introduction

The Simplified Insurance UI makes it easy to configure and manage insurance coverage plans. This guide will walk you through all the features, from setting up a new plan to managing exceptions and monitoring coverage.

**Key Features:**
- Quick plan setup with smart presets
- Visual coverage dashboard with color-coded categories
- Inline editing for fast adjustments
- Simplified exception management
- Bulk import for multiple exceptions
- Real-time monitoring of new items

---

## Getting Started

### Accessing the Insurance Management System

1. Log in to the admin panel
2. Navigate to **Insurance** → **Plans**
3. You'll see a list of all insurance plans

### Switching Between UI Modes

If your system has both the old and new UI enabled:

1. Look for the **UI Mode** toggle in the top-right corner
2. Click to switch between "Classic UI" and "Simplified UI"
3. Your preference will be saved for future sessions

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

### Step 3: Choose Coverage Preset

Select a preset that matches your plan type:

#### Available Presets:

**NHIS Standard**
- Consultation: 70%
- Drugs: 80%
- Labs: 90%
- Procedures: 75%
- Ward: 100%
- Nursing: 80%

**Corporate Premium**
- Consultation: 90%
- Drugs: 90%
- Labs: 100%
- Procedures: 90%
- Ward: 100%
- Nursing: 90%

**Basic Coverage**
- Consultation: 50%
- Drugs: 60%
- Labs: 70%
- Procedures: 50%
- Ward: 80%
- Nursing: 60%

**Custom**
- Start with blank fields and set your own percentages

### Step 4: Adjust Coverage (Optional)

After selecting a preset:
- Review the pre-filled percentages
- Modify any category as needed
- Use **Copy to All** to apply one percentage to all categories
- The patient copay is calculated automatically

### Step 5: Review and Create

1. Review all plan details and coverage percentages
2. Click **Create Plan**
3. The system creates the plan and all default coverage rules
4. You'll be redirected to the Coverage Dashboard

**Time to Complete:** Under 2 minutes for a standard plan!

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

## Managing Coverage with the Dashboard

### Understanding the Coverage Dashboard

The dashboard shows all six coverage categories as visual cards:

```
┌──────────────┐  ┌──────────────┐  ┌──────────────┐
│  💊 Drugs    │  │  🔬 Labs     │  │  👨‍⚕️ Consult  │
│     80%      │  │     90%      │  │     70%      │
│  3 exceptions│  │  1 exception │  │  No exceptions│
└──────────────┘  └──────────────┘  └──────────────┘
```

### Color Coding

Cards are color-coded for quick understanding:

- **Green (80-100%)**: High coverage - excellent for patients
- **Yellow (50-79%)**: Medium coverage - moderate patient cost
- **Red (1-49%)**: Low coverage - high patient cost
- **Gray**: Not configured - needs setup

### Card Information

Each card displays:
- **Category icon and name**
- **Default coverage percentage**
- **Number of exceptions** (items with different coverage)
- **Visual color indicator**

### Interacting with Cards

**Click a card** to expand and see:
- Default coverage rule details
- List of all exceptions
- Quick actions (Add Exception, Edit Default)

**Hover over a card** to see:
- Quick summary tooltip
- Coverage and copay breakdown
- Exception count

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

---

## Adding Coverage Exceptions

### What Are Exceptions?

Exceptions are specific items that have different coverage than the default for their category.

**Example:**
- Default drug coverage: 80%
- Paracetamol exception: 100% (fully covered)
- Expensive cancer drug exception: 50% (lower coverage)

### When to Add Exceptions

Add exceptions when:
- Specific items should be fully covered
- Expensive items need lower coverage
- Certain items should be excluded
- Special pricing applies to particular items

### Adding an Exception

1. **Open the category** by clicking its card
2. Click **Add Exception** button
3. The simplified modal appears

### Exception Modal Fields

**1. Search for Item**
- Type the item name or code
- Results appear as you type
- Select the item from the list
- Items with existing exceptions show a badge

**2. Choose Coverage Type**

Four simple options:

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

**3. Add Notes (Optional)**
- Explain why this exception exists
- Document special circumstances
- Help future administrators understand

### Coverage Preview

The modal shows a real-time preview:

```
Preview:
Item Price: $45.00
Insurance pays: $45.00 (100%)
Patient pays: $0.00 (0%)
```

### Saving the Exception

1. Review the preview
2. Click **Add Exception**
3. The modal closes
4. The exception appears in the category's exception list
5. The exception count badge updates

### Editing Exceptions

1. Expand the category card
2. Find the exception in the list
3. Click the **Edit** icon
4. Modify the values
5. Click **Save**

### Deleting Exceptions

1. Expand the category card
2. Find the exception in the list
3. Click the **Delete** icon
4. Confirm the deletion
5. The item reverts to using the default coverage

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

## Monitoring Recent Items

### Recent Items Panel

The Recent Items panel shows items added to the system in the last 30 days.

### Why Monitor Recent Items?

- **Catch expensive items**: Review high-cost items before they're claimed
- **Ensure proper coverage**: Verify new items have appropriate coverage
- **Prevent surprises**: Avoid unexpected claim costs
- **Maintain control**: Stay informed about system changes

### Understanding the Panel

```
┌─────────────────────────────────────────────┐
│  Recently Added Items (Last 30 Days)        │
├─────────────────────────────────────────────┤
│  ⚠️  Advanced MRI Scan                      │
│      $850.00 • Uses default 90% coverage    │
│      [Add Exception]                        │
├─────────────────────────────────────────────┤
│  ✓  Basic X-Ray                             │
│      $45.00 • Uses default 90% coverage     │
│      [Add Exception]                        │
└─────────────────────────────────────────────┘
```

### Item Indicators

**⚠️ Warning Icon (Red)**
- Item is expensive (over $500)
- Requires attention
- May need custom coverage

**✓ Check Icon (Green)**
- Standard-priced item
- Using default coverage
- No immediate action needed

### Coverage Status

Each item shows its current status:

- **"Uses default 90% coverage"**: Following the category default
- **"Custom exception: 50%"**: Has a specific exception
- **"Not covered"**: No coverage configured

### Quick Actions

**Add Exception Button**
- Click to immediately add an exception for this item
- Pre-fills the item in the exception modal
- Fast way to adjust coverage for new items

### Reviewing Items

Regular review workflow:

1. **Check the panel weekly**
2. **Look for warning icons** (expensive items)
3. **Review coverage status**
4. **Add exceptions** if needed
5. **Dismiss** items after review (if applicable)

### Expensive Item Threshold

Items over $500 are flagged as expensive. This threshold helps you:
- Focus on high-impact items
- Prevent costly surprises
- Maintain budget control

---

## Keyboard Shortcuts

Speed up your workflow with keyboard shortcuts:

### Global Shortcuts

| Shortcut | Action |
|----------|--------|
| `N` | Add new exception (when viewing a category) |
| `E` | Enable inline edit mode |
| `Esc` | Close modal or cancel edit |
| `?` | Show keyboard shortcuts help |

### Form Shortcuts

| Shortcut | Action |
|----------|--------|
| `Enter` | Save inline edit |
| `Tab` | Move to next field |
| `Shift + Tab` | Move to previous field |

### Navigation Shortcuts

| Shortcut | Action |
|----------|--------|
| `Arrow Keys` | Navigate between cards |
| `Enter` | Expand selected card |

### Enabling Shortcuts

Keyboard shortcuts are enabled by default. To see all available shortcuts:
- Press `?` key
- Or click **Keyboard Shortcuts** in the help menu

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

#### Issue: Can't see recent items

**Solution:**
- Ensure items were added in the last 30 days
- Check if the category has default coverage
- Verify you have permission to view recent items
- Refresh the page

#### Issue: Color coding seems wrong

**Solution:**
- Colors are based on coverage percentage:
  - Green: 80-100%
  - Yellow: 50-79%
  - Red: 1-49%
  - Gray: Not configured
- If colors still seem incorrect, report to your administrator

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
- [ ] Create new plan
- [ ] Choose preset or custom coverage
- [ ] Review and adjust percentages
- [ ] Create plan
- [ ] Add exceptions as needed
- [ ] Monitor recent items

### Daily Tasks
- [ ] Check recent items panel
- [ ] Review expensive item alerts
- [ ] Add exceptions for new items
- [ ] Respond to notifications

### Weekly Tasks
- [ ] Review all coverage categories
- [ ] Check exception lists
- [ ] Export coverage rules (backup)
- [ ] Review change history

### Monthly Tasks
- [ ] Audit all insurance plans
- [ ] Review expensive item trends
- [ ] Update coverage as needed
- [ ] Train new administrators

---

**Need More Help?**

Contact your system administrator or refer to the technical documentation for advanced features and troubleshooting.
