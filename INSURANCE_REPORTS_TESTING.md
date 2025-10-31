# Insurance Reports Testing Guide

This guide covers testing the Insurance Reports system using the comprehensive test data seeder and the Playwright MCP for browser-based UI testing.

## Database Seeder

### Overview

The `InsuranceReportsSeeder` creates comprehensive test data for all 6 insurance report pages:
- Claims Summary
- Revenue Analysis
- Outstanding Claims
- Vetting Performance
- Utilization Report
- Rejection Analysis

### What Gets Created

**Test Data for Last 6 Months:**
- 50 patients with active insurance coverage
- 300-480 insurance claims with various statuses
- 150-240 cash charges for revenue comparison
- Realistic vetting workflows with timestamps
- 10 different rejection reasons for rejected claims
- Service items linked to charges

### Running the Seeder

```bash
php artisan db:seed --class=InsuranceReportsSeeder
```

### Verifying Data

```bash
php artisan tinker
>>> App\Models\InsuranceClaim::count()
>>> App\Models\InsuranceClaim::where('status', 'rejected')->count()
```

## Testing with Playwright MCP

### Quick Start

1. Seed database: `php artisan db:seed --class=InsuranceReportsSeeder`
2. Start server: `composer run dev`
3. Ensure user has insurance permissions
4. Use Playwright MCP tools to test the UI

### Key Test Scenarios

See the reports at `/admin/insurance/reports/*` for testing each page.
