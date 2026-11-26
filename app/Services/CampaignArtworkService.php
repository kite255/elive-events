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
        try {
            if (! $disk->exists($path) || $disk->size($path) > 10 * 1024 * 1024) {
                return false;
            }

            $bytes = $disk->get($path);
            if (! is_string($bytes) || $bytes === '' || strlen($bytes) > 10 * 1024 * 1024) {
                return false;
            }

            if (! hash_equals(pathinfo($path, PATHINFO_FILENAME), hash('sha256', $bytes))) {
                return false;
            }

            $image = @getimagesizefromstring($bytes);
            $expectedType = match (pathinfo($path, PATHINFO_EXTENSION)) {
                'jpg', 'jpeg' => IMAGETYPE_JPEG,
                'png' => IMAGETYPE_PNG,
                'webp' => IMAGETYPE_WEBP,
                default => null,
            };

            return is_array($image)
                && $expectedType !== null
                && $image[2] === $expectedType
                && $image[0] > 0
                && $image[1] > 0;
        } catch (\Throwable $exception) {
            return false;
        }
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
