<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class OutstandingDebtsExport implements FromArray, ShouldAutoSize, WithHeadings, WithStyles
{
    /**
     * @param  array<int, array{student_name:string, guardian_name:string, guardian_phone:string, village_street:string, debt_cents:int, terms:array<int,string>}>  $rows
     */
    public function __construct(private readonly array $rows) {}

    public function array(): array
    {
        return array_map(fn (array $r) => [
            $r['student_name'],
            $r['guardian_name'],
            $r['guardian_phone'],
            $r['village_street'],
            round($r['debt_cents'] / 100),
            implode(', ', $r['terms']),
        ], $this->rows);
    }

    public function headings(): array
    {
        return ['Student Name', 'Parent/Guardian Name', 'Parent Phone', 'Village/Street', 'Debt Amount (TZS)', 'Terms Not Paid'];
    }

    public function styles(Worksheet $sheet): array
    {
        $sheet->getStyle('A1:F1')->getFont()->setBold(true);
        $sheet->getStyle('A1:F1')->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setRGB('F43F5E');
        $sheet->getStyle('A1:F1')->getFont()->getColor()->setRGB('FFFFFF');

        return [];
    }
}
