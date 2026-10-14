<?php

namespace App\Filament\Widgets;

use App\Models\MonthlyClosing;
use App\Services\SettlementCalculator;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;
use Illuminate\Validation\ValidationException;

/**
 * Full-width bar chart on the year planning page: incomes vs. costs per
 * month as grouped bars.
 *
 * Closed months show their frozen snapshot, open months the live calculation
 * — the same numbers as the planning table. Months with invalid data are
 * drawn as a gap. The year arrives as a Livewire prop from the page; on every
 * change the parent re-renders and the chart updates itself.
 */
class MonthlyCostsChart extends ChartWidget
{
    /** Year shown in the chart, passed down from the year planning page. */
    public string $year = '';

    protected static bool $isDiscovered = false;

    protected ?string $heading = 'Einkünfte und Kosten pro Monat';

    protected ?string $maxHeight = '18rem';

    protected int|string|array $columnSpan = 'full';

    /**
     * Chart.js type: vertical bars.
     */
    protected function getType(): string
    {
        return 'bar';
    }

    /**
     * Incomes and costs per month in euros; labels are the short German
     * month names.
     *
     * @return array{datasets: list<array{label: string, data: list<float|null>, backgroundColor: string}>, labels: list<string>}
     */
    public function getData(): array
    {
        $year = ctype_digit($this->year) ? (int) $this->year : (int) now()->format('Y');
        $closings = MonthlyClosing::where('month', 'like', $year.'-%')->whereNotNull('closed_at')->get()->keyBy('month');

        $incomes = [];
        $costs = [];
        for ($m = 1; $m <= 12; $m++) {
            $month = sprintf('%04d-%02d', $year, $m);
            try {
                $report = $closings[$month]->snapshot ?? app(SettlementCalculator::class)->calculate($month);
                $incomes[] = round($report['total_income_cents'] / 100, 2);
                $costs[] = round($report['total_expense_cents'] / 100, 2);
            } catch (ValidationException) {
                $incomes[] = null;
                $costs[] = null;
            }
        }

        return [
            'datasets' => [
                ['label' => 'Einkünfte in €', 'data' => $incomes, 'backgroundColor' => '#14b8a6'],
                ['label' => 'Kosten in €', 'data' => $costs, 'backgroundColor' => '#f43f5e'],
            ],
            'labels' => ['Jan', 'Feb', 'Mär', 'Apr', 'Mai', 'Jun', 'Jul', 'Aug', 'Sep', 'Okt', 'Nov', 'Dez'],
        ];
    }

    /**
     * Chart.js options: German euro formatting for the value axis and tooltips.
     */
    protected function getOptions(): RawJs
    {
        return RawJs::make(<<<'JS'
            {
                scales: {
                    y: {
                        ticks: {
                            callback: (value) => new Intl.NumberFormat('de-DE', { style: 'currency', currency: 'EUR' }).format(value),
                        },
                    },
                },
                plugins: {
                    tooltip: {
                        callbacks: {
                            label: (context) => ' ' + new Intl.NumberFormat('de-DE', { style: 'currency', currency: 'EUR' }).format(context.parsed.y),
                        },
                    },
                },
            }
            JS);
    }
}
