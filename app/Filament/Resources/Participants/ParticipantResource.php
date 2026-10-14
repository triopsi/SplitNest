<?php

namespace App\Filament\Resources\Participants;

use App\Filament\Resources\Participants\Pages\ManageParticipants;
use App\Models\Participant;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Filament resource for managing household participants.
 *
 * Participants cannot be deleted here on purpose: historical closings keep
 * referring to them, so the table offers no delete action at all.
 */
class ParticipantResource extends Resource
{
    protected static ?string $model = Participant::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static ?string $modelLabel = 'Teilnehmer';

    protected static ?string $pluralModelLabel = 'Teilnehmer';

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?int $navigationSort = 3;

    /**
     * Define the create/edit form for a participant.
     */
    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label('Name')->required()->maxLength(255),
            TextInput::make('email')->label('E-Mail (für spätere Benachrichtigungen)')->email()->maxLength(255),
            Textarea::make('notes')->label('Notizen')->columnSpanFull(),
        ]);
    }

    /**
     * Define the participants table (edit only, no delete action).
     */
    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name')->label('Name')->searchable()->sortable()->html()
                ->formatStateUsing(fn (Participant $record): string => view('components.person', ['name' => $record->name, 'email' => $record->email])->render()),
            TextColumn::make('email')->label('E-Mail')->placeholder('Noch nicht hinterlegt'),
        ])->recordActions([EditAction::make()]);
    }

    /**
     * Register the resource pages (single manage page).
     *
     * @return array<string, PageRegistration>
     */
    public static function getPages(): array
    {
        return ['index' => ManageParticipants::route('/')];
    }
}
