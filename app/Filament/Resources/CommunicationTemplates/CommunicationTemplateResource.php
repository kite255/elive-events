<?php

namespace App\Filament\Resources\CommunicationTemplates;

use App\Filament\Resources\CommunicationTemplates\Pages\CreateCommunicationTemplate;
use App\Filament\Resources\CommunicationTemplates\Pages\EditCommunicationTemplate;
use App\Filament\Resources\CommunicationTemplates\Pages\ListCommunicationTemplates;
use App\Filament\Resources\CommunicationTemplates\Schemas\CommunicationTemplateForm;
use App\Filament\Resources\CommunicationTemplates\Tables\CommunicationTemplatesTable;
use App\Models\CommunicationTemplate;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class CommunicationTemplateResource extends Resource
{
    protected static ?string $model =
        CommunicationTemplate::class;

    protected static string|BackedEnum|null $navigationIcon =
        Heroicon::OutlinedChatBubbleLeftRight;

    protected static ?string $recordTitleAttribute =
        'name';

    protected static ?string $navigationLabel =
        'Message Templates';

    protected static ?string $modelLabel =
        'Message Template';

    protected static ?string $pluralModelLabel =
        'Message Templates';

    protected static string|UnitEnum|null $navigationGroup =
        'Registration & Communication';

    protected static ?int $navigationSort = 1;

    /*
    |--------------------------------------------------------------------------
    | Ticket Organizer Access
    |--------------------------------------------------------------------------
    */

    public static function shouldRegisterNavigation(): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        if ($user->isTicketOrganizer()) {
            return false;
        }

        return parent::shouldRegisterNavigation();
    }

    public static function canViewAny(): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        if ($user->isTicketOrganizer()) {
            return false;
        }

        return parent::canViewAny();
    }

    public static function canCreate(): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        if ($user->isTicketOrganizer()) {
            return false;
        }

        return parent::canCreate();
    }

    public static function canEdit(
        $record
    ): bool {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        if ($user->isTicketOrganizer()) {
            return false;
        }

        return parent::canEdit(
            $record
        );
    }

    public static function canDelete(
        $record
    ): bool {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        if ($user->isTicketOrganizer()) {
            return false;
        }

        return parent::canDelete(
            $record
        );
    }

    public static function canDeleteAny(): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        if ($user->isTicketOrganizer()) {
            return false;
        }

        return parent::canDeleteAny();
    }

    /*
    |--------------------------------------------------------------------------
    | Form / Table
    |--------------------------------------------------------------------------
    */

    public static function form(
        Schema $schema
    ): Schema {
        return CommunicationTemplateForm::configure(
            $schema
        );
    }

    public static function table(
        Table $table
    ): Table {
        return CommunicationTemplatesTable::configure(
            $table
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Relations
    |--------------------------------------------------------------------------
    */

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Pages
    |--------------------------------------------------------------------------
    */

    public static function getPages(): array
    {
        return [
            'index' =>
                ListCommunicationTemplates::route(
                    '/'
                ),

            'create' =>
                CreateCommunicationTemplate::route(
                    '/create'
                ),

            'edit' =>
                EditCommunicationTemplate::route(
                    '/{record}/edit'
                ),
        ];
    }
}