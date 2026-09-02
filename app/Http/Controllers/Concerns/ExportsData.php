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

        if ($reason = $this->pdfExportUnavailableReason()) {
            return $this->exportPdfFailureResponse($reason);
        }

        try {
            $tempDir = storage_path('app/dompdf');
            if (! is_dir($tempDir)) {
                mkdir($tempDir, 0755, true);
            }

            $options = new Options();
            $options->set('isRemoteEnabled', false);
            $options->set('defaultFont', 'DejaVu Sans');
            $options->set('tempDir', $tempDir);
            $options->set('chroot', base_path());

            $dompdf = new Dompdf($options);
            $dompdf->loadHtml($html);
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

        return response()->streamDownload(function () use ($pdf) {
            echo $pdf;
        }, $filename, [
            'Content-Type' => 'application/pdf',
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
        ]);
    }

    private function pdfExportUnavailableReason(): ?string
    {
        if (! class_exists(Dompdf::class)) {
            return 'Dompdf is not installed. In the project folder run: composer install';
        }

        $missing = array_values(array_filter([
            ! extension_loaded('dom') ? 'dom' : null,
            ! extension_loaded('mbstring') ? 'mbstring' : null,
        ]));

        if ($missing !== []) {
            return 'Enable PHP extensions in php.ini, then restart Apache: extension='
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
