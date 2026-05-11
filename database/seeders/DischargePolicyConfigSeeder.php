<?php

namespace Database\Seeders;

use App\Models\BillingConfiguration;
use Illuminate\Database\Seeder;

class DischargePolicyConfigSeeder extends Seeder
{
    /**
     * Seed the admission discharge policy configuration.
     *
     * Modes:
     *  - allow: discharge freely, no balance check
     *  - warn:  allow discharge with balance, require acknowledgement + reason
     *  - block: hard-block discharge with outstanding balance unless user has
     *           the billing.override-discharge-block permission
     */
    public function run(): void
    {
        // Only create if it doesn't already exist so admins can change it
        // via the UI and re-seeding won't clobber their choice.
        $exists = BillingConfiguration::where('key', 'admission.discharge_policy')->exists();

        if (! $exists) {
            BillingConfiguration::setValue(
                key: 'admission.discharge_policy',
                value: 'warn',
                category: 'admissions',
                description: 'Controls what happens when discharging a patient with an outstanding balance. Allow: no check. Warn: allowed with acknowledgement. Block: disallowed unless user has override permission.'
            );
        }
    }
}
