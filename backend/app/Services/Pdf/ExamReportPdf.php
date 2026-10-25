<?php

namespace App\Services\Pdf;

use App\Services\Exam\ExamScope;
use App\Support\SchoolLetterhead;
use Barryvdh\DomPDF\Facade\Pdf;

/** Printable exam documents: a student's report card and a class results sheet. */
class ExamReportPdf
{
    /** @param  array  $card  from ExamResultsService::reportCard() */
    public function reportCard(array $card): string
    {
        return Pdf::loadView('pdf.exam.report_card', [
            'card' => $card,
            'lh' => SchoolLetterhead::for(ExamScope::school()),
        ])->setPaper('a4')->output();
    }

    /** @param  array<int, array>  $cards  each from ExamResultsService::reportCard() */
    public function reportCards(array $cards): string
    {
        return Pdf::loadView('pdf.exam.report_cards', [
            'cards' => $cards,
            'lh' => SchoolLetterhead::for(ExamScope::school()),
        ])->setPaper('a4')->output();
    }

    /** @param  array  $results  from ExamResultsService::classResults() */
    public function classResults(array $results): string
    {
        return Pdf::loadView('pdf.exam.class_results', [
            'results' => $results,
            'lh' => SchoolLetterhead::for(ExamScope::school()),
        ])->setPaper('a4', 'landscape')->output();
    }
}
