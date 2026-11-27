<?php

namespace App\Services;

use Barryvdh\DomPDF\Facade\Pdf;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class OperationalReportPdf
{
    public function download(array $rows, string $filename): StreamedResponse
    {
        $pdf = Pdf::loadView('exports.operational-report', ['rows' => $rows])
            ->setPaper('a4', 'landscape');

        return response()->streamDownload(fn () => print($pdf->output()), $filename, [
            'Content-Type' => 'application/pdf',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
