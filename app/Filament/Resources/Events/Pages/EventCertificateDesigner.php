<?php

namespace App\Filament\Resources\Events\Pages;

use App\Filament\Resources\Events\EventResource;
use App\Models\Event;
use App\Models\EventCertificateTemplate;
use App\Models\User;
use App\Services\EventCertificateAccess;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;

class EventCertificateDesigner extends Page
{
    protected static string $resource = EventResource::class;
    protected string $view = 'filament.resources.events.pages.event-certificate-designer';
    protected static ?string $title = 'Certificate designer';

    #[Locked]
    public int $eventId;

    #[Locked]
    public int $templateId;

    public array $elements = [];

    public string $backgroundColor = '#FFFFFF';

    public function mount(int|string $record, int|string $template): void
    {
        abort_unless(ctype_digit((string) $record) && ctype_digit((string) $template), 404);
        $this->eventId = (int) $record;
        $this->templateId = (int) $template;
        $draft = $this->draft();
        $this->elements = $draft->definition['elements'] ?? [];
        $this->backgroundColor = $draft->definition['background_color'] ?? '#FFFFFF';
    }

    public function draft(): EventCertificateTemplate
    {
        $user = auth()->user();
        abort_unless($user instanceof User, 403);
        $event = app(EventCertificateAccess::class)->authorize($user, $this->eventId);
        $draft = $event->certificateTemplates()->whereKey($this->templateId)
            ->where('is_active', false)->firstOrFail();
        abort_if($draft->certificates()->exists(), 422, 'Templates with certificate records cannot be edited.');

        return $draft;
    }

    public function saveDesign(): void
    {
        $this->draft();

        DB::transaction(function (): void {
            Event::query()->whereKey($this->eventId)->lockForUpdate()->firstOrFail();
            $draft = $this->draft();
            $draft = EventCertificateTemplate::query()->whereKey($draft->id)
                ->where('event_id', $this->eventId)->where('is_active', false)->lockForUpdate()->firstOrFail();
            abort_if($draft->certificates()->exists(), 422);
            $validated = Validator::make([
                'elements' => $this->elements,
                'background_color' => $this->backgroundColor,
            ], [
                'background_color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
                'elements' => ['present', 'array', 'max:50'],
                'elements.*' => ['required', 'array:type,text,x,y,width,height,font_size,color,font_family,font_weight,font_style,text_align,line_height'],
                'elements.*.type' => ['required', 'in:text,attendee_name,event_name,event_date,organization_name,certificate_number,issued_date,qr'],
                'elements.*.text' => ['nullable', 'string', 'max:500'],
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

            foreach ($validated['elements'] as $element) {
                if ($element['x'] + $element['width'] > $draft->width
                    || $element['y'] + $element['height'] > $draft->height
                    || ($element['type'] === 'qr' && $element['width'] != $element['height'])) {
                    throw ValidationException::withMessages(['elements' => 'Keep elements inside the canvas and QR placeholders square.']);
                }
            }

            $draft->update(['definition' => [
                'version' => 1,
                'background_color' => $validated['background_color'],
                'elements' => array_values($validated['elements']),
            ]]);
        });

        Notification::make()->title('Certificate design saved')->success()->send();
    }
}
