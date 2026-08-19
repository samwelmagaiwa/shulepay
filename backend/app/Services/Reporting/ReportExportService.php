<?php

namespace App\Services\Reporting;

use Barryvdh\DomPDF\Facade\Pdf;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportExportService
{
    /**
     * Render a Blade view to PDF and return a download response.
     */
    public function toPdf(string $view, array $data, string $filename): Response
    {
        $pdf = Pdf::loadView($view, $data)
            ->setPaper('a4', 'portrait')
            ->setOptions([
                'isHtml5ParserEnabled' => true,
                'isRemoteEnabled' => false,
                'defaultFont' => 'sans-serif',
            ]);

        return $pdf->download($filename);
    }

    /**
     * Stream a CSV download response.
     *
     * @param  string[]  $headers  Column header row
     * @param  array[]  $rows  Data rows (each an array of scalar values)
     */
    public function toCsv(array $headers, array $rows, string $filename): StreamedResponse
    {
        $response = new StreamedResponse(function () use ($headers, $rows) {
            $handle = fopen('php://output', 'w');

            // UTF-8 BOM so Excel opens it correctly
            fwrite($handle, "\xEF\xBB\xBF");

            fputcsv($handle, $headers);

            foreach ($rows as $row) {
                fputcsv($handle, $row);
            }

            fclose($handle);
        });

        $response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
        $response->headers->set(
            'Content-Disposition',
            'attachment; filename="'.$filename.'"'
        );

        return $response;
    }
}
