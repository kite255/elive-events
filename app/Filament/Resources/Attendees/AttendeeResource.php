<?php

namespace App\Filament\Resources\Attendees;

use App\Filament\Resources\Attendees\Pages\CreateAttendee;
use App\Filament\Resources\Attendees\Pages\EditAttendee;
use App\Filament\Resources\Attendees\Pages\ImportAttendees;
use App\Filament\Resources\Attendees\Pages\ListAttendees;
use App\Filament\Resources\Attendees\Pages\ViewAttendee;
use App\Filament\Resources\Attendees\Pages\ViewAttendeeQrCode;
use App\Filament\Resources\Attendees\Schemas\AttendeeForm;
use App\Filament\Resources\Attendees\Tables\AttendeesTable;
use App\Models\Attendee;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class AttendeeResource extends Resource
{
    protected static ?string $model = Attendee::class;

    protected static string|BackedEnum|null $navigationIcon =
        Heroicon::OutlinedUserGroup;

    protected static ?string $recordTitleAttribute = 'full_name';

    protected static ?string $navigationLabel = 'Attendees';

    protected static ?string $modelLabel = 'Attendee';

    protected static ?string $pluralModelLabel = 'Attendees';

    protected static string|UnitEnum|null $navigationGroup =
        'Event Management';

    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return AttendeeForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AttendeesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAttendees::route('/'),
            'create' => CreateAttendee::route('/create'),
            'import' => ImportAttendees::route('/import'),
            'view' => ViewAttendee::route('/{record}'),
            'edit' => EditAttendee::route('/{record}/edit'),
            'qr-code' => ViewAttendeeQrCode::route(
                '/{record}/qr-code'
            ),
        ];
    }
}
