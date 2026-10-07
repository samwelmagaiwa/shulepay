<?php

/**
 * Labels for the printed documents (receipts, statements, invoices, reports).
 *
 * These were written straight into the Blade templates in Swahili, so a receipt
 * stayed Swahili even for a user working in English. The wording of the receipt
 * and statements is the original wording — printed documents families already
 * recognise must not change just because they became translatable.
 *
 * @see lang/en/pdf.php
 */

return [
    // Receipt
    'receipt_title' => 'Risiti ya Malipo',
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
    'other_items' => 'Vipengele vingine (:count)',
    'amount_paid_caps' => 'KIASI KILICHOLIPWA',
    'invoice_total' => 'Jumla ya Ankara',
    'total_paid' => 'Jumla Iliyolipwa',
    'balance_caps' => 'SALIO',
    'receipt_settled' => '✓ ANKARA IMELIPWA YOTE',
    'received_by' => 'Imepokelewa na',
    'receipt_footer' => 'Asante kwa malipo yako. Hati hii ni ushahidi wa malipo.',

    // Statement
    'full_statement_title' => 'Taarifa ya Malipo Yote (Ankara Zote)',
    'statement_no' => 'TAARIFA NA.',
    'paid_column' => 'Alicholipa',
    'balance' => 'Salio',
    'all_invoices_total' => 'Jumla ya Ankara Zote',
    'grand_balance' => 'SALIO LA JUMLA (MADENI YOTE)',
    'no_invoices' => 'Hakuna ankara.',
    'all_paid' => '✓ ANKARA ZOTE ZIMELIPWA',
    'earlier_invoices' => 'Ankara za awali (:count)',
    'invoice_colon' => 'Ankara:',
    'status_paid' => 'Amelipa',
    'status_partial' => 'Amelipa Kiasi',
    'status_unpaid' => 'Hajalipa',
    'full_statement_footer' => 'Taarifa hii inaonyesha ankara zote za mwanafunzi huyu na malipo yaliyofanyika.',

    // Fee statement
    'fee_statement_title' => 'Taarifa ya Ada',
    'issued' => 'Imetolewa',
    'school' => 'Shule',
    'year' => 'Mwaka',
    'status' => 'Hali',
    'paid' => 'Imelipwa',
    'total' => 'Jumla',
    'fees_total' => 'Jumla ya Ada',
    'no_invoices_for_student' => 'Hakuna ankara kwa mwanafunzi huyu.',
    'all_fees_paid' => '✓ ADA YOTE IMELIPWA',
    'badge_paid' => 'IMELIPWA',
    'badge_partial' => 'SEHEMU',
    'badge_unpaid' => 'HAIJALIPWA',
    'fee_statement_footer' => 'Hati hii imetolewa na mfumo na ni sahihi bila saini.',

    // Bulk print
    'no_matching_students' => 'Hakuna wanafunzi wanaolingana na kigezo hiki.',

    // Reports (shared)
    'generated' => 'Imetengenezwa',
    'as_of' => 'Hadi :date',
    'period_range' => ':from hadi :to',
    'period' => 'Kipindi',
    'report_footer' => 'ShulePay — Mfumo wa Usimamizi wa Ada',
    'total_caps' => 'JUMLA',
    'count' => 'Idadi',

    // Collections report
    'collections_title' => 'Ripoti ya Makusanyo ya Ada',
    'summary' => 'Muhtasari',
    'total_payments' => 'Jumla ya Malipo',
    'total_collected' => 'Jumla Iliyokusanywa',
    'invoices' => 'Ankara',
    'partial' => 'Kiasi',
    'unpaid' => 'Hazijalipwa',
    'collections_by_period' => 'Makusanyo kwa Kipindi',
    'payments' => 'Malipo',
    'amount_tzs' => 'Kiasi (TZS)',
    'collections_by_method' => 'Makusanyo kwa Njia ya Malipo',
    'method_column' => 'Njia',
    'collections_by_class' => 'Makusanyo kwa Darasa',

    // Debtor aging report
    'debtor_aging_title' => 'Ripoti ya Umri wa Madeni',
    'total_debtors' => 'Jumla ya Wadaiwa',
    'total_outstanding' => 'Jumla ya Deni',
    'bucket_current' => 'Sasa (Bado Hazijafika Muda)',
    'bucket_days_1_30' => 'Siku 1–30 Zimepita',
    'bucket_days_31_60' => 'Siku 31–60 Zimepita',
    'bucket_days_61_90' => 'Siku 61–90 Zimepita',
    'bucket_over_90' => 'Zaidi ya Siku 90 Zimepita',
    'students_count' => 'wanafunzi :count',
    'student_name' => 'Jina la Mwanafunzi',
    'oldest_invoice_date' => 'Tarehe ya Ankara ya Zamani',
    'outstanding_tzs' => 'Deni (TZS)',
    'no_students_in_bucket' => 'Hakuna wanafunzi katika kundi hili.',

    // Income statement
    'income_statement_title' => 'Taarifa ya Mapato na Matumizi',
    'revenue' => 'MAPATO',
    'fee_collections' => 'Makusanyo ya Ada',
    'total_revenue' => 'Jumla ya Mapato',
    'expenses' => 'MATUMIZI',
    'payroll' => 'Mishahara',
    'total_expenses' => 'Jumla ya Matumizi',
    'net_income' => 'FAIDA HALISI',
    'net_loss' => 'HASARA HALISI',

    // Balance sheet
    'balance_sheet_title' => 'Mizania ya Hesabu',
    'assets' => 'MALI',
    'cash_and_bank' => 'Fedha Taslimu na Benki',
    'receivables' => 'Fedha Zinazodaiwa',
    'fixed_assets' => 'Mali za Kudumu',
    'total_assets' => 'JUMLA YA MALI',
    'liabilities' => 'MADENI',
    'payables' => 'Madeni Yanayolipwa',
    'total_liabilities' => 'JUMLA YA MADENI',
    'equity' => 'MTAJI',
    'retained_earnings' => 'Faida Iliyobaki / Salio la Mfuko',
    'total_equity' => 'JUMLA YA MTAJI',
    'liabilities_plus_equity' => 'MADENI + MTAJI',

    // Student fee statement report
    'student_statement_title' => 'Taarifa ya Ada ya Mwanafunzi',
    'invoice_history' => 'Historia ya Ankara na Malipo',
    'invoice_no' => 'Namba ya Ankara',
    'due_date' => 'Tarehe ya Mwisho',
    'gross_tzs' => 'Jumla (TZS)',
    'paid_tzs' => 'Kilicholipwa (TZS)',
    'balance_tzs' => 'Salio (TZS)',
    'ref' => 'Kumb',
    'total_invoiced' => 'Jumla ya Ankara',
    'outstanding_balance' => 'Deni Lililobaki',
    'accountant_signature' => 'Saini na Muhuri wa Mhasibu',
    'guardian_signature' => 'Saini ya Mzazi / Mlezi',
    'computer_generated' => 'Taarifa hii imetengenezwa na kompyuta.',

    // Clearance certificate
    'clr_title' => 'Cheti cha Usafi wa Madeni',
    'clr_subtitle' => 'Uthibitisho wa Usafi wa Madeni',
    'clr_school_fallback' => 'Jina la Shule',
    'clr_this_school' => 'shule hii',
    'clr_reg_no' => 'Namba ya Usajili',
    'clr_est' => 'Ilianzishwa',
    'clr_ref' => 'Kumb.',
    'clr_date' => 'Tarehe',
    'clr_intro' => 'Hii ni kuthibitisha kwamba mwanafunzi aliyetajwa hapa chini amefanya <strong>malipo yote ya shule</strong> kwa mwaka wa masomo ulioonyeshwa na hana deni lolote katika vitabu vya mahesabu ya <strong>:school</strong>.',
    'clr_photo' => "Picha ya\nPasipoti",
    'clr_full_name' => 'Jina Kamili',
    'clr_gender' => 'Jinsia',
    'clr_dob' => 'Tarehe ya Kuzaliwa',
    'clr_male' => 'Kiume',
    'clr_female' => 'Kike',
    'clr_note' => 'Cheti hiki kimetolewa kwa madhumuni ya kuthibitisha usafi wa madeni ya shule peke yake. Halali tu ikiwa ina muhuri rasmi wa shule na saini ya mwenye mamlaka.',
    'clr_issued_by' => 'Imetolewa na',
    'clr_system' => 'Mfumo',
    'clr_accountant' => 'Mhasibu',
    'clr_principal' => 'Mkuu wa Shule',
    'clr_signature_date' => 'Saini na Tarehe',
    'clr_stamp' => "Muhuri\nRasmi",
    'clr_watermark' => 'Imetengenezwa kidijitali na ShulePay • :year • Hati hii imethibitishwa na mfumo na haihitaji nambari ya nakala ya mkono.',
];
