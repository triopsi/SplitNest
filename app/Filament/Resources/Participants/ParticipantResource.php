<?php

namespace App\Filament\Resources\Participants;

use App\Filament\Resources\Participants\Pages\ManageParticipants;
use App\Models\Participant;
use App\Services\ArchiveParticipant;
use App\Support\Month;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

/**
 * Filament resource for managing household participants.
 *
 * Deletion is available for unused records while linked household data stays protected.
 */
class ParticipantResource extends Resource
{
    protected static ?string $model = Participant::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?int $navigationSort = 3;

    /**
     * Singular and plural model label (translated).
     */
    public static function getModelLabel(): string
    {
        return __('participants.label');
    }

    /**
     * Plural model label (translated).
     */
    public static function getPluralModelLabel(): string
    {
        return __('participants.label');
    }

    /**
     * Sidebar group (translated).
     */
    public static function getNavigationGroup(): string
    {
        return __('nav.groups.household');
    }

    /**
     * Define the create/edit form for a participant.
     */
    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label(__('participants.fields.name'))->required()->maxLength(255),
            TextInput::make('email')->label(__('participants.fields.email'))->email()->maxLength(255),
            Toggle::make('only_own_report')->label(__('participants.fields.only_own_report'))->default(false)
                ->helperText(__('participants.fields.only_own_report_hint'))->columnSpanFull(),
            Textarea::make('notes')->label(__('participants.fields.notes'))->columnSpanFull(),
        ]);
    }

    /**
     * Define participant editing and confirmed deletion with an explanation for protected records.
     */
    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name')->label(__('participants.table.name'))->searchable()->sortable()->html()
                ->formatStateUsing(fn (Participant $record): string => view('components.person', ['name' => $record->name, 'email' => $record->email])->render()),
            TextColumn::make('email')->label(__('participants.table.email'))->placeholder(__('participants.table.no_email')),
            IconColumn::make('only_own_report')->label(__('participants.table.only_own_report'))->boolean(),
            TextColumn::make('archived_from_month')->label(__('participants.archive.from'))
                ->formatStateUsing(fn (string $state): string => Month::format($state))
                ->placeholder(__('participants.archive.active'))->badge(),
        ])->filters([
            TernaryFilter::make('archived_from_month')->label(__('participants.archive.filter'))->nullable()
                ->trueLabel(__('participants.archive.archived'))->falseLabel(__('participants.archive.active'))->default(false),
        ])->recordActions([
            EditAction::make(),
            Action::make('archive')->label(__('participants.archive.action'))->icon('heroicon-o-archive-box')->color('warning')
                ->visible(fn (Participant $record): bool => $record->archived_from_month === null)
                ->requiresConfirmation()->modalDescription(__('participants.archive.help'))
                ->schema([TextInput::make('archived_from_month')->label(__('participants.archive.from'))->type('month')->required()->rules(['date_format:Y-m'])->default(now()->format('Y-m'))])
                ->action(fn (Participant $record, array $data) => app(ArchiveParticipant::class)->archive($record, $data['archived_from_month'])),
            Action::make('restore')->label(__('participants.archive.restore'))->icon('heroicon-o-arrow-uturn-left')
                ->visible(fn (Participant $record): bool => $record->archived_from_month !== null)->requiresConfirmation()
                ->action(fn (Participant $record): bool => $record->forceFill(['archived_from_month' => null])->save()),
            DeleteAction::make()->requiresConfirmation()
                ->modalDescription(__('participants.delete_confirmation'))
                ->disabled(fn (Participant $record): bool => $record->deletionBlockReason() !== null)
                ->tooltip(fn (Participant $record): ?string => $record->deletionBlockReason()),
        ]);
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
