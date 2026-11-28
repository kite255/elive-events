<?php

namespace App\Services;

use App\Models\Event;
use App\Models\EventCertificateTemplate;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class EventCertificatePublishing
{
    public function publish(User $user, int $eventId, int $templateId): EventCertificateTemplate
    {
        $access = app(EventCertificateAccess::class);
        $access->authorize($user, $eventId);

        return DB::transaction(function () use ($access, $user, $eventId, $templateId): EventCertificateTemplate {
            Event::query()->whereKey($eventId)->lockForUpdate()->firstOrFail();
            $event = $access->authorize($user, $eventId);
            abort_unless($event->certificatesAreEnabled() && ! $event->isCancelled(), 422, 'Enable certificates for a non-cancelled event first.');
            $draft = $event->certificateTemplates()->whereKey($templateId)->lockForUpdate()->firstOrFail();
            abort_if($draft->is_active || $draft->certificates()->exists(), 422, 'Only unused drafts can be published.');
            $this->validateLayout($draft);
            $draft->forceFill(['is_active' => true])->save();

            return $draft;
        });
    }

    private function validateLayout(EventCertificateTemplate $draft): void
    {
        $definition = Validator::make($draft->definition ?? [], [
            'version' => ['required', 'integer', 'in:1'],
            'background_color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'elements' => ['required', 'array', 'min:3', 'max:50'],
            'elements.*' => ['required', 'array:type,text,x,y,width,height,font_size,color,font_family,font_weight,font_style,text_align,line_height,asset_id'],
            'elements.*.type' => ['required', 'in:text,attendee_name,event_name,event_date,organization_name,certificate_number,issued_date,qr,image'],
            'elements.*.text' => ['nullable', 'string', 'max:500'],
            'elements.*.asset_id' => ['nullable', 'integer', 'min:1'],
            'elements.*.x' => ['required', 'integer', 'min:0', 'max:'.$draft->width],
            'elements.*.y' => ['required', 'integer', 'min:0', 'max:'.$draft->height],
            'elements.*.width' => ['required', 'integer', 'min:20', 'max:'.$draft->width],
            'elements.*.height' => ['required', 'integer', 'min:20', 'max:'.$draft->height],
            'elements.*.font_size' => ['required', 'integer', 'min:8', 'max:150'],
            'elements.*.color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'elements.*.font_family' => ['required', 'in:brand,sans,serif,mono'],
            'elements.*.font_weight' => ['required', 'integer', 'in:400,600,700'],
            'elements.*.font_style' => ['required', 'in:normal,italic'],
            'elements.*.text_align' => ['required', 'in:left,center,right'],
            'elements.*.line_height' => ['required', 'numeric', 'min:1', 'max:2'],
        ])->validate();

        $types = array_column($definition['elements'], 'type');
        if (array_diff(['attendee_name', 'certificate_number', 'qr'], $types)) {
            throw ValidationException::withMessages(['elements' => 'Include attendee name, certificate number and QR verification elements.']);
        }
        $disk = Storage::disk('local');
        $prefix = 'certificate-drafts/event-'.$draft->event_id.'/template-'.$draft->id.'/';
        foreach ($definition['elements'] as $element) {
            if ($element['x'] + $element['width'] > $draft->width
                || $element['y'] + $element['height'] > $draft->height
                || ($element['type'] === 'qr' && ($element['width'] != $element['height'] || $element['width'] < 80))) {
                throw ValidationException::withMessages(['elements' => 'Keep elements inside the canvas and QR boxes square and at least 80 px.']);
            }
            if ($element['type'] !== 'image') {
                continue;
            }
            $asset = $draft->assets()->whereKey($element['asset_id'] ?? 0)->first();
            $path = $asset?->file_path;
            if (! is_string($path) || ! str_starts_with($path, $prefix) || str_contains($path, '..')
                || ! $disk->exists($path) || $disk->size($path) > 2097152
                || ! in_array($disk->mimeType($path), ['image/png', 'image/jpeg'], true)) {
                throw ValidationException::withMessages(['elements' => 'Every referenced image must be available in this template.']);
            }
            $size = @getimagesize($disk->path($path));
            if (! $size || $size[0] < 1 || $size[1] < 1 || $size[0] > 4000 || $size[1] > 4000) {
                throw ValidationException::withMessages(['elements' => 'A referenced image has invalid dimensions.']);
            }
        }
    }
}
