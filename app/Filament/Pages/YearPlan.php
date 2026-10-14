<?php

namespace App\Filament\Pages;

use App\Models\MonthlyClosing;
use App\Services\SettlementCalculator;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Validation\ValidationException;

class YearPlan extends Page
{
    protected string $view = 'filament.pages.year-plan';

    protected static ?string $title = 'Jahresplanung';

    protected static ?int $navigationSort = 2;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    public string $year = '2027';

    protected function getViewData(): array
    {
        $rows = [];
        if (! ctype_digit($this->year) || $this->year < 2000 || $this->year > 2100) {
            return ['rows' => [], 'problem' => 'Bitte ein Jahr von 2000 bis 2100 auswählen.'];
        }
        $closings = MonthlyClosing::where('month', 'like', $this->year.'-%')->whereNotNull('closed_at')->get()->keyBy('month');
        for ($m = 1; $m <= 12; $m++) {
            $month = sprintf('%04d-%02d', $this->year, $m);
            try {
                $rows[] = ['month' => $month, 'closed' => isset($closings[$month]), 'report' => $closings[$month]->snapshot ?? app(SettlementCalculator::class)->calculate($month), 'error' => null];
            } catch (ValidationException $e) {
                $rows[] = ['month' => $month, 'closed' => false, 'report' => null, 'error' => collect($e->errors())->flatten()->implode(' ')];
            }
        }

        return ['rows' => $rows, 'problem' => null];
    }
}
