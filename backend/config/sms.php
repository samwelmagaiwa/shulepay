<?php

return [
    'driver' => env('SMS_DRIVER', 'kilakona'),
    'enabled' => env('SMS_ENABLED', false),

    'kilakona' => [
        'api_url' => env('SMS_API_URL', 'https://messaging.kilakona.co.tz/api/v1/vendor/message/send'),
        'api_key' => env('SMS_API_KEY', ''),
        'secret_key' => env('SMS_SECRET_KEY', ''),
        'sender_id' => env('SMS_SENDER_ID', 'ShulePay'),
        'country_code' => env('SMS_COUNTRY_CODE', '255'),
    ],

    'beem' => [
        'api_key' => env('BEEM_API_KEY', ''),
        'secret_key' => env('BEEM_SECRET_KEY', ''),
        'sender_name' => env('BEEM_SENDER_ID', 'ShulePay'),
    ],
];
