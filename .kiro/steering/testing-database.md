---
inclusion: always
---

# Testing Database Rules

## ⚠️ CRITICAL: Test Database Protection

**NEVER run tests against the production/development database (`hms3`).**

Tests MUST use the dedicated test database: `hmst_testing`

## Before Running Any Tests

1. **ALWAYS clear config cache first** — cached config ignores phpunit.xml env vars:
   ```bash
   php artisan config:clear
   ```
2. **Check if `hmst_testing` database exists** before running tests
3. **If it doesn't exist, STOP and ask the user** to create it:
   ```sql
   CREATE DATABASE hmst_testing;
   ```
4. **Never proceed with tests** if the test database doesn't exist

## Test Commands

When running tests, always clear config cache and verify the test database first:

```bash
# Step 1: ALWAYS clear config cache (MANDATORY - prevents tests hitting production DB)
php artisan config:clear

# Step 2: Check if test database exists
mysql -u root -p -e "SHOW DATABASES LIKE 'hmst_testing'"

# Step 3: Only run tests after confirming hmst_testing exists
php artisan test
php artisan test --filter=SomeTest
```

## ⚠️ Why Config Cache Must Be Cleared

When Laravel's config is cached (`bootstrap/cache/config.php` exists), the `phpunit.xml` `<env>` values are **completely ignored**. This means tests will run against the production database instead of `hmst_testing`. Always run `php artisan config:clear` before any test execution.

## Configuration

The test database is configured in `phpunit.xml`:
```xml
<env name="DB_DATABASE" value="hmst_testing"/>
```

## Why This Matters

- Tests use `RefreshDatabase` trait which **wipes and re-migrates** the database
- Running tests without `hmst_testing` will destroy all data in the main database
- Production/development data is irreplaceable without backups

---

**Remember**: Always ask the user before running tests if unsure about the test database status.
