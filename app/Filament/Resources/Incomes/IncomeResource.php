<?php

namespace App\Filament\Resources\Incomes;

use App\Filament\Resources\Incomes\Pages\ManageIncomes;
use App\Filament\Support\ScheduleFields;
use App\Models\Income;
use App\Support\Money;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/**
 * Filament resource for managing participant incomes.
 */
class IncomeResource extends Resource
{
    protected static ?string $model = Income::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static ?string $modelLabel = 'Einkunft';

    protected static ?string $pluralModelLabel = 'Einkünfte';

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?int $navigationSort = 4;

    /**
     * Define the create/edit form for an income.
     */
    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label('Bezeichnung')->placeholder('Nettogehalt')->required()->maxLength(255),
            Select::make('participant_id')->label('Teilnehmer')->relationship('participant', 'name')->searchable()->preload()->required(),
            ...ScheduleFields::make(),
        ]);
    }

    /**
     * Define the incomes table (columns, participant filter, record actions).
     */
    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name')->label('Bezeichnung')->searchable(),
            TextColumn::make('participant.name')->label('Teilnehmer')->sortable()->html()
                ->formatStateUsing(fn (Income $record): string => view('components.person', ['name' => $record->participant->name, 'email' => $record->participant->email])->render()),
            TextColumn::make('amount_cents')->label('Betrag')->formatStateUsing(fn ($state) => Money::format($state))->sortable(),
            TextColumn::make('start_month')->label('Ab')->sortable(),
            TextColumn::make('end_month')->label('Bis')->placeholder('Unbegrenzt'),
            TextColumn::make('interval_months')->label('Intervall')->formatStateUsing(fn ($state) => $state ? "Alle {$state} Monate" : 'Einmalig'),
        ])->filters([SelectFilter::make('participant')->relationship('participant', 'name')->label('Teilnehmer')])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    /**
     * Register the resource pages (single manage page).
     *
     * @return array<string, PageRegistration>
     */
    public static function getPages(): array
    {
        return ['index' => ManageIncomes::route('/')];
    }
}
