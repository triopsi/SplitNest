<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\InteractsWithSettlementReport;
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
    use InteractsWithSettlementReport;

    protected static bool $isLazy = false;

    protected ?string $pollingInterval = null;

    protected static bool $isDiscovered = false;

    protected ?string $heading = null;

    protected ?string $description = null;

    protected ?string $maxHeight = null;

    protected int|string|array $columnSpan = 'full';

    /**
     * Chart heading (translated).
     */
    public function getHeading(): string
    {
        return __('settlement.shares_chart.heading');
    }

    /**
     * Chart description (translated).
     */
    public function getDescription(): string
    {
        return __('settlement.shares_chart.description');
    }

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
            $report = $this->settlementReport;
        } catch (ValidationException) {
            return ['datasets' => [], 'labels' => []];
        }

        return [
            'datasets' => [[
                'label' => __('settlement.shares_chart.dataset'),
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
                maintainAspectRatio: false,
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
