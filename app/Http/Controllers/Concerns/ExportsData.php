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
        if (! class_exists(Dompdf::class)) {
            // Fallback: HTML printable sheet when Dompdf is not installed.
            $html = view('exports.table_pdf', compact('title', 'headers', 'rows'))->render();

            return response($html, 200, [
                'Content-Type' => 'text/html; charset=UTF-8',
                'Content-Disposition' => 'inline; filename="' . $filenamePrefix . '_' . date('Ymd_His') . '.html"',
            ]);
        }

        $html = view('exports.table_pdf', compact('title', 'headers', 'rows'))->render();

        $options = new Options();
        $options->set('isRemoteEnabled', false);
        $options->set('defaultFont', 'DejaVu Sans');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();

        $filename = $filenamePrefix . '_' . date('Ymd_His') . '.pdf';

        return response($dompdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }
}
