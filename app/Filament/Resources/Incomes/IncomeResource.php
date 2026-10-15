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

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?int $navigationSort = 4;

    /**
     * Singular model label (translated).
     */
    public static function getModelLabel(): string
    {
        return __('incomes.label');
    }

    /**
     * Plural model label (translated).
     */
    public static function getPluralModelLabel(): string
    {
        return __('incomes.plural');
    }

    /**
     * Sidebar group (translated).
     */
    public static function getNavigationGroup(): string
    {
        return __('nav.groups.household');
    }

    /**
     * Define the create/edit form for an income.
     */
    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label(__('incomes.fields.name'))->placeholder(__('incomes.fields.name_placeholder'))->required()->maxLength(255),
            Select::make('participant_id')->label(__('incomes.fields.participant'))->relationship('participant', 'name')->searchable()->preload()->required(),
            ...ScheduleFields::make(),
        ]);
    }

    /**
     * Define the incomes table (columns, participant filter, record actions).
     */
    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name')->label(__('incomes.table.name'))->searchable()->toggleable(),
            TextColumn::make('participant.name')->label(__('incomes.table.participant'))->sortable()->html()->toggleable()
                ->formatStateUsing(fn (Income $record): string => view('components.person', ['name' => $record->participant->name, 'email' => $record->participant->email])->render()),
            TextColumn::make('amount_cents')->label(__('incomes.table.amount'))->formatStateUsing(fn ($state) => Money::format($state))->sortable()->toggleable(),
            TextColumn::make('start_month')->label(__('incomes.table.from'))->sortable()->toggleable(),
            TextColumn::make('end_month')->label(__('incomes.table.until'))->placeholder(__('schedule.unlimited'))->toggleable(),
            TextColumn::make('interval_months')->label(__('incomes.table.interval'))->formatStateUsing(fn ($state) => $state ? __('schedule.every_x_months', ['count' => $state]) : __('schedule.one_off'))->toggleable(),
        ])->filters([SelectFilter::make('participant')->relationship('participant', 'name')->label(__('incomes.table.participant'))])
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
