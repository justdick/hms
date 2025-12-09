# Mittag to HMS Migration Guide

## Overview

This guide covers migrating data from the old Mittag system to the new HMS system.

## Pre-Migration Setup

### 1. Configure Database Connection

Add to `config/database.php` (already configured):
```php
'mittag_old' => [
    'driver' => 'mysql',
    'host' => env('MITTAG_DB_HOST', '127.0.0.1'),
    'database' => env('MITTAG_DB_DATABASE', 'mittag_old'),
    'username' => env('MITTAG_DB_USERNAME', 'root'),
    'password' => env('MITTAG_DB_PASSWORD', ''),
    // ... other settings
],
```

### 2. Add Environment Variables

Add to `.env`:
```
MITTAG_DB_HOST=127.0.0.1
MITTAG_DB_DATABASE=mittag_old
MITTAG_DB_USERNAME=root
MITTAG_DB_PASSWORD=
```

### 3. Import Mittag Database

```bash
# Create the database
mysql -u root -e "CREATE DATABASE mittag_old;"

# Import the backup
mysql -u root mittag_old < backup_mittag.sql
```

### 4. Run HMS Migrations

Ensure the `mittag_migration_logs` table exists:
```bash
php artisan migrate
```

---

## Running the Migration

### Single Command (Recommended)

```bash
php artisan migrate:all-from-mittag
```

This runs all 13 migrations in the correct order with progress tracking.

### Options

```bash
# Dry run - see what would happen without inserting data
php artisan migrate:all-from-mittag --dry-run

# Resume from a specific step if interrupted
php artisan migrate:all-from-mittag --from=prescriptions

# Run only specific migrations
php artisan migrate:all-from-mittag --only=patients,checkins,consultations
```

---

## Migration Order

The migrations run in this exact sequence (dependencies matter!):

| Step | Migration | Depends On |
|------|-----------|------------|
| 1 | Patients | - |
| 2 | Drugs | - |
| 3 | Check-ins | Patients |
| 4 | OPD Vitals | Check-ins |
| 5 | Consultations | Check-ins |
| 6 | Prescriptions | Consultations, Drugs |
| 7 | Lab Services | - |
| 8 | Lab Orders + Results | Consultations, Lab Services |
| 9 | Admissions | Patients, Check-ins |
| 10 | IPD Vitals | Admissions |
| 11 | Ward Rounds | Admissions |
| 12 | Nursing Notes | Admissions |
| 13 | Patient Insurance | Patients |

---

## Individual Migration Commands

If you need to run migrations individually:

```bash
# 1. Patients (run first - everything depends on this)
php artisan migrate:patients-from-mittag

# 2. Drugs
php artisan migrate:drugs-from-mittag

# 3. Check-ins
php artisan migrate:checkins-from-mittag

# 4. OPD Vitals
php artisan migrate:vitals-from-mittag --source=opd

# 5. Consultations
php artisan migrate:consultations-from-mittag

# 6. Prescriptions
php artisan migrate:prescriptions-from-mittag

# 7. Lab Services
php artisan migrate:lab-services-from-mittag

# 8. Lab Orders (includes results)
php artisan migrate:lab-orders-from-mittag

# 9. Admissions
php artisan migrate:admissions-from-mittag

# 10. IPD Vitals
php artisan migrate:vitals-from-mittag --source=ipd

# 11. Ward Rounds
php artisan migrate:ward-rounds-from-mittag

# 12. Nursing Notes
php artisan migrate:nursing-notes-from-mittag

# 13. Patient Insurance
php artisan migrate:patient-insurance-from-mittag
```

---

## Expected Data Volumes

| Entity | Approximate Count |
|--------|-------------------|
| Patients | ~15,000 |
| Check-ins | ~51,000 |
| Consultations | ~50,000 |
| Prescriptions | ~196,000 |
| OPD Vitals | ~44,000 |
| Admissions | ~10,000 |
| Ward Rounds | ~16,000 |
| Nursing Notes | ~108,000 |
| Lab Orders | ~9,000 |
| IPD Vitals | ~14,000 |

**Total: ~500,000+ records**

---

## Time Estimates

- Full migration: **30-60 minutes** (depending on server)
- Prescriptions alone: ~10-15 minutes (largest dataset)

---

## Re-Running Migrations

All migrations are **idempotent** - they skip already-migrated records:

- Each migration logs success/failure to `mittag_migration_logs` table
- Re-running will only process new/failed records
- Safe to run multiple times

---

## Monitoring Progress

### During Migration
Each command shows a progress bar:
```
 50748/50748 [============================] 100%
Migration completed:
  ✓ Migrated: 50743
  ⊘ Skipped:  0
  ✗ Failed:   5
```

### After Migration
Check the migration logs:
```sql
SELECT entity_type, status, COUNT(*) as count 
FROM mittag_migration_logs 
GROUP BY entity_type, status 
ORDER BY entity_type;
```

---

## Troubleshooting

### Memory Issues
The migration commands are configured with:
- `memory_limit = 1G`
- `max_execution_time = 0` (unlimited)

If you still hit memory issues, run migrations individually.

### Connection Timeout
If the database connection times out:
1. Check `wait_timeout` in MySQL config
2. Run migrations in smaller batches using `--limit` option

### Failed Records
Check failure reasons:
```sql
SELECT entity_type, notes, COUNT(*) as count 
FROM mittag_migration_logs 
WHERE status = 'failed' 
GROUP BY entity_type, notes 
ORDER BY count DESC;
```

Common failures:
- **"Patient not found"** - Orphaned records in Mittag
- **"Checkin not found"** - Missing parent check-in
- **"No admission found"** - Missing parent admission

These are usually bad data in Mittag, not migration bugs.

---

## Post-Migration Checklist

- [ ] Verify patient count matches
- [ ] Spot-check some patient records in UI
- [ ] Verify consultations have prescriptions
- [ ] Check lab orders have results
- [ ] Run application tests: `php artisan test`
- [ ] Remove `mittag_old` database connection (optional)

---

## Rollback

To start fresh:
```sql
-- Clear migration logs
TRUNCATE mittag_migration_logs;

-- Clear migrated data (careful!)
DELETE FROM prescriptions WHERE migrated_from_mittag = 1;
DELETE FROM lab_orders WHERE migrated_from_mittag = 1;
DELETE FROM nursing_notes WHERE migrated_from_mittag = 1;
-- ... etc for other tables
```

---

## Support

Check Laravel logs for detailed error messages:
```bash
tail -f storage/logs/laravel.log
```
