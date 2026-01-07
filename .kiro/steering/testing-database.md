---
inclusion: always
---

# Testing Database Rules

## ⚠️ CRITICAL: Test Database Protection

**NEVER run tests against the production/development database (`hms3`).**

Tests MUST use the dedicated test database: `hmst_testing`

## Before Running Any Tests

1. **Check if `hmst_testing` database exists** before running tests
2. **If it doesn't exist, STOP and ask the user** to create it:
   ```sql
   CREATE DATABASE hmst_testing;
   ```
3. **Never proceed with tests** if the test database doesn't exist

## Test Commands

When running tests, always verify the test database first:

```bash
# Check if test database exists (run this first)
mysql -u root -p -e "SHOW DATABASES LIKE 'hmst_testing'"

# Only run tests after confirming hmst_testing exists
php artisan test
php artisan test --filter=SomeTest
```

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
