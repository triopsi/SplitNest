<?php

namespace App\Filament\Resources\Budgets;

use App\Filament\Resources\Budgets\Pages\ManageBudgets;
use App\Models\Budget;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/** Manage earmarked pools; retained pools preserve their historical carryovers. */
class BudgetResource extends Resource
{
    protected static ?string $model = Budget::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedWallet;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?int $navigationSort = 6;

    /** Singular translated pool label. */
    public static function getModelLabel(): string
    {
        return __('funding.budget');
    }

    /** Plural translated pool label. */
    public static function getPluralModelLabel(): string
    {
        return __('funding.budgets');
    }

    /** Place budgets beside incomes and expenses in the sidebar. */
    public static function getNavigationGroup(): string
    {
        return __('nav.groups.household');
    }

    /** Name a pool and explain how its receipts and expenses are assigned. */
    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label(__('funding.name'))->required()->maxLength(255)
                ->helperText(__('funding.budget_help')),
        ]);
    }

    /** List pools without destructive actions on their historical balances. */
    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name')->label(__('funding.name'))->searchable()->sortable(),
            TextColumn::make('incomes_count')->counts('incomes')->label(__('incomes.plural')),
            TextColumn::make('expenses_count')->counts('expenses')->label(__('expenses.plural')),
        ])->recordActions([EditAction::make()]);
    }

    /** Register the modal-based budget management page.
     * @return array<string, PageRegistration>
     */
    public static function getPages(): array
    {
        return ['index' => ManageBudgets::route('/')];
    }
}
