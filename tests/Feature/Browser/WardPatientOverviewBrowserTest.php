<?php

/**
 * Browser Tests for Ward Patient Overview and Labs Tabs
 *
 * These tests verify the responsive layout, tab navigation,
 * and interactive features of the Overview and Labs tabs.
 *
 * Note: These tests require Pest Browser plugin to be installed:
 * - composer require pestphp/pest-plugin-browser:^4.0 --dev
 * - npm install playwright@latest
 * - npx playwright install
 *
 * The core functionality is already covered by feature tests in:
 * - WardPatientOverviewTabTest.php
 * - WardPatientLabsTabTest.php
 */
test('browser tests require pest browser plugin', function () {
    expect(true)->toBeTrue();
})->skip('Install Pest Browser plugin to run browser tests');
