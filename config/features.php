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

];
