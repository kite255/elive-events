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

    public ?int $eventId = null;

    public ?string $eventName = null;

    public ?string $errorReportUrl = null;

    public function mount(): void
    {
        $this->eventId = request()->integer('event_id') ?: null;

        if ($this->eventId) {
            $event = Event::query()->find($this->eventId);
            $this->eventName = $event?->name;
        }

        $this->form->fill([
            'event_id' => $this->eventId,
            'file' => null,
            'generate_badges' => true,
        ]);
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
                            ->options(fn () => Event::query()
                                ->latest()
                                ->pluck('name', 'id')
                                ->toArray())
                            ->searchable()
                            ->preload()
                            ->required()
                            ->default(fn () => request()->integer('event_id') ?: null)
                            ->disabled(fn () => request()->filled('event_id'))
                            ->dehydrated(),

                        Forms\Components\FileUpload::make('file')
                            ->label('Excel File')
                            ->disk('public')
                            ->directory('imports/attendees')
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
                ->url(fn () => EventResource::getUrl('edit', [
                    'record' => $this->eventId,
                ])),

            Action::make('back_to_attendees')
                ->label('Back to Attendees')
                ->icon('heroicon-o-user-group')
                ->color('gray')
                ->url(fn () => AttendeeResource::getUrl('index')),
        ];
    }

    public function import(): void
    {
        $data = $this->form->getState();

        $eventId = (int) ($data['event_id'] ?? request()->integer('event_id'));
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

        $import = new AttendeesImport($eventId);

        Excel::import($import, Storage::disk('public')->path($file));

        $message = "Imported: {$import->imported}. Skipped: {$import->skipped}.";

        if (! empty($import->errors)) {
            $this->errorReportUrl = $this->storeErrorReport($import->errors, $eventId);

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
        $fileName = 'imports/errors/event-' . $eventId . '-import-errors-' . now()->format('Y-m-d-His') . '.xlsx';

        Excel::store(
            new ImportErrorsExport($errors),
            $fileName,
            'public'
        );

        return Storage::disk('public')->url($fileName);
    }
}