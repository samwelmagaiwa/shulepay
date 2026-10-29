<?php

return [
    'driver' => env('SMS_DRIVER', 'kilakona'),
    // Everything: payment receipts, absence alerts, reminders, blasts.
    'enabled' => env('SMS_ENABLED', false),
    // Only one-time security codes (account activation, two-factor). On by default so sign-in works even while
    // the general switch above is off; when it is off the codes are only written to the server log.
    'codes_enabled' => env('SMS_CODES_ENABLED', true),

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
