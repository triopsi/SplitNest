<?php

namespace App\Filament\Widgets;

use App\Models\MonthlyClosing;
use App\Services\SettlementCalculator;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;
use Illuminate\Validation\ValidationException;

/**
 * Doughnut chart on the monthly overview: who carries how much of the
 * month's costs. Closed months show their frozen snapshot, open months the
 * live calculation — the same shares as the "Eigener Kostenanteil" column.
 */
class MonthlyCostSharesChart extends ChartWidget
{
    /** Month shown in the chart (Y-m), passed down from the overview page. */
    public string $month = '';

    protected static bool $isDiscovered = false;

    protected ?string $heading = 'Kostenanteile';

    protected ?string $description = 'Wer trägt wie viel der Kosten in diesem Monat?';

    protected ?string $maxHeight = '18rem';

    protected int|string|array $columnSpan = 'full';

    /**
     * Chart.js type: doughnut.
     */
    protected function getType(): string
    {
        return 'doughnut';
    }

    /**
     * One slice per participant: their cost share in euros.
     *
     * @return array{datasets: list<array{label: string, data: list<float>, backgroundColor: list<string>}>, labels: list<string>}
     */
    public function getData(): array
    {
        try {
            $closing = MonthlyClosing::where('month', $this->month)->whereNotNull('closed_at')->first();
            $report = $closing?->snapshot ?? app(SettlementCalculator::class)->calculate($this->month);
        } catch (ValidationException) {
            return ['datasets' => [], 'labels' => []];
        }

        return [
            'datasets' => [[
                'label' => 'Kostenanteil in €',
                'data' => array_map(fn (array $participant): float => round($participant['share_cents'] / 100, 2), $report['participants']),
                'backgroundColor' => ['#14b8a6', '#f59e0b', '#8b5cf6', '#f43f5e', '#0ea5e9', '#84cc16'],
            ]],
            'labels' => array_column($report['participants'], 'name'),
        ];
    }

    /**
     * Chart.js options: legend on the right, euro-formatted tooltips.
     */
    protected function getOptions(): RawJs
    {
        return RawJs::make(<<<'JS'
            {
                plugins: {
                    legend: { position: 'right' },
                    tooltip: {
                        callbacks: {
                            label: (context) => ' ' + context.label + ': ' + new Intl.NumberFormat('de-DE', { style: 'currency', currency: 'EUR' }).format(context.parsed),
                        },
                    },
                },
            }
            JS);
    }
}
