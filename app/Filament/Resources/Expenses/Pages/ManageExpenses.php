<?php

namespace App\Filament\Resources\Expenses\Pages;

use App\Filament\Resources\Expenses\ExpenseResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Components\Attributes\Unexposed;

/**
 * Single-page CRUD screen for expenses.
 */
class ManageExpenses extends ManageRecords
{
    protected static string $resource = ExpenseResource::class;

    /** Explain expense fields and allocation before the list, with optional detailed budget guidance. */
    #[Unexposed]
    public function content(Schema $schema): Schema
    {
        $schema = parent::content($schema);

        return $schema->components([
            View::make('filament.expenses-help'),
            View::make('filament.budget-help')->viewData(['collapsed' => true]),
            ...$schema->getComponents(),
        ]);
    }

    /**
     * Header actions: create a new expense.
     *
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
