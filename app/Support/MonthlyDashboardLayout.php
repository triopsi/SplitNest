<?php

namespace App\Support;

use App\Filament\Widgets\MonthlyCostSharesChart;
use App\Filament\Widgets\MonthlyExpensesTable;
use App\Filament\Widgets\MonthlyExpenseStat;
use App\Filament\Widgets\MonthlyIncomeStat;
use App\Filament\Widgets\MonthlyParticipantsTable;
use App\Filament\Widgets\MonthlyRemainingStat;
use App\Filament\Widgets\MonthlyTransfers;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Defines the monthly widget catalog and validates personal layout geometry.
 */
final class MonthlyDashboardLayout
{
    /**
     * Return the only widgets allowed on the monthly dashboard with size limits.
     *
     * @return array<string, array{widget: class-string, label: string, minW: int, minH: int}>
     */
    public static function widgets(): array
    {
        return [
            'income' => ['widget' => MonthlyIncomeStat::class, 'label' => 'settlement.cards.income', 'minW' => 3, 'minH' => 3],
            'expenses' => ['widget' => MonthlyExpenseStat::class, 'label' => 'settlement.cards.costs', 'minW' => 3, 'minH' => 3],
            'remaining' => ['widget' => MonthlyRemainingStat::class, 'label' => 'settlement.cards.left', 'minW' => 3, 'minH' => 3],
            'shares' => ['widget' => MonthlyCostSharesChart::class, 'label' => 'settlement.shares_chart.heading', 'minW' => 4, 'minH' => 5],
            'transfers' => ['widget' => MonthlyTransfers::class, 'label' => 'settlement.transfers.title', 'minW' => 4, 'minH' => 3],
            'participants' => ['widget' => MonthlyParticipantsTable::class, 'label' => 'settlement.overview.title', 'minW' => 6, 'minH' => 4],
            'costs' => ['widget' => MonthlyExpensesTable::class, 'label' => 'settlement.costs.title', 'minW' => 6, 'minH' => 4],
        ];
    }

    /**
     * Provide a complete twelve-column layout for users without saved preferences.
     *
     * @return list<array{id: string, x: int, y: int, w: int, h: int}>
     */
    public static function defaults(): array
    {
        return [
            ['id' => 'income', 'x' => 0, 'y' => 0, 'w' => 4, 'h' => 3],
            ['id' => 'expenses', 'x' => 4, 'y' => 0, 'w' => 4, 'h' => 3],
            ['id' => 'remaining', 'x' => 8, 'y' => 0, 'w' => 4, 'h' => 3],
            ['id' => 'shares', 'x' => 0, 'y' => 3, 'w' => 12, 'h' => 7],
            ['id' => 'transfers', 'x' => 0, 'y' => 10, 'w' => 12, 'h' => 3],
            ['id' => 'participants', 'x' => 0, 'y' => 13, 'w' => 12, 'h' => 5],
            ['id' => 'costs', 'x' => 0, 'y' => 18, 'w' => 12, 'h' => 8],
        ];
    }

    /**
     * Load valid preferences or fall back when old or damaged layouts no longer fit the catalog.
     *
     * @param  array<array-key, mixed>|null  $layout
     * @return list<array{id: string, x: int, y: int, w: int, h: int}>
     */
    public static function resolve(?array $layout): array
    {
        if ($layout === null) {
            return self::defaults();
        }

        try {
            return self::validate($layout);
        } catch (ValidationException) {
            return self::defaults();
        }
    }

    /**
     * Accept only complete, non-overlapping layouts with bounded widget positions and sizes.
     *
     * @param  array<array-key, mixed>  $layout
     * @return list<array{id: string, x: int, y: int, w: int, h: int}>
     *
     * @throws ValidationException When widgets, geometry or additional fields are invalid.
     */
    public static function validate(array $layout): array
    {
        $widgets = self::widgets();
        Validator::make(['layout' => $layout], [
            'layout' => ['required', 'array', 'list', 'size:'.count($widgets)],
            'layout.*' => ['required', 'array:id,x,y,w,h'],
            'layout.*.id' => ['required', 'string', 'distinct:strict', Rule::in(array_keys($widgets))],
            'layout.*.x' => ['required', 'integer', 'between:0,11'],
            'layout.*.y' => ['required', 'integer', 'between:0,119'],
            'layout.*.w' => ['required', 'integer', 'between:1,12'],
            'layout.*.h' => ['required', 'integer', 'between:1,20'],
        ])->validate();

        $normalized = [];
        foreach ($layout as $item) {
            $item = ['id' => $item['id'], 'x' => (int) $item['x'], 'y' => (int) $item['y'], 'w' => (int) $item['w'], 'h' => (int) $item['h']];
            $limits = $widgets[$item['id']];
            if ($item['w'] < $limits['minW'] || $item['h'] < $limits['minH'] || $item['x'] + $item['w'] > 12 || $item['y'] + $item['h'] > 120) {
                throw ValidationException::withMessages(['layout' => __('settlement.layout.invalid')]);
            }
            foreach ($normalized as $other) {
                if ($item['x'] < $other['x'] + $other['w'] && $other['x'] < $item['x'] + $item['w']
                    && $item['y'] < $other['y'] + $other['h'] && $other['y'] < $item['y'] + $item['h']) {
                    throw ValidationException::withMessages(['layout' => __('settlement.layout.invalid')]);
                }
            }
            $normalized[] = $item;
        }

        usort($normalized, fn (array $first, array $second): int => [$first['y'], $first['x']] <=> [$second['y'], $second['x']]);

        return $normalized;
    }
}
