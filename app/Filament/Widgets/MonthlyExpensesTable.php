<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Expenses\ExpenseResource;
use App\Filament\Widgets\Concerns\InteractsWithSettlementReport;
use App\Models\Expense;
use App\Services\SettlementCalculator;
use App\Support\Money;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Contracts\View\View;

/**
 * Shows scheduled expenses and their allocations from the selected month's report.
 */
class MonthlyExpensesTable extends TableWidget
{
    use InteractsWithSettlementReport;

    protected static bool $isDiscovered = false;

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    /**
     * Configure expense rows, payer details and one allocation column per participant.
     */
    public function table(Table $table): Table
    {
        return $table
            ->heading(__('settlement.costs.title'))
            ->description(__('funding.cost_allocation_help'))
            ->records(fn (): array => collect($this->settlementReport['expenses'])->keyBy('id')->all())
            ->paginated(false)
            ->emptyStateHeading(__('settlement.costs.empty'))
            ->headerActions([
                Action::make('manageExpenses')
                    ->label(__('settlement.costs.manage'))
                    ->url(ExpenseResource::getUrl()),
            ])
            ->columns([
                TextColumn::make('name')->label(__('settlement.costs.item')),
                TextColumn::make('payer_id')
                    ->label(__('settlement.costs.payer'))
                    ->formatStateUsing(fn (int $state): View => view('components.person', [
                        'name' => collect($this->settlementReport['participants'])->firstWhere('id', $state)['name'],
                        'email' => $this->emails[$state] ?? null,
                    ])),
                TextColumn::make('split_mode')
                    ->label(__('settlement.costs.rule'))
                    ->formatStateUsing(fn (string $state): string => Expense::modeLabels()[$state]),
                TextColumn::make('amount_cents')
                    ->label(__('settlement.costs.total'))
                    ->alignEnd()
                    ->formatStateUsing(fn (int $state): string => Money::format($state)),
                TextColumn::make('funded_cents')->label(__('funding.financing_and_remainder'))
                    ->state(fn (array $record): int => ($record['budget_cents'] ?? 0) + ($record['reimbursed_cents'] ?? 0))
                    ->alignEnd()->formatStateUsing(fn (array $record): View => view('filament.widgets.monthly-expense-funding', compact('record')))->toggleable(),
                ...array_map(fn (array $participant): TextColumn => TextColumn::make('allocation_'.$participant['id'])
                    ->label(view('components.person', [
                        'name' => $participant['name'],
                        'email' => $this->emails[$participant['id']] ?? null,
                    ]))
                    ->alignEnd()
                    ->state(fn (array $record): int => $record['allocations'][$participant['id']] ?? 0)
                    ->formatStateUsing(fn (array $record): View => $this->allocationCell($record, $participant['id'])), $this->settlementReport['participants']),
            ]);
    }

    /** Explain saved totals using only the snapshot's remaining amount and original split weights.
     * @param  array<string, mixed>  $record
     */
    private function allocationCell(array $record, int $participantId): View
    {
        $totalCents = $record['allocations'][$participantId] ?? 0;
        $remainderCents = $record['uncovered_cents'] ?? $record['amount_cents'];
        if (array_key_exists('uncovered_cents', $record)) {
            $shares = $remainderCents > 0 && array_sum($record['weights']) > 0
                ? app(SettlementCalculator::class)->allocate($remainderCents, $record['weights']) : [];
            $ownCents = $shares[$participantId] ?? 0;
        } else {
            $ownCents = $totalCents;
        }
        $fundingCents = $totalCents - $ownCents;

        return view('filament.widgets.monthly-expense-allocation', compact('totalCents', 'remainderCents', 'ownCents', 'fundingCents'));
    }
}
