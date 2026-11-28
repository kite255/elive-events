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

    public array $elements = [];

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

    public function saveDesign(): void
    {
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

            $draft->pages()->updateOrCreate(['page_number' => 1], [
                'name' => 'Front',
                'definition' => ['version' => 1, 'elements' => array_values($validated['elements'])],
            ]);
        });

        Notification::make()->title('Draft design saved')->success()->send();
    }
}
