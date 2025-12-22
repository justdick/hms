<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;

abstract class TestCase extends BaseTestCase
{
    /**
     * Boot the testing helper traits.
     * Includes safety check to prevent tests from running against production database.
     */
    protected function setUpTraits(): array
    {
        $this->preventProductionDatabaseAccess();

        return parent::setUpTraits();
    }

    /**
     * Prevent tests from running against the production database.
     * This is a safety measure to protect production data.
     */
    protected function preventProductionDatabaseAccess(): void
    {
        $currentDatabase = DB::connection()->getDatabaseName();
        $productionDatabase = 'hmst'; // Your production database name

        if ($currentDatabase === $productionDatabase) {
            $this->fail(
                "SAFETY CHECK FAILED: Tests are attempting to run against the production database '{$productionDatabase}'. ".
                "Tests should run against 'hmst_testing'. ".
                "Please check your phpunit.xml configuration and run 'php artisan config:clear'."
            );
        }
    }
}
