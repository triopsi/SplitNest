<?php

namespace App\Filament\Pages;

use App\Models\Expense;
use App\Models\Income;
use App\Models\MonthlyClosing;
use App\Models\Participant;
use App\Services\BudgetFunding;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Url;

/** Show accumulated earmarked funds and their account holders for a selected month. */
class SavingsOverview extends Page
{
    protected string $view = 'filament.pages.savings-overview';

    protected static ?int $navigationSort = 3;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedWallet;

    /** Month whose projected or frozen closing reserves are displayed. */
    #[Url]
    public string $month = '';

    /** Default to this month while retaining direct links to historical months. */
    public function mount(): void
    {
        $this->month = $this->month ?: now()->format('Y-m');
    }

    /** Name the page and browser title. */
    public function getTitle(): string
    {
        return __('funding.savings.title');
    }

    /** Name the separate overview entry in the sidebar. */
    public static function getNavigationLabel(): string
    {
        return __('funding.savings.title');
    }

    /** Keep the balance overview beside monthly and annual planning. */
    public static function getNavigationGroup(): string
    {
        return __('nav.groups.overview');
    }

    /** Resolve reserves without requiring unrelated household bills to be allocatable.
     * @return array{budgets: list<array<string, mixed>>, names: array<int, string>, total: int, closed: bool, problem: string|null}
     */
    protected function getViewData(): array
    {
        $data = ['budgets' => [], 'names' => [], 'total' => 0, 'closed' => false, 'problem' => null];
        if (preg_match('/^\d{4}-(0[1-9]|1[0-2])$/D', $this->month) !== 1) {
            return [...$data, 'problem' => __('funding.savings.invalid_month')];
        }
        $closing = MonthlyClosing::where('month', $this->month)->whereNotNull('closed_at')->first();
        if ($closing) {
            if (! array_key_exists('budgets', $closing->snapshot)) {
                return [...$data, 'closed' => true, 'problem' => __('funding.savings.no_snapshot')];
            }
            $budgets = $closing->snapshot['budgets'];
            $names = array_column($closing->snapshot['participants'], 'name', 'id');
        } else {
            try {
                $budgets = app(BudgetFunding::class)->calculate($this->month, Income::orderBy('id')->get(), Expense::orderBy('id')->get())['budgets'];
            } catch (ValidationException $exception) {
                return [...$data, 'problem' => collect($exception->errors())->flatten()->implode(' ')];
            }
            $names = Participant::pluck('name', 'id')->all();
        }

        return ['budgets' => $budgets, 'names' => $names, 'total' => array_sum(array_column($budgets, 'closing_cents')), 'closed' => $closing !== null, 'problem' => null];
    }
}
