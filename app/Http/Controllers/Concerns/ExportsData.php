<?php

namespace App\Http\Controllers\Concerns;

use Dompdf\Dompdf;
use Dompdf\Options;
use Symfony\Component\HttpFoundation\StreamedResponse;

trait ExportsData
{
    /**
     * @param  list<string>  $headers
     * @param  list<array<int|string, mixed>>  $rows
     */
    protected function exportCsv(string $filenamePrefix, array $headers, array $rows): StreamedResponse
    {
        $filename = $filenamePrefix . '_' . date('Ymd_His') . '.csv';

        return response()->streamDownload(function () use ($headers, $rows) {
            $file = fopen('php://output', 'w');
            fputcsv($file, $headers);
            foreach ($rows as $row) {
                fputcsv($file, array_values($row));
            }
            fclose($file);
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }

    /**
     * @param  list<string>  $headers
     * @param  list<array<int|string, mixed>>  $rows
     */
    protected function exportPdf(string $filenamePrefix, string $title, array $headers, array $rows)
    {
        $filename = $filenamePrefix . '_' . date('Ymd_His') . '.pdf';
        $html = view('exports.table_pdf', compact('title', 'headers', 'rows'))->render();

        if (! class_exists(Dompdf::class)) {
            return $this->exportPdfFallback($filenamePrefix, $html);
        }

        try {
            $options = new Options();
            $options->set('isRemoteEnabled', false);
            $options->set('defaultFont', 'DejaVu Sans');

            $dompdf = new Dompdf($options);
            $dompdf->loadHtml($html);
            $dompdf->setPaper('A4', 'landscape');
            $dompdf->render();

            $pdf = $dompdf->output();
        } catch (\Throwable $e) {
            report($e);

            return $this->exportPdfFallback($filenamePrefix, $html);
        }

        return response()->streamDownload(function () use ($pdf) {
            echo $pdf;
        }, $filename, [
            'Content-Type' => 'application/pdf',
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
        ]);
    }

    private function exportPdfFallback(string $filenamePrefix, string $html)
    {
        $filename = $filenamePrefix . '_' . date('Ymd_His') . '.html';

        return response()->streamDownload(function () use ($html) {
            echo $html;
        }, $filename, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
        ]);
    }
}
