<?php

namespace App\Filament\Resources\Events\RelationManagers;

use App\Models\Event;
use App\Models\EventTicketTemplate;
use App\Models\User;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class TicketTemplatesRelationManager extends RelationManager
{
    protected static string $relationship = 'ticketTemplates';

    protected static ?string $title = 'Ticket Templates';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        $user = auth()->user();

        return $ownerRecord instanceof Event
            && $user instanceof User && ! $user->is_gate_staff
            && $ownerRecord->isAccessibleBy($user)
            && $ownerRecord->canBeManagedBy($user)
            && ($ownerRecord->ticketSetting?->ticket_sales_enabled
                || $ownerRecord->ticketTypes()->exists()
                || $ownerRecord->ticketTemplates()->exists());
    }

    private function authorizeEvent(): Event
    {
        $event = Event::query()->findOrFail($this->getOwnerRecord()->getKey());
        abort_unless(static::canViewForRecord($event, \App\Filament\Resources\Events\Pages\EditEvent::class), 403);

        return $event;
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->required()->maxLength(255),
            Select::make('ticket_type_id')->label('Ticket type')
                ->options(fn () => $this->authorizeEvent()->ticketTypes()->orderBy('name')->pluck('name', 'id'))
                ->searchable()->placeholder('All ticket types'),
            TextInput::make('width')->label('Canvas width (px)')->integer()->minValue(100)->maxValue(2400)->default(900)->required(),
            TextInput::make('height')->label('Canvas height (px)')->integer()->minValue(100)->maxValue(2400)->default(450)->required(),
        ]);
    }

    private function draftData(array $data): array
    {
        $event = $this->authorizeEvent();
        if (filled($data['ticket_type_id'] ?? null)
            && ! $event->ticketTypes()->whereKey($data['ticket_type_id'])->exists()) {
            throw ValidationException::withMessages(['ticket_type_id' => 'Select a ticket type belonging to this event.']);
        }

        return [
            'event_id' => $event->id,
            'name' => $data['name'],
            'ticket_type_id' => $data['ticket_type_id'] ?? null,
            'width' => $data['width'],
            'height' => $data['height'],
            'is_active' => false,
            'is_default' => false,
        ];
    }

    public function table(Table $table): Table
    {
        return $table->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('name')->searchable(),
                TextColumn::make('ticketType.name')->label('Ticket type')->placeholder('All ticket types'),
                TextColumn::make('width')->label('Width'),
                TextColumn::make('height')->label('Height'),
                TextColumn::make('pages_count')->counts('pages')->label('Pages'),
                TextColumn::make('is_active')->label('Status')->formatStateUsing(fn ($state) => $state ? 'Active' : 'Draft'),
            ])
            ->headerActions([
                CreateAction::make()->label('New draft')->mutateDataUsing(fn (array $data): array => $this->draftData($data)),
            ])
            ->recordActions([
                EditAction::make()->label('Edit draft')
                    ->visible(fn (EventTicketTemplate $record): bool => ! $record->is_active)
                    ->mutateDataUsing(function (array $data, EventTicketTemplate $record): array {
                        $event = $this->authorizeEvent();
                        abort_unless($event->ticketTemplates()->whereKey($record->id)->where('is_active', false)->exists(), 403);

                        return $this->draftData($data);
                    }),
            ])->defaultSort('id', 'desc');
    }
}
