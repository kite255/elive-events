<?php

namespace App\Services\Tickets\Rendering;

use App\Models\EventTicketTemplate;
use App\Models\OrganizationTicketTemplate;
use App\Models\TicketTemplatePage;
use Illuminate\Support\Facades\Storage;
use Throwable;

final class TicketAssetResolver
{
    private const MAX_BYTES = 10485760;

    public function dataUri(
        ?string $path,
        EventTicketTemplate|OrganizationTicketTemplate|null $template = null,
        ?TicketTemplatePage $page = null,
        bool $background = false,
    ): ?string {
        if (! $template || ! $page || ! is_string($path)) {
            return null;
        }

        $event = $template instanceof EventTicketTemplate;
        if ($event
            ? ((int) $page->event_ticket_template_id !== (int) $template->id || $page->organization_ticket_template_id !== null)
            : ((int) $page->organization_ticket_template_id !== (int) $template->id || $page->event_ticket_template_id !== null)) {
            return null;
        }

        $directory = $background ? 'ticket-template-backgrounds' : 'ticket-template-elements';
        $prefix = $directory.'/'.($event ? 'event' : 'organization').'/'.$template->id.'/';
        $filename = '[A-Za-z0-9_-]+\.(?:jpg|jpeg|png|webp)';
        $scoped = preg_match('~\A'.preg_quote($prefix, '~').$filename.'\z~i', $path) === 1;
        // Legacy paths are allowed only as existing persisted page references,
        // not as arbitrary public disk paths.
        $legacy = preg_match('~\A'.preg_quote($directory.'/', '~').$filename.'\z~i', $path) === 1;
        if (! $scoped && ! $legacy) {
            return null;
        }
        if ($legacy) {
            $stored = $page->exists ? TicketTemplatePage::query()->find($page->id) : null;
            if (! $stored || $stored->event_ticket_template_id !== $page->event_ticket_template_id
                || $stored->organization_ticket_template_id !== $page->organization_ticket_template_id) {
                return null;
            }
            if ($background ? $stored->background_image_path !== $path
                : ! collect($stored->definition['elements'] ?? [])->contains(fn ($element) =>
                    is_array($element) && in_array($element['type'] ?? null, ['image', 'logo', 'sponsor_logo'], true)
                    && ($element['asset_path'] ?? null) === $path)) {
                return null;
            }
        }

        try {
            $disk = Storage::disk('public');
            if (! $disk->exists($path) || $disk->size($path) > self::MAX_BYTES) {
                return null;
            }
            $bytes = $disk->get($path);
            if (! is_string($bytes) || $bytes === '' || strlen($bytes) > self::MAX_BYTES) {
                return null;
            }
            $image = @getimagesizefromstring($bytes);
            if (! $image || $image[0] < 1 || $image[1] < 1 || $image[0] > 4000 || $image[1] > 4000) {
                return null;
            }
            $mime = match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
                'jpg', 'jpeg' => 'image/jpeg',
                'png' => 'image/png',
                'webp' => 'image/webp',
                default => null,
            };
            if (! $mime || ($image['mime'] ?? null) !== $mime
                || (new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes) !== $mime) {
                return null;
            }

            return 'data:'.$mime.';base64,'.base64_encode($bytes);
        } catch (Throwable) {
            return null;
        }
    }
}
