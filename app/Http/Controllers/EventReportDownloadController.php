<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\User;
use App\Services\Tickets\EventReportArchive;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class EventReportDownloadController
{
    public function __invoke(Request $request, Event $event, EventReportArchive $archive): BinaryFileResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->isSuperAdmin(), 403);

        $path = $archive->create($event);
        $filename = (Str::slug($event->slug ?: $event->name) ?: 'event-'.$event->getKey()).'-event-reports.zip';

        clearstatcache(true, $path);

        return response()->download($path, $filename, [
            'Content-Type' => 'application/zip',
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
        ])->deleteFileAfterSend(true);
    }
}
