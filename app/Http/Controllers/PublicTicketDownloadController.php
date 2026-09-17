<?php

namespace App\Http\Controllers;

use App\Services\Tickets\Rendering\PublicTicketAccess;
use App\Services\Tickets\Rendering\TicketOutputService;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PublicTicketDownloadController extends Controller
{
    public function png(
        string $token,
        int $pageNumber,
        PublicTicketAccess $access,
        TicketOutputService $output
    ): StreamedResponse {
        $result = $output->png(
            $access->findEligible($token),
            $pageNumber
        );

        return response()->streamDownload(
            static function () use ($result): void {
                echo $result['bytes'];
            },
            $result['filename'],
            $this->headers('image/png')
        );
    }

    public function pdf(
        string $token,
        PublicTicketAccess $access,
        TicketOutputService $output
    ): StreamedResponse {
        $result = $output->pdf(
            $access->findEligible($token)
        );

        return response()->streamDownload(
            static function () use ($result): void {
                echo $result['bytes'];
            },
            $result['filename'],
            $this->headers('application/pdf')
        );
    }

    /**
     * @return array<string, string>
     */
    private function headers(string $contentType): array
    {
        return [
            'Content-Type' => $contentType,
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ];
    }
}
