<?php

// Ujumbe wa moduli ya mitihani (App\Http\Controllers\Api\Exam, App\Services\Exam).
// Funguo ni zile zile za lang/en/exams.php.
return [
    'created' => 'Mtihani ":name" umeundwa.',
    'updated' => 'Mtihani ":name" umesasishwa.',
    'deleted' => 'Mtihani ":name" umefutwa.',
    'opened' => 'Mtihani ":name" umefunguliwa: walimu sasa wanaweza kuingiza alama.',
    'published' => 'Matokeo ya ":name" yamechapishwa kwa wazazi.',
    'unpublished' => 'Matokeo ya ":name" yameondolewa kwa wazazi.',

    'division' => [
        'I' => 'Daraja la I',
        'II' => 'Daraja la II',
        'III' => 'Daraja la III',
        'IV' => 'Daraja la IV',
        '0' => 'Daraja la 0',
    ],

    'state' => [
        'already_open' => 'Mtihani huu tayari umefunguliwa.',
        'already_published' => 'Mtihani huu tayari umechapishwa.',
        'open_first' => 'Fungua mtihani kabla ya kufanya hili.',
        'not_open' => 'Mtihani huu haujafunguliwa kwa ajili ya alama.',
        'only_published' => 'Ni mtihani uliochapishwa tu unaoweza kuondolewa.',
        'reason_needed' => 'Toa sababu kabla ya kuondoa matokeo yaliyochapishwa.',
        'results_published' => 'Matokeo yamechapishwa, kwa hiyo alama haziwezi kubadilishwa tena.',
        'published_locked' => 'Mtihani huu umechapishwa na haiwezekani kuwa :action.',
        'action_edited' => 'umehaririwa',
        'action_deleted' => 'umefutwa',
    ],

    'err' => [
        'bands_invalid' => 'Kila kiwango cha alama kinahitaji maoni ya mwalimu wa darasa na ya mwalimu mkuu.',
        'class_has_marks' => 'Alama tayari zimerekodiwa kwa :classes, kwa hiyo haziwezi kuondolewa kwenye mtihani huu.',
        'class_not_in_exam' => 'Mtihani ":exam" haufanywi na :class.',
        'combined_weights' => 'Uzito wa mitihani iliyounganishwa lazima ujumlishe 100.',
        'comments_class_not_yours' => 'Wewe si mwalimu wa darasa hili.',
        'comments_locked' => 'Maoni ya darasa hili yamewasilishwa na hayawezi kubadilishwa tena.',
        'comments_missing' => 'Wanafunzi :count bado hawana maoni.',
        'comments_no_pupils' => 'Darasa hili halina wanafunzi wa kutolea maoni.',
        'comments_not_signed' => 'Mwalimu wa darasa bado hajawasilisha maoni.',
        'comments_pending' => 'Maoni bado yanasubiri idhini katika: :classes.',
        'comments_teacher_first' => 'Mwalimu wa darasa lazima awasilishe maoni kwanza.',
        'deadline_passed' => 'Tarehe ya mwisho ya kuingiza alama ilikuwa :date.',
        'duplicate_student' => 'Mwanafunzi huyo huyo ameonekana zaidi ya mara moja.',
        'empty' => 'Mtihani huu bado hauna alama.',
        'fees_hold' => 'Matokeo yamezuiwa hadi ada ya shule ilipwe. Tafadhali wasiliana na shule.',
        'import_header' => 'Faili linahitaji safu za "namba ya usajili" na moja kwa kila somo.',
        'import_none' => 'Hakuna alama zilizopatikana kwenye faili.',
        'import_problems' => 'Safu :count hazikuweza kusomwa.',
        'incomplete' => 'Alama :count bado hazijaingizwa.',
        'marks_over' => 'Safu :position: alama haziwezi kuzidi :max.',
        'no_classes' => 'Chagua angalau darasa moja kwa mtihani huu.',
        'no_pupils' => 'Darasa hili halina wanafunzi.',
        'no_subjects' => 'Darasa hili bado halina masomo.',
        'not_a_teacher' => 'Mtumiaji huyu si mwalimu.',
        'not_enrolled' => 'Mwanafunzi huyu hayuko darasani kwa sasa.',
        'not_submitted' => 'Alama hizi hazijawasilishwa.',
        'out_of_locked' => 'Alama kamili haziwezi kubadilishwa baada ya alama kuingizwa.',
        'student_not_in_class' => 'Mwanafunzi huyu hayuko :class.',
        'subject_not_in_class' => ':subject halifundishwi katika :class.',
        'subjects_not_in_classes' => 'Baadhi ya masomo hayafundishwi katika madarasa yaliyochaguliwa.',
        'submit_incomplete' => 'Ingiza alama (au weka hakuhudhuria) kwa kila mwanafunzi kabla ya kuwasilisha.',
        'submitted_locked' => 'Alama hizi zimewasilishwa na zimefungwa.',
        'teacher_not_assigned' => 'Hujapangiwa kufundisha :subject katika :class.',
    ],

    'marks' => [
        'saved' => ':count zimehifadhiwa',
        'cleared' => ':count zimefutwa',
        'nothing' => 'hakuna kilichobadilika',
        'summary' => ':subject: :parts.',
        'imported' => 'Alama :count zimeingizwa.',
        'submitted' => 'Alama za :subject katika :class zimewasilishwa.',
        'reopened' => 'Alama za :subject katika :class zimefunguliwa tena kwa mabadiliko.',
    ],

    'comments' => [
        'saved' => 'Maoni yamehifadhiwa.',
        'applied' => 'Maoni :count yamejazwa kutoka viwango vya alama.',
        'bands_saved' => 'Viwango vya maoni vimehifadhiwa.',
        'reopened' => 'Maoni ya :class yamefunguliwa tena.',
    ],

    'class_subjects' => [
        'now_has' => ':class sasa ina masomo :count.',
        'cannot_remove' => ':names tayari yana alama katika :class na hayawezi kuondolewa.',
    ],

    'subject' => [
        'added' => 'Somo ":name" limeongezwa.',
        'updated' => 'Somo ":name" limesasishwa.',
        'deleted' => 'Somo ":name" limefutwa.',
        'has_results' => 'Somo ":name" tayari lina matokeo na haliwezi kufutwa.',
    ],

    'teachers' => [
        'saved' => ':name sasa anafundisha masomo :count.',
    ],

    'remind' => [
        'title' => 'Alama bado hazijaingizwa — :exam',
        'body' => 'Tafadhali ingiza alama za :exam. Zinazokosekana — :list.',
        'sent' => 'Ukumbusho umetumwa kwa walimu :count.',
        'none' => 'Hakuna mwenye alama zinazosubiriwa.',
    ],

    'notify' => [
        'published_title' => 'Matokeo yamechapishwa — :exam',
        'published_plain' => 'Matokeo ya :exam (:term) yako tayari.',
        'published_by' => 'Matokeo ya :exam (:term) yamechapishwa na :user.',
        'sms' => ':school: matokeo ya :exam ya :student yako tayari. Ingia ShulePay kuyaona.',
    ],

    'parent' => [
        'no_results' => 'Hakuna matokeo yaliyochapishwa kwa mwanafunzi huyu.',
    ],

    'validation' => [
        'absent_boolean' => 'Kutohudhuria lazima kuwe ndiyo au hapana.',
        'absent_with_marks' => 'Safu :position: mwanafunzi aliyewekwa hakuhudhuria hawezi kuwa na alama.',
        'all_classes_required' => 'Eleza kama mtihani ni wa madarasa yote.',
        'class_distinct' => 'Darasa limechaguliwa mara mbili.',
        'class_invalid' => 'Darasa lililochaguliwa halipo.',
        'class_missing' => 'Darasa halikupatikana.',
        'class_required' => 'Chagua darasa.',
        'classes_required' => 'Chagua madarasa yanayofanya mtihani huu.',
        'comment_max' => 'Maoni yanaweza kuwa na herufi 500 kwa kiwango cha juu.',
        'date_invalid' => 'Weka tarehe sahihi.',
        'deadline_before' => 'Tarehe ya mwisho ya alama haiwezi kuwa kabla mtihani kuanza.',
        'end_before_start' => 'Tarehe ya mwisho haiwezi kuwa kabla ya tarehe ya kuanza.',
        'entries_max' => 'Maingizo mengi mno yametumwa kwa mara moja.',
        'marks_decimals' => 'Alama zinaweza kuwa na desimali mbili kwa kiwango cha juu.',
        'marks_negative' => 'Alama haziwezi kuwa hasi.',
        'marks_numeric' => 'Alama lazima ziwe namba.',
        'name_max' => 'Jina linaweza kuwa na herufi 150 kwa kiwango cha juu.',
        'name_required' => 'Weka jina la mtihani.',
        'name_unique' => 'Mtihani wenye jina hili tayari upo katika muhula huu.',
        'no_marks' => 'Hakuna alama za kuhifadhi.',
        'out_of' => 'Alama kamili lazima ziwe namba nzima kuanzia 1 hadi 1000.',
        'reason_required' => 'Toa sababu.',
        'remark_max' => 'Maelezo yanaweza kuwa na herufi 255 kwa kiwango cha juu.',
        'student_missing' => 'Mwanafunzi hakupatikana.',
        'subject_invalid' => 'Somo lililochaguliwa halipo.',
        'subject_required' => 'Chagua somo.',
        'term_invalid' => 'Muhula uliochaguliwa si wa shule hii.',
        'term_required' => 'Chagua muhula.',
        'type_in' => 'Aina ya mtihani lazima iwe mojawapo ya: :types.',
        'type_required' => 'Chagua aina ya mtihani.',
    ],

    // Maoni yaliyoandaliwa kwa kila kiwango cha alama (shule inaweza kuyabadilisha kwenye mipangilio ya maoni).
    'bands' => [
        'low' => [
            'teacher' => 'Anahitaji kujitahidi zaidi na kuomba msaada katika masomo yaliyo dhaifu.',
            'head' => 'Ufaulu uko chini ya matarajio; msaada wa ziada na ufuatiliaji wa karibu vinahitajika.',
        ],
        'mid' => [
            'teacher' => 'Maendeleo ya wastani. Kwa mazoezi zaidi na umakini, matokeo bora yanawezekana.',
            'head' => 'Matokeo ya wastani. Endelea kujituma na lenga juu zaidi muhula ujao.',
        ],
        'high' => [
            'teacher' => 'Kazi nzuri sana. Endelea na juhudi na nidhamu njema.',
            'head' => 'Ufaulu mzuri sana. Hongera, endelea hivyo.',
        ],
    ],
];
