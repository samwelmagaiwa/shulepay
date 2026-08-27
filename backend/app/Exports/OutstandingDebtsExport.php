<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class OutstandingDebtsExport implements FromArray, ShouldAutoSize, WithEvents, WithHeadings, WithStyles
{
    /**
     * @param  array<int, array{student_name:string, student_class:string, guardian_name:string, guardian_phone:string, village_street:string, debt_cents:int, terms:array<int,string>}>  $rows
     */
    public function __construct(private readonly array $rows) {}

    public function array(): array
    {
        $rows = array_map(fn (array $r) => [
            $r['student_name'],
            $r['student_class'],
            $r['guardian_name'],
            $r['guardian_phone'],
            $r['village_street'],
            round($r['debt_cents'] / 100),
            implode(', ', $r['terms']),
        ], $this->rows);

        $totalDebt = array_sum(array_column($this->rows, 'debt_cents'));
        $rows[] = ['TOTAL ('.count($this->rows).' debtors)', '', '', '', '', round($totalDebt / 100), ''];

        return $rows;
    }

    public function headings(): array
    {
        return ['Student Name', 'Class', 'Parent/Guardian Name', 'Parent Phone', 'Village/Street', 'Debt Amount (TZS)', 'Terms Not Paid'];
    }

    public function styles(Worksheet $sheet): array
    {
        $sheet->getStyle('A1:G1')->getFont()->setBold(true);
        $sheet->getStyle('A1:G1')->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setRGB('F43F5E');
        $sheet->getStyle('A1:G1')->getFont()->getColor()->setRGB('FFFFFF');

        $totalRow = count($this->rows) + 2;
        $sheet->getStyle("A{$totalRow}:G{$totalRow}")->getFont()->setBold(true);
        $sheet->getStyle("A{$totalRow}:G{$totalRow}")->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setRGB('FEE2E2');
        $sheet->mergeCells("A{$totalRow}:E{$totalRow}");

        return [];
    }

    /**
     * Parent Phone (column D) must be forced to an explicit string type — the
     * default numeric-string auto-detection otherwise renders the long digit
     * string in scientific notation (2.55E+11) once opened in Excel.
     */
    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $lastRow = count($this->rows) + 1;
                $sheet->getStyle("D2:D{$lastRow}")->getNumberFormat()->setFormatCode('@');
                for ($row = 2; $row <= $lastRow; $row++) {
                    $cell = $sheet->getCell("D{$row}");
                    $sheet->setCellValueExplicit("D{$row}", (string) $cell->getValue(), DataType::TYPE_STRING);
                }
            },
        ];
    }
}
