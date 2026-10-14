<?php

namespace App\Filament\Pages;

use App\Models\MonthlyClosing;
use App\Services\SettlementCalculator;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Validation\ValidationException;

/**
 * Year planning page: lists all twelve months of the chosen year with their
 * settlement (live or frozen snapshot) and closing state.
 */
class YearPlan extends Page
{
    protected string $view = 'filament.pages.year-plan';

    protected static ?string $title = 'Jahresplanung';

    protected static ?int $navigationSort = 2;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    /** Selected year for the planning view. */
    public string $year = '';

    /**
     * Default the view to the current year on first load.
     */
    public function mount(): void
    {
        $this->year = $this->year ?: now()->format('Y');
    }

    /**
     * Build one row per month for the selected year.
     *
     * @return array{rows: list<array{month: string, closed: bool, report: array<string, mixed>|null, error: string|null}>, problem: string|null}
     */
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
