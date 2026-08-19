<?php

return [
    /*
    |--------------------------------------------------------------------------
    | SMS Template Presets
    |--------------------------------------------------------------------------
    |
    | These templates are exposed to the frontend for use in the SmsBlastModal.
    | Variables: [Jina], [Kiasi], [Tarehe], [Shule]
    |
    */

    'blast_default' => 'Habari [Jina]! Ada yako ya TZS [Kiasi] bado haijalipiwa. Mwisho: [Tarehe]. Tafadhali lipa haraka. [Shule]',
    'reminder_soft' => 'ShulePay: Kumbusho - Ada ya [Jina] ya TZS [Kiasi] inaisha [Tarehe]. Lipa mapema!',
    'reminder_hard' => 'ShulePay: MUHIMU - Ada ya [Jina] TZS [Kiasi] imepita tarehe [Tarehe]. Lipa leo!',
    'custom' => '', // user writes their own message
];
