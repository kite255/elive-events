<?php

namespace App\Filament\Resources\Attendees\Pages;

use App\Filament\Resources\Attendees\AttendeeResource;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewAttendee extends ViewRecord
{
    protected static string $resource = AttendeeResource::class;

    protected string $view =
        'filament.resources.attendees.pages.view-attendee';

    public function mount(int|string $record): void
    {
        parent::mount($record);

        $this->getRecord()->loadMissing([
            'event',
            'category',
            'badgeType',
            'eventDays',
            'registrationAnswers.registrationField',
            'merchandiseSelections.merchandise',
            'merchandiseSelections.variant',
            'checkIns.checkInPoint',
        ]);
    }

    public function getTitle(): string
    {
        return $this->getRecord()->full_name ?: 'Attendee Profile';
    }

    public function getSubheading(): ?string
    {
        $record = $this->getRecord();

        return collect([
            $record->event?->name,
            $record->badge_number,
        ])->filter()->implode(' • ') ?: null;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('open_public_page')
                ->label('Open Public Page')
                ->icon('heroicon-o-arrow-top-right-on-square')
                ->color('success')
                ->visible(
                    fn (): bool => filled(
                        $this->getRecord()->public_token
                    )
                )
                ->url(
                    fn (): string =>
                        $this->getRecord()->publicUrl()
                )
                ->openUrlInNewTab(),

            EditAction::make()
                ->label('Edit Attendee'),
        ];
    }
}
