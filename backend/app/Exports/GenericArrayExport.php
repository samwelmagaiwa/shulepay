<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Reusable .xlsx export for any of the Reports page tabs — takes plain
 * headers + rows (the same shape already built for the CSV download) and
 * renders a styled header row plus an optional bold "Total" row at the end.
 *
 * WithStrictNullComparison matters here: without it, Laravel-Excel writes
 * cells with a loose `$value == null` check, and PHP considers `0 == null`
 * true — every legitimate TZS 0 / zero-count cell silently disappears
 * instead of rendering as "0".
 */
class GenericArrayExport implements FromArray, ShouldAutoSize, WithHeadings, WithStrictNullComparison, WithStyles
{
    /**
     * @param  array<int,string>  $headers
     * @param  array<int,array<int,mixed>>  $rows
     * @param  array<int,mixed>|null  $totalRow
     */
    public function __construct(
        private readonly array $headers,
        private readonly array $rows,
        private readonly ?array $totalRow = null,
    ) {}

    public function array(): array
    {
        return $this->totalRow ? [...$this->rows, $this->totalRow] : $this->rows;
    }

    public function headings(): array
    {
        return $this->headers;
    }

    public function styles(Worksheet $sheet): array
    {
        $lastCol = chr(ord('A') + count($this->headers) - 1);

        $sheet->getStyle("A1:{$lastCol}1")->getFont()->setBold(true);
        $sheet->getStyle("A1:{$lastCol}1")->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setRGB('4F46E5');
        $sheet->getStyle("A1:{$lastCol}1")->getFont()->getColor()->setRGB('FFFFFF');

        if ($this->totalRow) {
            $totalRowNum = count($this->rows) + 2;
            $sheet->getStyle("A{$totalRowNum}:{$lastCol}{$totalRowNum}")->getFont()->setBold(true);
            $sheet->getStyle("A{$totalRowNum}:{$lastCol}{$totalRowNum}")->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setRGB('E5E7EB');
        }

        return [];
    }
}
