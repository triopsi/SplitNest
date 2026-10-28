<?php

namespace App\Filament\Pages;

use App\Models\MonthlyClosing;
use App\Models\Participant;
use App\Services\SettlementCalculator;
use App\Support\Month;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Illuminate\Validation\ValidationException;

/**
 * Participant detail page: shows one participant's incomes, cost shares and
 * balancing figures for a single month.
 */
class ParticipantMonth extends Page
{
    protected static ?string $slug = 'participants/{participant}/{month}';

    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'filament.pages.participant-month';

    /** The participant this page is about (route model binding). */
    public Participant $participant;

    /** The settlement month (Y-m). */
    public string $month = '';

    /**
     * Bind the participant and validate the month format.
     */
    public function mount(Participant $participant, string $month): void
    {
        abort_unless(preg_match('/^\d{4}-(0[1-9]|1[0-2])$/D', $month) === 1, 404);
        $this->participant = $participant;
        $this->month = $month;
    }

    /**
     * Page title: participant name and month.
     */
    public function getTitle(): string
    {
        return $this->participant->name.' · '.Month::format($this->month);
    }

    /** Preview the PDF this participant would receive for a closed month.
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        $closing = MonthlyClosing::where('month', $this->month)->whereNotNull('closed_at')->first();
        if (! $closing) {
            return [];
        }

        return [Action::make('recipientPdf')->label(__('participants.detail.recipient_pdf'))
            ->icon('heroicon-o-arrow-down-tray')
            ->url(route('closings.participant.pdf', ['closing' => $closing, 'participant' => $this->participant]))];
    }

    /**
     * Provide the participant's figures and their share of the month's costs.
     *
     * Closed months show the frozen snapshot, open months the live calculation.
     *
     * @return array{person: array<string, mixed>|null, expenses: list<array<string, mixed>>, incomes: list<array<string, mixed>>, names: array<int, string>, problem: string|null}
     */
    protected function getViewData(): array
    {
        $closing = MonthlyClosing::where('month', $this->month)->whereNotNull('closed_at')->first();
        try {
            $report = $closing?->snapshot ?? app(SettlementCalculator::class)->calculate($this->month);
        } catch (ValidationException $e) {
            return ['person' => null, 'expenses' => [], 'incomes' => [], 'names' => [], 'emails' => collect(), 'problem' => collect($e->errors())->flatten()->implode(' ')];
        }
        $person = collect($report['participants'])->firstWhere('id', $this->participant->id);
        $expenses = array_values(array_filter($report['expenses'], fn (array $expense): bool => ($expense['allocations'][$this->participant->id] ?? 0) > 0));
        $incomes = array_values(array_filter($report['incomes'], fn (array $income): bool => $income['participant_id'] === $this->participant->id));
        $names = array_column($report['participants'], 'name', 'id');

        return ['person' => $person, 'expenses' => $expenses, 'incomes' => $incomes, 'names' => $names, 'emails' => Participant::pluck('email', 'id'), 'problem' => null];
    }
}
