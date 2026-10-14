<?php

namespace App\Filament\Pages;

use App\Models\MonthlyClosing;
use App\Services\CloseMonth;
use App\Services\SettlementCalculator;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Url;

/**
 * Monthly overview page: shows the live settlement for the selected month,
 * its frozen snapshot once closed, and the action to close a month.
 */
class Settlement extends Page
{
    protected string $view = 'filament.pages.settlement';

    protected static ?string $slug = '/';

    protected static ?string $title = 'Monatsübersicht';

    protected static ?int $navigationSort = 1;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedHome;

    /** Currently selected month (Y-m), kept in the URL query string. */
    #[Url]
    public string $month = '';

    /**
     * Default the view to the current month on first load.
     */
    public function mount(): void
    {
        $this->month = $this->month ?: now()->format('Y-m');
    }

    /**
     * "Close month" action: freezes the settlement after a confirmation dialog.
     *
     * Validation problems are shown as a notification instead of an exception.
     */
    public function closeMonthAction(): Action
    {
        return Action::make('closeMonth')->label('Monat abschließen')->icon('heroicon-o-lock-closed')
            ->requiresConfirmation()->modalHeading('Abrechnung verbindlich speichern?')
            ->modalDescription('Die geplanten Rechnungen und ihre Aufteilung werden eingefroren. Der Abschluss kann auch am Monatsanfang erstellt werden. Die PDF bleibt bei späteren Änderungen unverändert.')
            ->action(function (): void {
                abort_unless(auth()->user()?->is_admin, 403);
                try {
                    app(CloseMonth::class)->close($this->month, auth()->id());
                    Notification::make()->success()->title('Monat abgeschlossen. Die PDF steht bereit.')->send();
                } catch (ValidationException $e) {
                    Notification::make()->danger()->title('Abschluss nicht möglich')->body(collect($e->errors())->flatten()->implode(' '))->send();
                }
            });
    }

    /**
     * Provide the closing, the report (snapshot or live calculation) and an
     * optional problem message to the view.
     *
     * @return array{closing: MonthlyClosing|null, report: array<string, mixed>|null, problem: string|null}
     */
    protected function getViewData(): array
    {
        $closing = MonthlyClosing::where('month', $this->month)->whereNotNull('closed_at')->first();
        try {
            $report = $closing?->snapshot ?? app(SettlementCalculator::class)->calculate($this->month);
            $problem = null;
        } catch (ValidationException $e) {
            $report = null;
            $problem = collect($e->errors())->flatten()->implode(' ');
        }

        return compact('closing', 'report', 'problem');
    }
}
