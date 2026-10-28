<?php

namespace App\Filament\Resources\Incomes\Pages;

use App\Filament\Resources\Incomes\IncomeResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Components\Attributes\Unexposed;

/**
 * Single-page CRUD screen for incomes.
 */
class ManageIncomes extends ManageRecords
{
    protected static string $resource = IncomeResource::class;

    /** Explain the household-wide income list before displaying its records. */
    #[Unexposed]
    public function content(Schema $schema): Schema
    {
        $schema = parent::content($schema);

        return $schema->components([
            View::make('filament.incomes-help'),
            ...$schema->getComponents(),
        ]);
    }

    /**
     * Header actions: create a new income.
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
