<?php

namespace App\Services\Sms;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\Student;
use Carbon\Carbon;

class SmsTemplates
{
    // ── PAYMENT CONFIRMATION ─────────────────────────────────────────────────

    /**
     * Sent to primary guardian immediately after a payment is recorded.
     * ~155 chars
     */
    public static function paymentConfirmed(Payment $payment): string
    {
        $invoice = $payment->invoice;
        $student = $invoice->student;
        $amount = number_format($payment->amount_cents->cents() / 100, 0, '.', ',');
        $balance = number_format($invoice->balanceDueCents() / 100, 0, '.', ',');
        $method = strtoupper($payment->method instanceof \UnitEnum ? $payment->method->value : (string) $payment->method);
        $name = $student->first_name;

        if ($invoice->balanceDueCents() <= 0) {
            return "ShulePay: Malipo ya TZS {$amount} ({$method}) kwa {$name} yamepokewa. Ada IMELIPWA KIKAMILIFU. Asante!";
        }

        return "ShulePay: Malipo TZS {$amount} ({$method}) kwa {$name} yamepokewa. Baki: TZS {$balance}. Asante!";
    }

    // ── INVOICE ISSUED ───────────────────────────────────────────────────────

    /**
     * Sent when a new invoice is generated for a student.
     * ~155 chars
     */
    public static function invoiceIssued(Invoice $invoice): string
    {
        $student = $invoice->student;
        $total = number_format($invoice->total_amount_cents->cents() / 100, 0, '.', ',');
        $due = $invoice->due_date ? Carbon::parse($invoice->due_date)->format('d/m/Y') : '—';
        $name = $student->first_name;
        $term = $invoice->term?->name ?? '';

        return "ShulePay: Ankara ya {$term} imetolewa kwa {$name}. Jumla: TZS {$total}. Mwisho kulipa: {$due}. Tafadhali lipa mapema.";
    }

    // ── PAYMENT REMINDER ─────────────────────────────────────────────────────

    /**
     * Sent as automated reminder (3 days before due, on due date, 7 days after).
     */
    public static function paymentReminder(Invoice $invoice, string $urgency = 'normal'): string
    {
        $student = $invoice->student;
        $balance = number_format($invoice->balanceDueCents() / 100, 0, '.', ',');
        $due = $invoice->due_date ? Carbon::parse($invoice->due_date)->format('d/m/Y') : '—';
        $name = $student->first_name;

        return match ($urgency) {
            'upcoming' => "ShulePay: Kumbusho - Ada ya {$name} ya TZS {$balance} inaisha {$due}. Tafadhali lipa haraka. Msaada: shule yako.",
            'overdue' => "ShulePay: MUHIMU - Ada ya {$name} ya TZS {$balance} imepita tarehe. Tafadhali lipa leo ili kuepuka adhabu. Wasiliana na shule.",
            default => "ShulePay: Ada ya {$name} ya TZS {$balance} bado haijalipiwa. Mwisho: {$due}. Tafadhali lipa mapema. Asante.",
        };
    }

    // ── BULK REMINDER (CUSTOM) ────────────────────────────────────────────────

    /**
     * Used for blast SMS with custom message; replaces known variables.
     */
    public static function bulkReminder(string $template, Student $student, Invoice $invoice): string
    {
        $balance = number_format($invoice->balanceDueCents() / 100, 0, '.', ',');
        $due = $invoice->due_date ? Carbon::parse($invoice->due_date)->format('d/m/Y') : '—';

        return str_replace(
            ['[Jina]', '[Kiasi]', '[Tarehe]', '[Shule]'],
            [$student->first_name, "TZS {$balance}", $due, $invoice->school?->name ?? 'Shule'],
            $template
        );
    }

    // ── REFUND PROCESSED ─────────────────────────────────────────────────────

    public static function refundProcessed(Refund $refund): string
    {
        $invoice = $refund->invoice;
        $student = $invoice?->student;
        $amount = number_format($refund->amount_cents->cents() / 100, 0, '.', ',');
        $name = $student?->first_name ?? 'Mwanafunzi';
        $method = strtoupper($refund->method ?? 'cash');

        return "ShulePay: Marejesho ya TZS {$amount} ({$method}) kwa {$name} yamefanyika. Wasiliana na shule kwa maswali.";
    }

    // ── INSTALLMENT PLAN CREATED ──────────────────────────────────────────────

    public static function installmentPlanCreated(Invoice $invoice, int $totalInstallments, int $amountPerInstallmentCents): string
    {
        $student = $invoice->student;
        $amount = number_format($amountPerInstallmentCents / 100, 0, '.', ',');
        $name = $student->first_name;

        return "ShulePay: Mpango wa malipo {$totalInstallments} kwa {$name} umewekwa. Kila mkopo: TZS {$amount}. Lipa kwa wakati. Asante!";
    }

    // ── INSTALLMENT PAID ─────────────────────────────────────────────────────

    public static function installmentPaid(int $installmentNumber, int $totalInstallments, string $studentName, int $remainingCents): string
    {
        $remaining = number_format($remainingCents / 100, 0, '.', ',');
        $left = $totalInstallments - $installmentNumber;

        if ($left === 0) {
            return "ShulePay: Hongera! {$studentName} amelipa mkopo wa mwisho. Ada IMELIPWA KIKAMILIFU. Asante sana!";
        }

        return "ShulePay: Mkopo {$installmentNumber}/{$totalInstallments} kwa {$studentName} umelipwa. Mikopo {$left} iliyobaki. Baki: TZS {$remaining}.";
    }

    // ── CLEARANCE ISSUED ─────────────────────────────────────────────────────

    public static function clearanceIssued(Student $student, string $academicYear): string
    {
        $name = $student->first_name.' '.$student->last_name;

        return "ShulePay: Hati ya kufuzu (clearance) ya {$name} mwaka {$academicYear} imetolewa. Ada zote zimelipwa. Asante!";
    }

    // ── OTP / 2FA ────────────────────────────────────────────────────────────

    public static function otpCode(string $code, int $expiresInMinutes = 10): string
    {
        return "ShulePay: Msimbo wako wa kuthibitisha ni {$code}. Utaisha baada ya dakika {$expiresInMinutes}. Usishirikishe na mtu yeyote.";
    }

    // ── NEW STUDENT WELCOME ───────────────────────────────────────────────────

    public static function studentWelcome(Student $student, string $schoolName, string $className): string
    {
        $name = $student->first_name.' '.$student->last_name;
        $adm = $student->currentEnrollment?->admission_number ?? '';

        return "ShulePay: Karibu {$name} ({$adm}) amesajiliwa katika {$schoolName} - {$className}. Tunafurahi kuwa nawe. Shule inakusubiri!";
    }

    // ── FEE STRUCTURE CHANGED ────────────────────────────────────────────────

    public static function feeStructureUpdated(string $schoolName, string $term, int $totalCents): string
    {
        $total = number_format($totalCents / 100, 0, '.', ',');

        return "ShulePay: Muundo wa ada wa {$schoolName} kwa {$term} umesasishwa. Jumla mpya: TZS {$total}. Maswali: wasiliana na shule.";
    }

    // ── ACCOUNT CREATED (Parent login) ───────────────────────────────────────

    public static function accountCreated(string $parentName, string $phone): string
    {
        return "ShulePay: Akaunti yako imeundwa kwa {$phone}. Tumia nambari hii kuingia kwenye mfumo. Wasiliana na shule kwa msaada.";
    }

    // ── ABSENCE ALERT (to owner) ─────────────────────────────────────────────

    /**
     * Sent to the school owner when a student is marked absent.
     */
    public static function absenceAlert(string $studentName, string $className, string $date, ?string $admissionNumber = null): string
    {
        $adm = $admissionNumber ? " ({$admissionNumber})" : '';

        return "ShulePay: Taarifa ya Kutokuhudhuria - {$studentName}{$adm} wa {$className} HAKUHUDHURIA tarehe {$date}. Wasiliana na mzazi/mlezi.";
    }

    // ── WEEKLY SUMMARY (to owner) ─────────────────────────────────────────────

    public static function weeklySummary(string $schoolName, int $collectionsCents, int $pendingCents, int $invoiceCount): string
    {
        $collected = number_format($collectionsCents / 100, 0, '.', ',');
        $pending = number_format($pendingCents / 100, 0, '.', ',');

        return "ShulePay Wiki: {$schoolName} - Makusanyo: TZS {$collected} | Madeni: TZS {$pending} | Ankara: {$invoiceCount}. Ripoti kamili: mfumo.";
    }

    // ── EXPENSE APPROVED ─────────────────────────────────────────────────────

    public static function expenseApproved(string $description, int $amountCents, string $approverName): string
    {
        $amount = number_format($amountCents / 100, 0, '.', ',');

        return "ShulePay: Gharama '{$description}' ya TZS {$amount} imeidhinishwa na {$approverName}. Unaweza kuendelea na malipo.";
    }

    // ── PAYROLL PROCESSED ────────────────────────────────────────────────────

    public static function payrollProcessed(string $employeeName, int $netSalaryCents, string $month): string
    {
        $salary = number_format($netSalaryCents / 100, 0, '.', ',');

        return "ShulePay: Mshahara wa {$month} wa TZS {$salary} umechakatwa kwa {$employeeName}. Lipa kupitia njia iliyochaguliwa.";
    }

    // ── HELPERS ──────────────────────────────────────────────────────────────

    /**
     * Truncate to maxLength chars. Returns original if within limit.
     */
    public static function truncate(string $message, int $maxLength = 160): string
    {
        if (strlen($message) <= $maxLength) {
            return $message;
        }

        return substr($message, 0, $maxLength - 3).'...';
    }

    /**
     * Split into multiple SMS parts if over 160 chars.
     * Returns array of strings, each ≤ 153 chars (leaving room for part indicator).
     */
    public static function splitParts(string $message): array
    {
        if (strlen($message) <= 160) {
            return [$message];
        }

        $parts = [];
        $words = explode(' ', $message);
        $current = '';

        foreach ($words as $word) {
            $test = $current ? "{$current} {$word}" : $word;
            if (strlen($test) <= 150) {
                $current = $test;
            } else {
                if ($current) {
                    $parts[] = $current;
                }
                $current = $word;
            }
        }

        if ($current) {
            $parts[] = $current;
        }

        // Add part indicators if multiple parts
        if (count($parts) > 1) {
            $total = count($parts);
            $parts = array_values(array_map(
                fn ($p, $i) => '('.($i + 1)."/{$total}) {$p}",
                $parts,
                array_keys($parts)
            ));
        }

        return $parts;
    }
}
