<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\InteractsWithSettlementReport;
use Filament\Widgets\Widget;

/**
 * Displays the balancing transfers needed for the selected month.
 */
class MonthlyTransfers extends Widget
{
    use InteractsWithSettlementReport;

    protected static bool $isDiscovered = false;

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament.widgets.monthly-transfers';
}
