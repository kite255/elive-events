<?php

namespace App\Filament\Resources\Attendees\Pages;

use App\Exports\AttendeesImportTemplateExport;
use App\Exports\ImportErrorsExport;
use App\Filament\Resources\Attendees\AttendeeResource;
use App\Filament\Resources\Events\EventResource;
use App\Imports\AttendeesImport;
use App\Jobs\GenerateEventBadgesJob;
use App\Models\Event;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;

class ImportAttendees extends Page implements Forms\Contracts\HasForms
{
    use Forms\Concerns\InteractsWithForms;

    protected static string $resource = AttendeeResource::class;

    protected static ?string $title = 'Import Attendees';

    protected string $view = 'filament.resources.attendees.pages.import-attendees';

    public ?array $data = [];

    #[\Livewire\Attributes\Locked]
    public ?int $eventId = null;

    public ?string $eventName = null;

    #[\Livewire\Attributes\Locked]
    public ?string $errorReportPath = null;

    #[\Livewire\Attributes\Locked]
    public ?int $errorReportEventId = null;

    public function mount(): void
    {
        abort_unless(AttendeeResource::canImport(), 403);
        if (request()->has('event_id')) {
            $id = request()->query('event_id');
            abort_unless(is_scalar($id) && ctype_digit((string) $id), 404);
            $this->eventId = (int) $id;
            $this->eventName = $this->authorizedEvent($this->eventId)->name;
        }

        $this->form->fill([
            'event_id' => $this->eventId,
            'file' => null,
            'generate_badges' => true,
        ]);
    }

    private function authorizedEvent(int $id): Event
    {
        abort_unless(AttendeeResource::canImport(), 403);
        $event = Event::query()->accessibleBy(auth()->user())->whereKey($id)->firstOrFail();
        abort_unless($event->canManageRegistrationBy(auth()->user()), 403);

        return $event;
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Import File')
                    ->description('Upload an Excel file containing attendees for the selected event.')
                    ->schema([
                        Forms\Components\Select::make('event_id')
                            ->label('Event')
                            ->options(fn () => Event::query()->accessibleBy(auth()->user())
                                ->when($this->eventId !== null, fn ($query) => $query->whereKey($this->eventId))
                                ->latest()->get()
                                ->filter(fn (Event $event) => $event->canManageRegistrationBy(auth()->user()))
                                ->pluck('name', 'id')->all())
                            ->searchable()
                            ->preload()
                            ->required()
                            ->default(fn () => $this->eventId)
                            ->disabled(fn () => $this->eventId !== null)
                            ->dehydrated(),

                        Forms\Components\FileUpload::make('file')
                            ->label('Excel or CSV File')
                            ->disk('local')
                            ->visibility('private')
                            ->directory(fn () => 'imports/attendees/user-'.auth()->id())
                            ->preventFilePathTampering()
                            ->maxSize(5120)
                            ->acceptedFileTypes([
                                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                                'application/vnd.ms-excel',
                                'text/csv',
                            ])
                            ->required()
                            ->helperText('Accepted columns: full_name, phone, email, organization_name, position, category, badge_type.'),

                        Forms\Components\Checkbox::make('generate_badges')
                            ->label('Generate badges after import')
                            ->helperText('Recommended. This will queue badge generation after attendees are imported.')
                            ->default(true),
                    ])
                    ->columns(1),
            ])
            ->statePath('data');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('download_template')
                ->label('Download Excel Template')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->action(function () {
                    abort_unless(AttendeeResource::canImport(), 403);
                    if ($this->eventId !== null) {
                        $this->authorizedEvent($this->eventId);
                    }
                    return Excel::download(
                        new AttendeesImportTemplateExport(),
                        'eLive-events-attendees-import-template.xlsx'
                    );
                }),

            Action::make('back_to_event')
                ->label('Back to Event')
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->visible(fn () => filled($this->eventId))
                ->url(fn () => EventResource::getUrl('workspace', [
                    'record' => $this->eventId,
                ])),

            Action::make('back_to_attendees')
                ->label('Back to Attendees')
                ->icon('heroicon-o-user-group')
                ->color('gray')
                ->url(fn () => AttendeeResource::getUrl('index', $this->eventId === null ? [] : ['event_id' => $this->eventId])),
        ];
    }

    public function import(): void
    {
        abort_unless(AttendeeResource::canImport(), 403);
        if ($this->eventId !== null) {
            $this->authorizedEvent($this->eventId);
        }
        $data = $this->form->getState();

        $eventId = $this->eventId ?? (int) ($data['event_id'] ?? 0);
        $file = $data['file'] ?? null;
        $generateBadges = (bool) ($data['generate_badges'] ?? false);

        if (! $eventId || ! $file) {
            Notification::make()
                ->title('Missing information')
                ->body('Please select an event and upload an Excel file.')
                ->danger()
                ->send();

            return;
        }

        $event = $this->authorizedEvent($eventId);
        abort_unless(is_string($file) && str_starts_with($file, 'imports/attendees/user-'.auth()->id().'/')
            && ! str_contains($file, '..') && ! str_contains($file, '\\')
            && Storage::disk('local')->exists($file), 422);
        $this->errorReportPath = null;
        $this->errorReportEventId = null;

        $import = new AttendeesImport($eventId);
        \Illuminate\Support\Facades\DB::transaction(function () use ($event, $import, $file): void {
            Event::query()->whereKey($event->id)->lockForUpdate()->firstOrFail();
            $this->authorizedEvent($event->id);
            Excel::import($import, Storage::disk('local')->path($file));
        });

        $message = "Imported: {$import->imported}. Skipped: {$import->skipped}.";

        if (! empty($import->errors)) {
            $this->errorReportPath = $this->storeErrorReport($import->errors, $eventId);
            $this->errorReportEventId = $eventId;

            $message .= ' Error report is available for download.';
        }

        if ($generateBadges && $import->imported > 0) {
            GenerateEventBadgesJob::dispatch($eventId);

            $message .= ' Badge generation has started in the background.';
        }

        Notification::make()
            ->title('Import completed')
            ->body($message)
            ->success()
            ->send();

        if (! empty($import->errors)) {
            Notification::make()
                ->title('Import notes')
                ->body('Some rows were skipped. Download the error report from this page.')
                ->warning()
                ->send();

            return;
        }

        $this->redirect(EventResource::getUrl('edit', [
            'record' => $eventId,
        ]));
    }

    protected function storeErrorReport(array $errors, int $eventId): string
    {
        $fileName = 'imports/errors/user-'.auth()->id().'/event-'.$eventId.'-'.\Illuminate\Support\Str::uuid().'.xlsx';

        Excel::store(
            new ImportErrorsExport($errors),
            $fileName,
            'local'
        );

        return $fileName;
    }

    public function downloadErrorReport(): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        abort_unless($this->errorReportEventId && $this->errorReportPath, 404);
        $this->authorizedEvent($this->errorReportEventId);
        abort_unless(str_starts_with($this->errorReportPath, 'imports/errors/user-'.auth()->id().'/')
            && Storage::disk('local')->exists($this->errorReportPath), 404);

        return Storage::disk('local')->download($this->errorReportPath, 'attendee-import-errors.xlsx');
    }
}
