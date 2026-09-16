<?php

namespace App\Filament\Resources\EventTicketTemplates\Pages;

use App\Filament\Resources\EventTicketTemplates\EventTicketTemplateResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditEventTicketTemplate extends EditRecord
{
    protected static string $resource =
        EventTicketTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('design')
                ->label('Design Ticket')
                ->icon('heroicon-o-paint-brush')
                ->url(
                    fn (): string =>
                        EventTicketTemplateResource::getUrl(
                            'designer',
                            [
                                'record' => $this->getRecord(),
                            ]
                        )
                ),

            DeleteAction::make(),
        ];
    }
}