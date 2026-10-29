<?php

namespace App\Filament\Resources\Incomes;

use App\Filament\Resources\Incomes\Pages\ManageIncomes;
use App\Filament\Support\ScheduleFields;
use App\Models\Income;
use App\Support\Money;
use App\Support\Month;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

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
            Select::make('participant_id')->label(__('incomes.fields.participant'))->relationship('participant', 'name', modifyQueryUsing: fn (Builder $query, Get $get): Builder => $query->where(fn (Builder $query): Builder => $query->whereNull('archived_from_month')->orWhereKey($get('participant_id'))))->searchable()->preload()->required()
                ->helperText(__('incomes.fields.participant_hint')),
            Select::make('sender_id')->label(__('funding.sender_household'))->relationship('sender', 'name', modifyQueryUsing: fn (Builder $query, Get $get): Builder => $query->where(fn (Builder $query): Builder => $query->whereNull('archived_from_month')->orWhereKey($get('sender_id'))))->searchable()->preload()->live()
                ->helperText(__('funding.sender_household_help'))
                ->afterStateUpdated(function (Set $set, mixed $state): void {
                    if ($state) {
                        $set('sender_name', null);
                        $set('affects_allocation', false);
                    }
                }),
            TextInput::make('sender_name')->label(__('funding.sender_external'))->maxLength(255)
                ->helperText(__('funding.sender_external_help'))
                ->disabled(fn (Get $get): bool => (bool) $get('sender_id')),
            Toggle::make('include_in_payment_plan')->label(__('funding.include_payment'))->default(false)
                ->helperText(__('funding.include_payment_help')),
            Toggle::make('affects_allocation')->label(__('funding.affects_allocation'))->default(true)
                ->helperText(__('funding.allocation_help'))
                ->disabled(fn (Get $get): bool => (bool) ($get('budget_id') || $get('expense_id') || $get('sender_id')))
                ->dehydrated(),
            Select::make('budget_id')->label(__('funding.budget'))->relationship('budget', 'name')->searchable()->preload()->live()
                ->helperText(__('funding.budget_income_help'))
                ->createOptionForm([TextInput::make('name')->label(__('funding.name'))->required()->maxLength(255)])
                ->afterStateUpdated(function (Set $set, mixed $state): void {
                    if ($state) {
                        $set('expense_id', null);
                        $set('affects_allocation', false);
                    }
                }),
            Select::make('expense_id')->label(__('funding.refund_for'))->relationship('expense', 'name')->searchable()->preload()->live()
                ->helperText(__('funding.refund_help'))
                ->afterStateUpdated(function (Set $set, mixed $state): void {
                    if ($state) {
                        $set('budget_id', null);
                        $set('affects_allocation', false);
                    }
                }),
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
            TextColumn::make('sender_display')->label(__('funding.sender'))
                ->getStateUsing(fn (Income $record): ?string => $record->sender?->name ?? $record->sender_name)->placeholder('–')->toggleable(),
            IconColumn::make('include_in_payment_plan')->label(__('funding.include_payment'))->boolean()->toggleable(),
            IconColumn::make('affects_allocation')->label(__('funding.affects_allocation'))->boolean(),
            TextColumn::make('budget.name')->label(__('funding.budget'))->placeholder('–')->toggleable(),
            TextColumn::make('expense.name')->label(__('funding.refund_for'))->placeholder('–')->toggleable(),
            TextColumn::make('start_month')->formatStateUsing(fn (string $state): string => Month::format($state))->label(__('incomes.table.from'))->sortable()->toggleable(),
            TextColumn::make('end_month')->formatStateUsing(fn (string $state): string => Month::format($state))->label(__('incomes.table.until'))->placeholder(__('schedule.unlimited'))->toggleable(),
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
