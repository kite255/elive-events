<?php

namespace App\Services\Tickets\Rendering;

use Illuminate\Support\Facades\Storage;
use Throwable;

final class TicketAssetResolver
{
    private const ALLOWED_MIME_TYPES = [
        'image/jpeg',
        'image/png',
        'image/webp',
    ];

    public function dataUri(?string $path): ?string
    {
        if (! is_string($path) || trim($path) === '') {
            return null;
        }

        $path = str_replace('\\', '/', trim($path));

        if (
            str_starts_with($path, '/')
            || str_starts_with($path, '//')
            || preg_match('/(^|\/)\.\.(\/|$)/', $path) === 1
            || parse_url($path, PHP_URL_SCHEME) !== null
        ) {
            return null;
        }

        try {
            $disk = Storage::disk('public');

            if (! $disk->exists($path)) {
                return null;
            }

            $bytes = $disk->get($path);
            $mime = (new \finfo(FILEINFO_MIME_TYPE))
                ->buffer($bytes);

            if (! in_array($mime, self::ALLOWED_MIME_TYPES, true)) {
                return null;
            }

            return 'data:' . $mime . ';base64,' . base64_encode($bytes);
        } catch (Throwable) {
            return null;
        }
    }
}
