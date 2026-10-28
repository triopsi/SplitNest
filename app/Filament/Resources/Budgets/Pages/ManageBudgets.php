<?php

namespace App\Filament\Resources\Budgets\Pages;

use App\Filament\Resources\Budgets\BudgetResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Components\Attributes\Unexposed;

/** Manage earmarked pools through create and edit modals. */
class ManageBudgets extends ManageRecords
{
    protected static string $resource = BudgetResource::class;

    /** Explain purpose, funding and carryovers before showing the available pools. */
    #[Unexposed]
    public function content(Schema $schema): Schema
    {
        $schema = parent::content($schema);

        return $schema->components([
            View::make('filament.budget-help'),
            ...$schema->getComponents(),
        ]);
    }

    /** Offer creation of a pool without adding example data automatically.
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
