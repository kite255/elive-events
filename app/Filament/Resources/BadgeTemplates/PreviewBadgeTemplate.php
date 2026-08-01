<?php

namespace App\Filament\Resources\BadgeTemplates\Pages;

use App\Filament\Resources\BadgeTemplates\BadgeTemplateResource;
use App\Models\Attendee;
use App\Models\BadgeTemplate;
use App\Services\BadgeGenerationService;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;

class PreviewBadgeTemplate extends Page
{
    use InteractsWithRecord;

    protected static string $resource = BadgeTemplateResource::class;

    protected string $view = 'filament.resources.badge-templates.pages.preview-badge-template';

    public BadgeTemplate $template;

    public ?Attendee $sampleAttendee = null;

    public string $badgePreviewUrl = '';

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);

        /** @var BadgeTemplate $template */
        $template = $this->record->load(['event', 'elements']);

        $this->template = $template;

        $this->sampleAttendee = Attendee::query()
            ->with(['event', 'category', 'badgeType'])
            ->when($template->event_id, function ($query) use ($template) {
                $query->where('event_id', $template->event_id);
            })
            ->latest()
            ->first();

        if ($this->sampleAttendee) {
            $path = app(BadgeGenerationService::class)
                ->generateForAttendee($this->sampleAttendee);

            $this->badgePreviewUrl = asset('storage/' . $path);
        }
    }

    public function getTitle(): string
    {
        return 'Preview Badge Template';
    }
}