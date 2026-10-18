<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\MonthlyCostSharesChart;
use App\Filament\Widgets\MonthlyExpensesTable;
use App\Filament\Widgets\MonthlyExpenseStat;
use App\Filament\Widgets\MonthlyIncomeStat;
use App\Filament\Widgets\MonthlyParticipantsTable;
use App\Filament\Widgets\MonthlyRemainingStat;
use App\Filament\Widgets\MonthlyTransfers;
use App\Models\MonthlyClosing;
use App\Models\Participant;
use App\Services\CloseMonth;
use App\Services\ReopenMonth;
use App\Services\SettlementCalculator;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Dashboard;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\Widget;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;

/**
 * Monthly overview page: shows the live settlement for the selected month,
 * its frozen snapshot once closed, and the action to close a month.
 */
class Settlement extends Dashboard
{
    protected string $view = 'filament.pages.settlement';

    protected static ?string $slug = '/';

    protected static ?int $navigationSort = 1;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedHome;

    /** Currently selected month (Y-m), kept in the URL query string. */
    #[Url]
    public string $month = '';

    /** Month number (1-12) of the month dropdown. */
    public string $monthPicker = '';

    /** Year of the year dropdown. */
    public string $yearPicker = '';

    /**
     * Default the view to the current month on first load and sync the dropdowns.
     */
    public function mount(): void
    {
        $this->month = $this->month ?: now()->format('Y-m');
        $this->syncPickers();
    }

    /**
     * Page title (translated).
     */
    public function getTitle(): string
    {
        return __('settlement.title');
    }

    /**
     * Navigation label (translated).
     */
    public static function getNavigationLabel(): string
    {
        return __('settlement.title');
    }

    /**
     * Sidebar group (translated).
     */
    public static function getNavigationGroup(): string
    {
        return __('nav.groups.overview');
    }

    /**
     * Update the settlement month when the month dropdown changes.
     */
    public function updatedMonthPicker(): void
    {
        $this->updateMonthFromPickers();
    }

    /**
     * Update the settlement month when the year dropdown changes.
     */
    public function updatedYearPicker(): void
    {
        $this->updateMonthFromPickers();
    }

    /**
     * Push the canonical month (Y-m) into the two dropdown properties.
     */
    private function syncPickers(): void
    {
        [$year, $month] = explode('-', $this->month);
        $this->yearPicker = $year;
        $this->monthPicker = (string) (int) $month;
    }

    /**
     * Combine both dropdown values into the canonical month (Y-m).
     */
    private function updateMonthFromPickers(): void
    {
        if (ctype_digit($this->monthPicker) && ctype_digit($this->yearPicker)) {
            $this->month = sprintf('%04d-%02d', (int) $this->yearPicker, (int) $this->monthPicker);
        }
    }

    /**
     * "Close month" action: freezes the settlement after a confirmation dialog.
     *
     * Validation problems are shown as a notification instead of an exception.
     */
    public function closeMonthAction(): Action
    {
        return Action::make('closeMonth')->label(__('settlement.close_action.label'))->icon('heroicon-o-lock-closed')
            ->requiresConfirmation()->modalHeading(__('settlement.close_action.heading'))
            ->modalDescription(__('settlement.close_action.description'))
            ->action(function (): void {
                abort_unless(Auth::user()?->is_admin, 403);
                try {
                    app(CloseMonth::class)->close($this->month, (int) Auth::id());
                    unset($this->settlementData);
                    Notification::make()->success()->title(__('settlement.close_action.success'))->send();
                } catch (ValidationException $e) {
                    Notification::make()->danger()->title(__('settlement.close_action.error'))->body(collect($e->errors())->flatten()->implode(' '))->send();
                }
            });
    }

    /**
     * "Reopen month" action: discards the frozen snapshot after a confirmation
     * dialog, so forgotten entries can be added before closing the month anew.
     *
     * Validation problems are shown as a notification instead of an exception.
     */
    public function reopenMonthAction(): Action
    {
        return Action::make('reopenMonth')->label(__('settlement.reopen_action.label'))->icon('heroicon-o-lock-open')->color('gray')
            ->requiresConfirmation()->modalHeading(__('settlement.reopen_action.heading'))
            ->modalDescription(__('settlement.reopen_action.description'))
            ->action(function (): void {
                abort_unless(Auth::user()?->is_admin, 403);
                try {
                    app(ReopenMonth::class)->reopen($this->month);
                    unset($this->settlementData);
                    Notification::make()->success()->title(__('settlement.reopen_action.success'))->send();
                } catch (ValidationException $e) {
                    Notification::make()->danger()->title(__('settlement.reopen_action.error'))->body(collect($e->errors())->flatten()->implode(' '))->send();
                }
            });
    }

    /**
     * Provide the closing, the report (snapshot or live calculation) and an
     * optional problem message to the view.
     *
     * @return array{closing: MonthlyClosing|null, report: array<string, mixed>|null, problem: string|null, emails: Collection<int, string|null>}
     */
    #[Computed]
    public function settlementData(): array
    {
        $closing = MonthlyClosing::where('month', $this->month)->whereNotNull('closed_at')->first();
        try {
            $report = $closing?->snapshot ?? app(SettlementCalculator::class)->calculate($this->month);
            $problem = null;
        } catch (ValidationException $e) {
            $report = null;
            $problem = collect($e->errors())->flatten()->implode(' ');
        }

        return compact('closing', 'report', 'problem') + ['emails' => Participant::pluck('email', 'id')];
    }

    /**
     * Register the independently placeable monthly widgets in their default order.
     *
     * @return array<class-string<Widget>>
     */
    public function getWidgets(): array
    {
        return [
            MonthlyIncomeStat::class,
            MonthlyExpenseStat::class,
            MonthlyRemainingStat::class,
            MonthlyCostSharesChart::class,
            MonthlyTransfers::class,
            MonthlyParticipantsTable::class,
            MonthlyExpensesTable::class,
        ];
    }

    /**
     * Use one widget column on small screens and three from the medium breakpoint.
     *
     * @return array<string, int>
     */
    public function getColumns(): array
    {
        return ['default' => 1, 'md' => 3];
    }

    /**
     * Pass the same selected month, report and participant e-mails to every widget.
     *
     * @return array{month: string, report: array<string, mixed>|null, emails: array<int, string|null>}
     */
    public function getWidgetData(): array
    {
        return [
            'month' => $this->month,
            'report' => $this->settlementData['report'],
            'emails' => $this->settlementData['emails']->all(),
        ];
    }

    /**
     * Expose the shared report, closing state and validation feedback to the page view.
     *
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        return $this->settlementData;
    }
}
