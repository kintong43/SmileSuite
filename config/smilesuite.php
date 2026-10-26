<?php

return [
    'sandbox' => env('SMILESUITE_SANDBOX', false),
    'live_billing' => env('LIVE_BILLING_ENABLED', false),
    'live_commerce' => env('LIVE_COMMERCE_ENABLED', false),
    'real_patient_data' => env('REAL_PATIENT_DATA_ENABLED', false),
];
