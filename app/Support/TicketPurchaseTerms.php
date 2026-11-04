<?php

namespace App\Support;

final class TicketPurchaseTerms
{
    public const CURRENT_VERSION = 'ticket-purchase-2026-11-04';

    public static function versionedView(string $version): string
    {
        abort_unless($version === self::CURRENT_VERSION, 404);

        return 'public.legal.versions.'.self::CURRENT_VERSION;
    }

    public static function snapshotHash(): string
    {
        $path = resource_path('views/public/legal/versions/'.self::CURRENT_VERSION.'.blade.php');

        $hash = hash_file('sha256', $path);

        if ($hash === false) {
            throw new \RuntimeException('Ticket purchase terms snapshot is unavailable.');
        }

        return $hash;
    }
}
