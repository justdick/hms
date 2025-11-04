# Enhanced Bulk Import User Guide

## Overview

The Enhanced Bulk Import feature allows insurance administrators to quickly configure coverage rules for hundreds of drugs, lab tests, and services without manual data entry. The system pre-populates templates with all items from your inventory, so you only need to edit coverage settings.

## Key Benefits

- **Zero Manual Data Entry**: All items are pre-filled from your system
- **No Typos or Invalid Codes**: Guaranteed valid item codes
- **Faster Configuration**: Just edit coverage values, not enter data
- **Full Coverage Flexibility**: Support all coverage types (percentage, fixed amount, full, excluded)
- **Error Prevention**: Clear validation and detailed error messages

## Quick Start

1. Navigate to Insurance Plans → Select Plan → Coverage Dashboard
2. Click "Bulk Import" button
3. Click "Download Pre-populated Template"
4. Edit coverage_type and coverage_value columns in Excel
5. Save the file
6. Upload the edited template
7. Review the import results

## Step-by-Step Guide

### Step 1: Access Bulk Import

1. Log in as an insurance administrator
2. Navigate to **Admin → Insurance → Plans**
3. Select the insurance plan you want to configure
4. Click the **Coverage Dashboard** tab
5. Click the **Bulk Import** button

### Step 2: Download Pre-populated Template

1. In the Bulk Import modal, click **"Download Pre-populated Template"**
2. The system will generate an Excel file containing:
   - **Instructions Sheet**: Detailed guidance on using the template
   - **Data Sheet**: All items pre-filled with current coverage settings

The template filename includes the category and date:
```
coverage_template_drug_NHIF_Basic_2025-11-01.xlsx
```

### Step 3: Understanding the Template Structure

The downloaded Excel file contains two sheets:

#### Instructions Sheet

This sheet provides:
- Overview of the pre-populated template
- Detailed explanation of each coverage type
- Real-world examples
- Important warnings about what not to modify

#### Data Sheet

This sheet contains all items with the following columns:

| Column | Description | Editable |
|--------|-------------|----------|
| **item_code** | Unique identifier for the item | ❌ No - Do not modify |
| **item_name** | Name of the drug/service/test | ❌ No - Do not modify |
| **current_price** | Current price in the system | ❌ No - Do not modify |
| **coverage_type** | Type of coverage (see below) | ✅ Yes - Edit this |
| **coverage_value** | Coverage amount/percentage | ✅ Yes - Edit this |
| **notes** | Optional notes about the rule | ✅ Yes - Optional |

### Step 4: Understanding Coverage Types

The system supports four coverage types:

#### 1. Percentage Coverage

**Description**: Insurance covers X%, patient pays (100-X)%

**When to Use**: Standard percentage-based coverage for most items

**Example**:
```
Item: Paracetamol 500mg
coverage_type: percentage
coverage_value: 80
Result: Insurance pays 80%, patient pays 20%
```

**Calculation**:
- If item costs $100
- Insurance pays: $80 (80%)
- Patient pays: $20 (20%)

#### 2. Fixed Amount Coverage

**Description**: Insurance pays a fixed dollar amount, patient pays the rest

**When to Use**: Drugs or services with fixed copay amounts

**Example**:
```
Item: Insulin Injection
coverage_type: fixed_amount
coverage_value: 30
Result: Insurance pays $30, patient pays (price - $30)
```

**Calculation**:
- If item costs $100
- Insurance pays: $30 (fixed)
- Patient pays: $70 (remainder)

#### 3. Full Coverage

**Description**: 100% coverage, no patient copay

**When to Use**: Essential medications, preventive care, priority services

**Example**:
```
Item: Preventive Vaccine
coverage_type: full
coverage_value: 100 (value is ignored)
Result: Insurance pays 100%, patient pays 0%
```

**Calculation**:
- If item costs $100
- Insurance pays: $100 (100%)
- Patient pays: $0 (0%)

#### 4. Excluded Coverage

**Description**: 0% coverage, patient pays all

**When to Use**: Cosmetic items, non-covered services, excluded medications

**Example**:
```
Item: Cosmetic Cream
coverage_type: excluded
coverage_value: 0 (value is ignored)
Result: Insurance pays 0%, patient pays 100%
```

**Calculation**:
- If item costs $100
- Insurance pays: $0 (0%)
- Patient pays: $100 (100%)

### Step 5: Editing the Template

#### What You Should Edit

1. **coverage_type**: Change to one of: `percentage`, `fixed_amount`, `full`, or `excluded`
2. **coverage_value**: Set the appropriate value based on coverage type
3. **notes**: Add optional notes for reference

#### What You Should NOT Edit

1. **item_code**: This must match the system exactly
2. **item_name**: This is for reference only
3. **current_price**: This is for reference only

#### Deleting Rows

You can delete rows for items that should use the general/default rule for that category. Only include items that need specific coverage settings.

#### Example Edits

**Before** (downloaded template):
```
item_code | item_name        | current_price | coverage_type | coverage_value | notes
PAR500    | Paracetamol 500mg| 5.00         | percentage    | 80            |
INS100    | Insulin 100IU    | 120.00       | percentage    | 80            |
COS001    | Cosmetic Cream   | 45.00        | percentage    | 80            |
VAC001    | Flu Vaccine      | 30.00        | percentage    | 80            |
```

**After** (your edits):
```
item_code | item_name        | current_price | coverage_type | coverage_value | notes
PAR500    | Paracetamol 500mg| 5.00         | full          | 100           | Essential medication
INS100    | Insulin 100IU    | 120.00       | fixed_amount  | 30            | Standard copay
COS001    | Cosmetic Cream   | 45.00        | excluded      | 0             | Not covered
VAC001    | Flu Vaccine      | 30.00        | full          | 100           | Preventive care
```

### Step 6: Upload the Edited Template

1. Save your edited Excel file
2. Return to the Bulk Import modal
3. Click **"Choose File"** or drag and drop your file
4. Click **"Upload and Import"**
5. Wait for the import to process

### Step 7: Review Import Results

After the import completes, you'll see a summary:

```
Import Results
--------------
Created: 45 rules
Updated: 23 rules
Skipped: 2 rules

Errors:
Row 15: Invalid coverage_type: partial. Must be: percentage, fixed_amount, full, or excluded
Row 28: Item code XYZ999 not found in system
```

#### Understanding Results

- **Created**: New coverage rules that didn't exist before
- **Updated**: Existing rules that were modified
- **Skipped**: Rows that had errors and weren't processed

#### Handling Errors

If there are errors:
1. Note the row numbers with errors
2. Open your Excel file
3. Fix the issues (check coverage_type spelling, verify item codes)
4. Re-upload the corrected file

## Common Coverage Scenarios

### Scenario 1: Essential Medications (100% Coverage)

For medications that should be fully covered:

```
coverage_type: full
coverage_value: 100
```

Examples: Life-saving drugs, chronic disease medications, preventive care

### Scenario 2: Standard Medications (Percentage Coverage)

For most medications with percentage-based coverage:

```
coverage_type: percentage
coverage_value: 80
```

This means 80% insurance, 20% patient copay

### Scenario 3: Specialty Drugs (Fixed Copay)

For expensive medications with fixed patient copay:

```
coverage_type: fixed_amount
coverage_value: 50
```

Patient always pays $50, insurance covers the rest

### Scenario 4: Non-Covered Items (Excluded)

For items not covered by the plan:

```
coverage_type: excluded
coverage_value: 0
```

Patient pays 100% of the cost

## Advanced Features

### Pre-filled Values

The template automatically pre-fills:
- **Existing specific rules**: If you've already configured a rule for an item, it appears with those values
- **General rule values**: If no specific rule exists, it uses the category's general rule
- **Default values**: If no rules exist, it defaults to `percentage` with `80`

This means you can:
1. Download the template to see current settings
2. Make only the changes you need
3. Upload to update just those items

### Bulk Updates

To update multiple items at once:
1. Download the current template (shows existing values)
2. Use Excel's fill-down or find-replace features
3. Upload to apply changes in bulk

Example: Change all drugs from 80% to 90% coverage
1. Download template
2. Select all rows in coverage_value column
3. Find: 80, Replace: 90
4. Upload

### Category-Specific Templates

Each category (drugs, lab tests, consultations, procedures) has its own template:
- **Drugs**: All medications from the drug inventory
- **Lab Tests**: All laboratory tests
- **Consultations**: All consultation services
- **Procedures**: All procedure services

Download the appropriate template for what you want to configure.

## Troubleshooting

### Error: "Invalid coverage_type"

**Problem**: The coverage_type value is not recognized

**Solution**: Ensure coverage_type is exactly one of:
- `percentage`
- `fixed_amount`
- `full`
- `excluded`

Check for typos, extra spaces, or incorrect capitalization.

### Error: "Item code not found in system"

**Problem**: The item_code doesn't exist in your system inventory

**Solution**: 
- Verify the item code is correct
- Check if the item exists in your system
- Don't add new rows with custom codes - only edit pre-filled rows

### Error: "Missing item_code"

**Problem**: The item_code column is empty

**Solution**: Don't delete or modify the item_code column. If you want to skip an item, delete the entire row.

### Import Shows 0 Created/Updated

**Problem**: No rules were created or updated

**Possible Causes**:
1. All rows had errors (check error list)
2. File format is incorrect
3. Wrong category selected

**Solution**: Review the error messages and correct the issues

### Template Download Fails

**Problem**: Can't download the template

**Possible Causes**:
1. No permission to manage the insurance plan
2. Invalid category selected
3. Network issue

**Solution**: 
- Verify you have administrator access
- Try refreshing the page
- Contact system administrator if issue persists

## Best Practices

### 1. Start with a Download

Always download the current template first to see existing settings before making changes.

### 2. Make Incremental Changes

For large imports:
1. Start with a small batch (10-20 items)
2. Verify the results
3. Then process the full list

### 3. Keep Backups

Before uploading major changes:
1. Download the current template (this is your backup)
2. Save it with a date: `backup_2025-11-01.xlsx`
3. Make your changes
4. Upload

If something goes wrong, you can restore from the backup.

### 4. Use Notes Column

Add notes to document your decisions:
```
notes: "Approved by medical board 2025-10-15"
notes: "Temporary exclusion pending review"
notes: "Standard copay for chronic disease"
```

### 5. Review Before Upload

Before uploading:
1. Check coverage_type spelling
2. Verify coverage_value makes sense
3. Ensure you didn't modify item_code, item_name, or current_price
4. Save the file

### 6. Test with One Category First

If configuring multiple categories:
1. Start with one category (e.g., drugs)
2. Complete and verify
3. Then move to next category

## Frequently Asked Questions

### Q: Can I add new items through bulk import?

**A**: No. The bulk import only configures coverage for existing items in your system. To add new drugs, lab tests, or services, use the respective inventory management screens first, then configure coverage.

### Q: What happens to items I delete from the template?

**A**: Deleted rows are simply not processed. The existing coverage rules for those items remain unchanged. If you want to remove a coverage rule, delete it through the UI or set it to use the general rule.

### Q: Can I import multiple categories at once?

**A**: No. Each import is for one category (drugs, lab tests, consultations, or procedures). Download and upload separate templates for each category.

### Q: How do I set different coverage for different patient groups?

**A**: Each insurance plan has its own coverage rules. Configure separate bulk imports for each plan. The template is plan-specific.

### Q: What if I make a mistake?

**A**: You can:
1. Download the current template (shows the mistake)
2. Correct the values
3. Re-upload to fix

Or use the UI to manually correct individual rules.

### Q: Can I use the template offline?

**A**: Yes! Download the template, edit it offline in Excel, and upload when ready. The template is a standard Excel file.

### Q: How often should I update coverage rules?

**A**: Update as needed when:
- New medications are added to inventory
- Coverage policies change
- Prices are updated
- Plan benefits are modified

### Q: What's the maximum file size?

**A**: The system accepts files up to 5MB, which can handle thousands of items.

## Support

If you encounter issues not covered in this guide:

1. Check the error messages carefully - they provide specific guidance
2. Verify your file format matches the template structure
3. Contact your system administrator
4. Refer to the technical documentation for advanced scenarios

## Summary

The Enhanced Bulk Import feature streamlines insurance coverage configuration by:
- Pre-populating templates with all system items
- Supporting all coverage types (percentage, fixed amount, full, excluded)
- Providing clear validation and error messages
- Eliminating manual data entry and reducing errors

Follow this guide to efficiently configure coverage rules for your insurance plans.
