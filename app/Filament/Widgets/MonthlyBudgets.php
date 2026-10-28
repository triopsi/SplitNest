<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\InteractsWithSettlementReport;
use App\Support\Money;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Contracts\View\View;

/** Display live or frozen budget balances as an independently placeable monthly widget. */
class MonthlyBudgets extends TableWidget
{
    use InteractsWithSettlementReport;

    protected static bool $isDiscovered = false;

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    /** Show each pool's opening reserve, receipts, spending and remaining funds. */
    public function table(Table $table): Table
    {
        return $table->heading(__('funding.budget_summary'))
            ->records(fn (): array => collect($this->settlementReport['budgets'] ?? [])->keyBy('id')->all())
            ->paginated(false)
            ->emptyStateHeading(__('funding.no_budgets'))
            ->columns([
                TextColumn::make('name')->label(__('funding.budget')),
                ...array_map(fn (string $field): TextColumn => TextColumn::make($field.'_cents')
                    ->label(__('funding.'.($field === 'income' ? 'receipts' : $field)))->alignEnd()
                    ->formatStateUsing(fn (int $state): string => Money::format($state)), ['opening', 'income', 'spent', 'closing']),
            ])->contentFooter(fn (): View => view('filament.widgets.monthly-budgets-footer'));
    }
}
