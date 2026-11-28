<?php

namespace App\Services;

use App\Models\Event;
use App\Models\EventCertificateTemplate;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class EventCertificateTemplates
{
    public function save(User $user, int $eventId, ?int $templateId, array $data): EventCertificateTemplate
    {
        $access = app(EventCertificateAccess::class);
        $access->authorize($user, $eventId);
        $data = Validator::make($data, [
            'name' => ['required', 'string', 'max:150'],
            'width' => ['required', 'integer', 'min:200', 'max:2400'],
            'height' => ['required', 'integer', 'min:200', 'max:2400'],
        ])->validate();

        return DB::transaction(function () use ($access, $user, $eventId, $templateId, $data): EventCertificateTemplate {
            Event::query()->whereKey($eventId)->lockForUpdate()->firstOrFail();
            $event = $access->authorize($user, $eventId);
            if ($templateId === null) {
                return $event->certificateTemplates()->create($data);
            }

            $draft = $event->certificateTemplates()->whereKey($templateId)
                ->where('is_active', false)->lockForUpdate()->firstOrFail();
            abort_if($draft->certificates()->exists(), 422, 'Issued certificate templates cannot be edited.');
            if (! empty($draft->definition) && ((int) $data['width'] !== $draft->width || (int) $data['height'] !== $draft->height)) {
                throw ValidationException::withMessages(['width' => 'Canvas dimensions cannot change after a layout has been saved.']);
            }
            $draft->update($data);

            return $draft;
        });
    }

    public function duplicate(User $user, int $eventId, int $templateId): EventCertificateTemplate
    {
        $access = app(EventCertificateAccess::class);
        $access->authorize($user, $eventId);

        return DB::transaction(function () use ($access, $user, $eventId, $templateId): EventCertificateTemplate {
            Event::query()->whereKey($eventId)->lockForUpdate()->firstOrFail();
            $event = $access->authorize($user, $eventId);
            $source = $event->certificateTemplates()->whereKey($templateId)->lockForUpdate()->firstOrFail();

            // Asset references need an explicit remapping workflow before layouts can be cloned.
            abort_if($source->assets()->exists() || ! empty($source->definition), 422, 'Only blank templates can be duplicated at present.');

            return $event->certificateTemplates()->create([
                'name' => mb_substr($source->name, 0, 143).' (copy)',
                'width' => $source->width,
                'height' => $source->height,
            ]);
        });
    }
}
