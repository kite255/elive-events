<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;

class CampaignArtworkService
{
    public function validPoster(int $eventId, mixed $path): bool
    {
        if (! is_string($path) || ! preg_match('~\Aevent-campaign-posters/'. $eventId .'/[a-f0-9]{64}\.(jpg|jpeg|png|webp)\z~D', $path)) {
            return false;
        }

        $disk = Storage::disk('public');
        return $disk->exists($path)
            && $disk->size($path) <= 10 * 1024 * 1024
            && in_array($disk->mimeType($path), ['image/jpeg', 'image/png', 'image/webp'], true);
    }

    public function posterUrl(int $eventId, mixed $path): ?string
    {
        return $this->validPoster($eventId, $path)
            ? url(Storage::disk('public')->url($path))
            : null;
    }

    public function validActionUrl(mixed $url): bool
    {
        return is_string($url) && strlen($url) <= 2048
            && filter_var($url, FILTER_VALIDATE_URL) !== false
            && parse_url($url, PHP_URL_SCHEME) === 'https'
            && parse_url($url, PHP_URL_USER) === null
            && parse_url($url, PHP_URL_PASS) === null
            && ! preg_match('/[\x00-\x20\x7f]/', $url);
    }
}
