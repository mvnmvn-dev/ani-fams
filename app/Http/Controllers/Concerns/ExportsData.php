<?php

namespace App\Http\Controllers\Concerns;

use Dompdf\Dompdf;
use Dompdf\Options;
use Symfony\Component\HttpFoundation\Response;

trait ExportsData
{
    /**
     * @param  list<string>  $headers
     * @param  list<array<int|string, mixed>>  $rows
     */
    protected function exportCsv(string $filenamePrefix, array $headers, array $rows): Response
    {
        $filename = $filenamePrefix . '_' . date('Ymd_His') . '.csv';
        $csv = $this->buildCsvString($headers, $rows);

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Content-Length' => (string) strlen($csv),
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * @param  list<string>  $headers
     * @param  list<array<int|string, mixed>>  $rows
     */
    protected function exportPdf(string $filenamePrefix, string $title, array $headers, array $rows): Response
    {
        $filename = $filenamePrefix . '_' . date('Ymd_His') . '.pdf';
        $html = view('exports.table_pdf', compact('title', 'headers', 'rows'))->render();

        if ($reason = $this->pdfExportUnavailableReason()) {
            return $this->exportPdfFailureResponse($reason);
        }

        try {
            $tempDir = storage_path('app/dompdf');
            if (! is_dir($tempDir) && ! mkdir($tempDir, 0755, true) && ! is_dir($tempDir)) {
                throw new \RuntimeException('Cannot create Dompdf temp directory: ' . $tempDir);
            }
            if (! is_writable($tempDir)) {
                throw new \RuntimeException('Dompdf temp directory is not writable: ' . $tempDir);
            }

            $options = new Options();
            $options->set('isRemoteEnabled', false);
            $options->set('isHtml5ParserEnabled', true);
            $options->set('defaultFont', 'DejaVu Sans');
            $options->set('tempDir', $tempDir);
            $options->set('chroot', base_path());
            $options->set('fontCache', $tempDir);

            $dompdf = new Dompdf($options);
            $dompdf->loadHtml($html, 'UTF-8');
            $dompdf->setPaper('A4', 'landscape');
            $dompdf->render();

            $pdf = $dompdf->output();
            if ($pdf === '' || ! str_starts_with($pdf, '%PDF')) {
                throw new \RuntimeException('Dompdf did not produce a valid PDF file.');
            }
        } catch (\Throwable $e) {
            report($e);

            return $this->exportPdfFailureResponse(
                'PDF generation failed: ' . $e->getMessage()
            );
        }

        // Never fall back to HTML — browsers must receive binary PDF bytes.
        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Content-Length' => (string) strlen($pdf),
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
            'Pragma' => 'no-cache',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * @param  list<string>  $headers
     * @param  list<array<int|string, mixed>>  $rows
     */
    private function buildCsvString(array $headers, array $rows): string
    {
        $handle = fopen('php://temp', 'r+');
        fputcsv($handle, $headers);
        foreach ($rows as $row) {
            fputcsv($handle, array_values($row));
        }
        rewind($handle);
        $csv = stream_get_contents($handle) ?: '';
        fclose($handle);

        return $csv;
    }

    private function pdfExportUnavailableReason(): ?string
    {
        if (! class_exists(Dompdf::class)) {
            return 'Dompdf is not installed. In the project folder run: composer install';
        }

        $missing = array_values(array_filter([
            ! extension_loaded('dom') ? 'dom' : null,
            ! extension_loaded('mbstring') ? 'mbstring' : null,
            ! extension_loaded('gd') ? 'gd' : null,
        ]));

        if ($missing !== []) {
            return 'Enable PHP extensions in C:\\xampp\\php\\php.ini, then restart Apache: extension='
                . implode(', extension=', $missing);
        }

        return null;
    }

    private function exportPdfFailureResponse(string $message)
    {
        return redirect()->back()->with(
            'flash_error',
            $message . ' (Contact your admin if this persists.)'
        );
    }
}
