<?php

namespace App\Services\Pdf;

use App\Models\Student;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

class ReportPdf
{
    /**
     * Fee statement for one student: every invoice with its payments, and the
     * running totals a parent needs to see what is owed and what has been paid.
     */
    public function studentStatement(Student $student, Collection $invoices): string
    {
        // Loaded up front so the Blade view never triggers a lazy query mid-render.
        $student->loadMissing([
            'currentEnrollment.schoolClass',
            'currentEnrollment.school',
        ]);

        $enrollment = $student->currentEnrollment;
        $school = $enrollment?->school;

        $settings = $school?->settings ?? [];
        $branding = $settings['branding'] ?? [];

        $appName = $branding['app_name'] ?? ($school?->name ?? 'ShulePay');
        $appTagline = $branding['app_tagline'] ?? 'nexoryaTECH';
        $logoBase64 = null;

        if (isset($branding['logo_path']) && Storage::exists($branding['logo_path'])) {
            $mime = Storage::mimeType($branding['logo_path']);
            $data = base64_encode(Storage::get($branding['logo_path']));
            $logoBase64 = "data:{$mime};base64,{$data}";
        }

        // Oldest first — a statement reads chronologically.
        $rows = $invoices
            ->sortBy(fn ($i) => [$i->academicYear?->name, $i->term?->number ?? 0])
            ->values();

        $totalBilled = $rows->sum(
            fn ($i) => $i->total_amount_cents->cents() + $i->arrears_cents->cents() - $i->discount_cents->cents()
        );
        $totalPaid = $rows->sum(fn ($i) => $i->paidCents());

        return Pdf::loadView('pdf.statement', [
            'student' => $student,
            'enrollment' => $enrollment,
            'school' => $school,
            'invoices' => $rows,
            'totalBilled' => $totalBilled,
            'totalPaid' => $totalPaid,
            'totalBalance' => max(0, $totalBilled - $totalPaid),
            'appName' => $appName,
            'appTagline' => $appTagline,
            'logoBase64' => $logoBase64,
        ])->setPaper('a4')->output();
    }
}
