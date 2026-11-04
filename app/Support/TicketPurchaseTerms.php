<?php

namespace App\Support;

final class TicketPurchaseTerms
{
    public const CURRENT_VERSION = 'ticket-purchase-2026-11-04-2';

    public const ARCHIVED_VERSIONS = [
        'ticket-purchase-2026-11-04',
        'ticket-purchase-2026-11-04-2',
    ];

    public static function versionedView(string $version): string
    {
        abort_unless(in_array($version, self::ARCHIVED_VERSIONS, true), 404);

        return 'public.legal.versions.'.$version;
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
