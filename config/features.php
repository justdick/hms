<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Feature Flags
    |--------------------------------------------------------------------------
    |
    | This file contains feature flags that can be toggled to enable or disable
    | specific features in the application. These flags allow for gradual
    | rollout of new features and easy rollback if needed.
    |
    */

    'simplified_insurance_ui' => env('FEATURE_SIMPLIFIED_INSURANCE_UI', true),

    /*
    |--------------------------------------------------------------------------
    | Bed Management
    |--------------------------------------------------------------------------
    |
    | When enabled, the system tracks individual beds and available bed counts.
    | When disabled, patients are admitted to wards without bed tracking.
    | Useful for hospitals that don't need granular bed management.
    |
    */

    'bed_management' => env('FEATURE_BED_MANAGEMENT', false),

    /*
    |--------------------------------------------------------------------------
    | Vitals Alerts
    |--------------------------------------------------------------------------
    |
    | When enabled, the system generates popup alerts when vitals are due/overdue.
    | When disabled, vitals schedules still work but no alert notifications are
    | generated. Staff can still see when vitals are due on the patient page.
    |
    */

    'vitals_alerts' => env('FEATURE_VITALS_ALERTS', false),

];
