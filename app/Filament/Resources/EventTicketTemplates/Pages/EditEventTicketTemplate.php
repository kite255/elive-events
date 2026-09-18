<?php

namespace App\Filament\Resources\EventTicketTemplates\Pages;

use App\Filament\Resources\EventTicketTemplates\EventTicketTemplateResource;
use App\Services\Tickets\TicketDesignerService;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditEventTicketTemplate extends EditRecord
{
    protected static string $resource =
        EventTicketTemplateResource::class;

    protected function handleRecordUpdate(
        Model $record,
        array $data
    ): Model {
        $oldWidth = (int) $record->width;
        $oldHeight = (int) $record->height;

        $newWidth = (int) ($data['width'] ?? $oldWidth);
        $newHeight = (int) ($data['height'] ?? $oldHeight);

        app(TicketDesignerService::class)->resizeTemplate(
            TicketDesignerService::TYPE_EVENT,
            (int) $record->getKey(),
            $oldWidth,
            $oldHeight,
            $newWidth,
            $newHeight
        );

        $record->update($data);

        return $record->refresh();
    }

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