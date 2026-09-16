<?php

namespace App\Filament\Resources\OrganizationTicketTemplates\Pages;

use App\Filament\Resources\OrganizationTicketTemplates\OrganizationTicketTemplateResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditOrganizationTicketTemplate extends EditRecord
{
    protected static string $resource =
        OrganizationTicketTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('design')
                ->label('Design Ticket')
                ->icon('heroicon-o-paint-brush')
                ->url(
                    fn (): string =>
                        OrganizationTicketTemplateResource::getUrl(
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