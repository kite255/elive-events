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
use Illuminate\Support\Facades\Storage;
use Livewire\WithFileUploads;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;

class EventCertificateDesigner extends Page
{
    use WithFileUploads;

    public $imageUpload = null;
    public string $imageKind = 'logo';

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

    protected function assetDirectory(): string
    {
        return 'certificate-drafts/event-'.$this->eventId.'/template-'.$this->templateId.'/';
    }

    public function imagePreviews(): array
    {
        $draft = $this->draft();
        $disk = Storage::disk('local');
        $images = [];
        foreach ($draft->assets()->orderBy('id')->limit(12)->get() as $asset) {
            $path = $asset->file_path;
            if (! str_starts_with($path, $this->assetDirectory()) || str_contains($path, '..')
                || ! $disk->exists($path) || $disk->size($path) > 2097152) {
                continue;
            }
            $mime = $disk->mimeType($path);
            if (! in_array($mime, ['image/png', 'image/jpeg'], true)) {
                continue;
            }
            $size = @getimagesize($disk->path($path));
            if (! $size || $size[0] < 1 || $size[1] < 1) {
                continue;
            }
            $images[$asset->id] = [
                'kind' => $asset->asset_type,
                'width' => $size[0],
                'height' => $size[1],
                'src' => $disk->temporaryUrl($path, now()->addMinutes(30)),
            ];
        }

        return $images;
    }

    public function uploadImage(): void
    {
        $this->draft();
        $this->validate([
            'imageUpload' => ['required', 'image', 'mimes:png,jpg,jpeg', 'max:2048', 'dimensions:max_width=4000,max_height=4000'],
            'imageKind' => ['required', 'in:background,logo,signature,stamp,image'],
        ]);
        $path = null;
        try {
            DB::transaction(function () use (&$path): void {
                Event::query()->whereKey($this->eventId)->lockForUpdate()->firstOrFail();
                $draft = $this->draft();
                abort_unless($draft->assets()->count() < 12, 422, 'A draft supports up to 12 images.');
                $path = $this->imageUpload->store($this->assetDirectory(), 'local');
                abort_unless(is_string($path) && $path !== '', 500, 'Image upload failed.');
                $asset = new \App\Models\EventCertificateAsset;
                $asset->forceFill([
                    'asset_type' => $this->imageKind,
                    'file_path' => $path,
                    'mime_type' => $this->imageUpload->getMimeType(),
                ]);
                $draft->assets()->save($asset);
            });
        } catch (\Throwable $exception) {
            if (is_string($path) && $path !== '') {
                Storage::disk('local')->delete($path);
            }
            throw $exception;
        }
        $this->imageUpload = null;
        Notification::make()->title('Certificate image uploaded')->success()->send();
    }

    public function downloadPreview(): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $this->draft();
        $user = auth()->user();
        abort_unless($user instanceof User, 403);
        $bytes = app(\App\Services\EventCertificatePreviewPdf::class)->render($user, $this->eventId, $this->templateId);

        return response()->streamDownload(fn () => print($bytes), 'certificate-sample-'.$this->templateId.'.pdf', [
            'Content-Type' => 'application/pdf',
            'Cache-Control' => 'private, no-store',
        ]);
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
                'elements.*' => ['required', 'array:type,text,x,y,width,height,font_size,color,font_family,font_weight,font_style,text_align,line_height,asset_id'],
                'elements.*.type' => ['required', 'in:text,attendee_name,event_name,event_date,organization_name,certificate_number,issued_date,qr,image'],
                'elements.*.asset_id' => ['nullable', 'integer', 'min:1'],
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
                if ($element['type'] === 'image' && ! $draft->assets()->whereKey($element['asset_id'] ?? 0)->exists()) {
                    throw ValidationException::withMessages(['elements' => 'Select an image belonging to this certificate draft.']);
                }
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
