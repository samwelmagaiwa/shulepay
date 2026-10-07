<?php

/**
 * Labels for the printed documents (receipts, statements, invoices).
 *
 * These were written straight into the Blade templates in Swahili, so a receipt
 * stayed Swahili even for a user working in English. The wording here is the
 * original wording — printed documents families already recognise must not
 * change just because they became translatable.
 */

return [
    // Receipt
    'receipt_no' => 'NAMBA YA RISITI',
    'date' => 'TAREHE',
    'student' => 'Mwanafunzi',
    'admission_no' => 'Namba ya Usajili',
    'class' => 'Darasa',
    'guardian' => 'Mzazi / Mlezi',
    'invoice' => 'Ankara',
    'term' => 'Muhula',
    'academic_year' => 'Mwaka wa Masomo',
    'paid_at' => 'Tarehe ya Malipo',
    'method' => 'Njia ya Malipo',
    'reference' => 'Kumbukumbu',
    'description' => 'Maelezo',
    'amount' => 'Kiasi',
    'amount_paid_caps' => 'KIASI KILICHOLIPWA',
    'invoice_total' => 'Jumla ya Ankara',
    'total_paid' => 'Jumla Iliyolipwa',
    'balance_caps' => 'SALIO',
    'received_by' => 'Imepokelewa na',

    // Statement
    'statement_no' => 'TAARIFA NA.',
    'paid_column' => 'Alicholipa',
    'balance' => 'Salio',
    'all_invoices_total' => 'Jumla ya Ankara Zote',
    'grand_balance' => 'SALIO LA JUMLA (MADENI YOTE)',
    'no_invoices' => 'Hakuna ankara.',
    'all_paid' => '✓ ANKARA ZOTE ZIMELIPWA',

    // Fee statement
    'school' => 'Shule',
    'year' => 'Mwaka',
    'status' => 'Hali',
    'paid' => 'Imelipwa',
    'total' => 'Jumla',
    'fees_total' => 'Jumla ya Ada',
    'no_invoices_for_student' => 'Hakuna ankara kwa mwanafunzi huyu.',
    'all_fees_paid' => '✓ ADA YOTE IMELIPWA',
];
