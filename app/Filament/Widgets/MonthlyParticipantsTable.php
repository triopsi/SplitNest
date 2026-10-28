<?php

namespace App\Filament\Widgets;

use App\Filament\Pages\ParticipantMonth;
use App\Filament\Widgets\Concerns\InteractsWithSettlementReport;
use App\Support\Money;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Contracts\View\View;

/**
 * Shows each participant's income, cost share, balance and remaining budget.
 */
class MonthlyParticipantsTable extends TableWidget
{
    use InteractsWithSettlementReport;

    protected static bool $isDiscovered = false;

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    /**
     * Configure participant rows, monthly detail links and cent-exact monetary columns.
     */
    public function table(Table $table): Table
    {
        return $table
            ->heading(__('settlement.overview.title'))
            ->records(fn (): array => collect($this->settlementReport['participants'])->keyBy('id')->all())
            ->paginated(false)
            ->columns([
                TextColumn::make('name')
                    ->label(__('settlement.overview.participant'))
                    ->formatStateUsing(fn (array $record): View => view('components.person', [
                        'name' => $record['name'],
                        'email' => $this->emails[$record['id']] ?? null,
                        'href' => ParticipantMonth::getUrl(['participant' => $record['id'], 'month' => $this->month]),
                    ])),
                $this->moneyColumn('income_cents', 'income'),
                TextColumn::make('allocation_income_cents')->label(__('funding.allocation_income'))
                    ->state(fn (array $record): int => $record['allocation_income_cents'] ?? $record['income_cents'])
                    ->alignEnd()->formatStateUsing(fn (int $state): string => Money::format($state))->toggleable(),
                TextColumn::make('other_income_cents')->label(__('funding.other_income'))
                    ->state(fn (array $record): int => $record['other_income_cents'] ?? 0)
                    ->alignEnd()->formatStateUsing(fn (int $state): string => Money::format($state))->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('reserved_cents')->label(__('funding.reserved'))
                    ->state(fn (array $record): int => $record['reserved_cents'] ?? 0)
                    ->alignEnd()->formatStateUsing(fn (int $state): string => Money::format($state))->toggleable(),
                $this->moneyColumn('share_cents', 'share'),
                TextColumn::make('share_percentage')
                    ->label(__('settlement.overview.share_pct'))
                    ->alignEnd()
                    ->state(fn (array $record): string => $this->settlementReport['total_expense_cents'] > 0
                        ? number_format(round($record['share_cents'] * 100 / $this->settlementReport['total_expense_cents']), 0, ',', '').' %'
                        : '–'),
                $this->moneyColumn('paid_cents', 'paid'),
                $this->moneyColumn('balance_cents', 'balance'),
                $this->moneyColumn('remaining_cents', 'left')
                    ->weight(FontWeight::Bold)
                    ->color(fn (int $state): ?string => $state < 0 ? 'danger' : null),
            ])
            ->contentFooter(fn (): View => view('filament.widgets.monthly-participants-footer', ['report' => $this->settlementReport]));
    }

    /**
     * Build a right-aligned euro column from a report field stored in cents.
     */
    private function moneyColumn(string $name, string $label): TextColumn
    {
        return TextColumn::make($name)
            ->label(__('settlement.overview.'.$label))
            ->alignEnd()
            ->formatStateUsing(fn (int $state): string => Money::format($state));
    }
}
