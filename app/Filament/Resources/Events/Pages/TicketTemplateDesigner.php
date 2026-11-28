<?php

namespace App\Filament\Resources\Events\Pages;

use App\Filament\Resources\Events\EventResource;
use App\Models\EventTicketTemplate;
use App\Models\User;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Livewire\Attributes\Locked;

class TicketTemplateDesigner extends Page
{
    protected static string $resource = EventResource::class;
    protected string $view = 'filament.resources.events.pages.ticket-template-designer';

    #[Locked]
    public int $eventId;

    #[Locked]
    public int $templateId;

    use \Livewire\WithFileUploads;

    public array $elements = [];

    public $backgroundUpload = null;

    #[Locked]
    public bool $removeBackground = false;

    public function mount(int|string $record, int|string $template): void
    {
        abort_unless(ctype_digit((string) $record) && ctype_digit((string) $template), 404);
        $this->eventId = (int) $record;
        $this->templateId = (int) $template;
        $draft = $this->draft();
        $page = $draft->pages()->where('page_number', 1)->first();
        $this->elements = $page?->definition['elements'] ?? [];
    }

    public function draft(): EventTicketTemplate
    {
        $user = auth()->user();
        abort_unless($user instanceof User && ! $user->is_gate_staff, 403);
        $event = EventWorkspace::authorizedEvents($user)->whereKey($this->eventId)->firstOrFail();
        abort_unless($event->canBeManagedBy($user), 403);

        return $event->ticketTemplates()->whereKey($this->templateId)->where('is_active', false)->firstOrFail();
    }

    public function backgroundPreview(): ?string
    {
        $draft = $this->draft();
        if ($this->removeBackground) {
            return null;
        }
        $path = $draft->pages()->where('page_number', 1)->value('background_image_path');
        $prefix = 'ticket-template-drafts/event-'.$this->eventId.'/template-'.$this->templateId.'/';
        if (! is_string($path) || ! str_starts_with($path, $prefix) || str_contains($path, '..')) {
            return null;
        }

        $disk = \Illuminate\Support\Facades\Storage::disk('local');
        if (! $disk->exists($path) || $disk->size($path) > 5242880) {
            return null;
        }
        $mime = $disk->mimeType($path);
        if (! in_array($mime, ['image/png', 'image/jpeg', 'image/webp'], true)) {
            return null;
        }

        return 'data:'.$mime.';base64,'.base64_encode($disk->get($path));
    }

    public function clearBackground(): void
    {
        $this->draft();
        $this->backgroundUpload = null;
        $this->removeBackground = true;
    }

    public function saveDesign(): void
    {
        $this->draft();
        $this->validate(['backgroundUpload' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120', 'dimensions:max_width=4000,max_height=4000']]);

        DB::transaction(function (): void {
            $draft = $this->draft();
            $draft = EventTicketTemplate::query()->whereKey($draft->id)->where('event_id', $this->eventId)
                ->where('is_active', false)->lockForUpdate()->firstOrFail();
            $validated = Validator::make(['elements' => $this->elements], [
                'elements' => ['array', 'max:40'],
                'elements.*' => ['required', 'array:type,text,x,y,width,height,font_size,color'],
                'elements.*.type' => ['required', 'in:text,event_name,ticket_type,holder_name,ticket_number,event_date,venue,qr'],
                'elements.*.text' => ['nullable', 'string', 'max:300'],
                'elements.*.x' => ['required', 'integer', 'min:0', 'max:'.$draft->width],
                'elements.*.y' => ['required', 'integer', 'min:0', 'max:'.$draft->height],
                'elements.*.width' => ['required', 'integer', 'min:20', 'max:'.$draft->width],
                'elements.*.height' => ['required', 'integer', 'min:20', 'max:'.$draft->height],
                'elements.*.font_size' => ['required', 'integer', 'min:8', 'max:100'],
                'elements.*.color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            ])->validate();

            foreach ($validated['elements'] as $element) {
                abort_unless($element['x'] + $element['width'] <= $draft->width
                    && $element['y'] + $element['height'] <= $draft->height, 422, 'Keep every element inside the canvas.');
            }

            $page = $draft->pages()->where('page_number', 1)->first();
            $background = $this->removeBackground ? null : $page?->background_image_path;
            if ($this->backgroundUpload) {
                $background = $this->backgroundUpload->store(
                    'ticket-template-drafts/event-'.$this->eventId.'/template-'.$this->templateId,
                    'local'
                );
                abort_unless(is_string($background) && $background !== '', 500, 'Background upload failed.');
            }

            $draft->pages()->updateOrCreate(['page_number' => 1], [
                'background_image_path' => $background,
                'name' => 'Front',
                'definition' => ['version' => 1, 'elements' => array_values($validated['elements'])],
            ]);
        });

        $this->backgroundUpload = null;
        $this->removeBackground = false;

        Notification::make()->title('Draft design saved')->success()->send();
    }
}
