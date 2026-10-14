<?php

namespace App\Filament\Resources\Incomes\Pages;

use App\Filament\Resources\Incomes\IncomeResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

/**
 * Single-page CRUD screen for incomes.
 */
class ManageIncomes extends ManageRecords
{
    protected static string $resource = IncomeResource::class;

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
