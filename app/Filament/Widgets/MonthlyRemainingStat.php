<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\InteractsWithSettlementReport;
use App\Support\Money;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Displays the household budget remaining after the selected month's costs.
 */
class MonthlyRemainingStat extends StatsOverviewWidget
{
    use InteractsWithSettlementReport;

    protected static bool $isDiscovered = false;

    protected static bool $isLazy = false;

    protected ?string $pollingInterval = null;

    protected int|string|array $columnSpan = 1;

    protected int|array|null $columns = 1;

    /**
     * Build the translated statistic using the shared monthly report.
     *
     * @return array<Stat>
     */
    protected function getStats(): array
    {
        $report = $this->settlementReport;
        $amount = $report['total_income_cents'] - $report['total_expense_cents'];

        return [
            Stat::make(__('settlement.cards.left'), Money::format($amount))
                ->description(__('settlement.cards.left_hint'))
                ->icon(Heroicon::OutlinedWallet)
                ->color($amount < 0 ? 'danger' : 'primary'),
        ];
    }
}
