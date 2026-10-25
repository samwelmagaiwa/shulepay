<?php

// Messages of the exam module (App\Http\Controllers\Api\Exam, App\Services\Exam).
// Same keys as lang/sw/exams.php.
return [
    'created' => 'Exam ":name" has been created.',
    'updated' => 'Exam ":name" has been updated.',
    'deleted' => 'Exam ":name" has been deleted.',
    'opened' => 'Exam ":name" is open: teachers can now enter marks.',
    'published' => 'Results of ":name" have been published to parents.',
    'unpublished' => 'Results of ":name" have been withdrawn from parents.',

    'division' => [
        'I' => 'Division I',
        'II' => 'Division II',
        'III' => 'Division III',
        'IV' => 'Division IV',
        '0' => 'Division 0',
    ],

    'state' => [
        'already_open' => 'This exam is already open.',
        'already_published' => 'This exam has already been published.',
        'open_first' => 'Open the exam before doing this.',
        'not_open' => 'This exam is not open for marks.',
        'only_published' => 'Only a published exam can be withdrawn.',
        'reason_needed' => 'Give a reason before withdrawing published results.',
        'results_published' => 'The results are published, so marks can no longer be changed.',
        'published_locked' => 'This exam is published and cannot be :action.',
        'action_edited' => 'edited',
        'action_deleted' => 'deleted',
    ],

    'err' => [
        'bands_invalid' => 'Every score band needs a comment for the class teacher and one for the head teacher.',
        'class_has_marks' => 'Marks are already recorded for :classes, so they cannot be removed from this exam.',
        'class_not_in_exam' => 'Exam ":exam" is not sat by :class.',
        'combined_weights' => 'The weights of the combined exams must add up to 100.',
        'comments_class_not_yours' => 'You are not the class teacher of this class.',
        'comments_locked' => 'The comments of this class have been submitted and can no longer be changed.',
        'comments_missing' => ':count pupils still have no comment.',
        'comments_no_pupils' => 'This class has no pupils to comment on.',
        'comments_not_signed' => 'The class teacher has not submitted the comments yet.',
        'comments_pending' => 'Comments are still waiting for approval in: :classes.',
        'comments_teacher_first' => 'The class teacher must submit the comments first.',
        'deadline_passed' => 'The deadline for entering marks was :date.',
        'duplicate_student' => 'The same pupil appears more than once.',
        'empty' => 'This exam has no marks yet.',
        'fees_hold' => 'The results are held back until school fees are cleared. Please contact the school.',
        'import_header' => 'The file needs the columns "admission number" and one for each subject.',
        'import_none' => 'No marks were found in the file.',
        'import_problems' => ':count rows could not be read.',
        'incomplete' => ':count marks are still missing.',
        'marks_over' => 'Row :position: marks cannot be more than :max.',
        'no_classes' => 'Choose at least one class for this exam.',
        'no_pupils' => 'This class has no pupils.',
        'no_subjects' => 'This class has no subjects yet.',
        'not_a_teacher' => 'This user is not a teacher.',
        'not_enrolled' => 'This pupil is not in a class at the moment.',
        'not_submitted' => 'These marks have not been submitted.',
        'out_of_locked' => 'The total marks cannot be changed once marks have been entered.',
        'student_not_in_class' => 'This pupil is not in :class.',
        'subject_not_in_class' => ':subject is not taught in :class.',
        'subjects_not_in_classes' => 'Some subjects are not taught in the chosen classes.',
        'submit_incomplete' => 'Enter a mark (or mark absent) for every pupil before submitting.',
        'submitted_locked' => 'These marks have been submitted and are locked.',
        'teacher_not_assigned' => 'You are not assigned to teach :subject in :class.',
    ],

    'marks' => [
        'saved' => ':count saved',
        'cleared' => ':count cleared',
        'nothing' => 'nothing changed',
        'summary' => ':subject: :parts.',
        'imported' => ':count marks imported.',
        'submitted' => 'Marks for :subject in :class were submitted.',
        'reopened' => 'Marks for :subject in :class were reopened for changes.',
    ],

    'comments' => [
        'saved' => 'Comments saved.',
        'applied' => ':count comments filled in from the score bands.',
        'bands_saved' => 'Comment bands saved.',
        'reopened' => 'Comments of :class were reopened.',
    ],

    'class_subjects' => [
        'now_has' => ':class now has :count subjects.',
        'cannot_remove' => ':names already have marks in :class and cannot be removed.',
    ],

    'subject' => [
        'added' => 'Subject ":name" was added.',
        'updated' => 'Subject ":name" was updated.',
        'deleted' => 'Subject ":name" was deleted.',
        'has_results' => 'Subject ":name" already has results and cannot be deleted.',
    ],

    'teachers' => [
        'saved' => ':name now teaches :count subjects.',
    ],

    'remind' => [
        'title' => 'Marks still missing — :exam',
        'body' => 'Please enter the marks for :exam. Still missing — :list.',
        'sent' => 'Reminder sent to :count teachers.',
        'none' => 'Nobody has marks outstanding.',
    ],

    'notify' => [
        'published_title' => 'Results published — :exam',
        'published_plain' => 'The results of :exam (:term) are ready.',
        'published_by' => 'The results of :exam (:term) were published by :user.',
        'sms' => ':school: results of :exam for :student are ready. Log in to ShulePay to see them.',
    ],

    'parent' => [
        'no_results' => 'There are no published results for this pupil.',
    ],

    'validation' => [
        'absent_boolean' => 'Absent must be yes or no.',
        'absent_with_marks' => 'Row :position: a pupil marked absent cannot have marks.',
        'all_classes_required' => 'Say whether the exam is for all classes.',
        'class_distinct' => 'A class was chosen twice.',
        'class_invalid' => 'The chosen class does not exist.',
        'class_missing' => 'The class was not found.',
        'class_required' => 'Choose a class.',
        'classes_required' => 'Choose the classes that sit this exam.',
        'comment_max' => 'A comment can have at most 500 characters.',
        'date_invalid' => 'Enter a valid date.',
        'deadline_before' => 'The marks deadline cannot be before the exam starts.',
        'end_before_start' => 'The end date cannot be before the start date.',
        'entries_max' => 'Too many entries were sent at once.',
        'marks_decimals' => 'Marks can have at most 2 decimal places.',
        'marks_negative' => 'Marks cannot be negative.',
        'marks_numeric' => 'Marks must be a number.',
        'name_max' => 'The name can have at most 150 characters.',
        'name_required' => 'Enter the exam name.',
        'name_unique' => 'An exam with this name already exists in this term.',
        'no_marks' => 'There are no marks to save.',
        'out_of' => 'The total marks must be a whole number from 1 to 1000.',
        'reason_required' => 'Give a reason.',
        'remark_max' => 'A remark can have at most 255 characters.',
        'student_missing' => 'The pupil was not found.',
        'subject_invalid' => 'The chosen subject does not exist.',
        'subject_required' => 'Choose a subject.',
        'term_invalid' => 'The chosen term does not belong to this school.',
        'term_required' => 'Choose the term.',
        'type_in' => 'The exam type must be one of: :types.',
        'type_required' => 'Choose the exam type.',
    ],

    // Ready-made comments per score band (the school can replace them in the comment settings).
    'bands' => [
        'low' => [
            'teacher' => 'Needs to work much harder and ask for help in the weaker subjects.',
            'head' => 'Performance is below expectation; extra support and close follow-up are needed.',
        ],
        'mid' => [
            'teacher' => 'Fair progress. With more practice and focus, better results are possible.',
            'head' => 'An average result. Keep working steadily and aim higher next term.',
        ],
        'high' => [
            'teacher' => 'Excellent work. Keep up the effort and the good discipline.',
            'head' => 'Very good performance. Congratulations, keep it up.',
        ],
    ],
];
