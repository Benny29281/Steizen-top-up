<?php

return [
    'invoice_minutes' => 60,
    'tripay' => [
        // Integrasi saat ini sengaja hanya mengakses endpoint sandbox.
        'api_key' => env('TRIPAY_API_KEY'),
        'private_key' => env('TRIPAY_PRIVATE_KEY'),
        'merchant_code' => env('TRIPAY_MERCHANT_CODE'),
        'callback_url' => env('TRIPAY_CALLBACK_URL'),
    ],
];
