<?php

namespace App\Filament\Widgets\Concerns;

use App\Models\MonthlyClosing;
use App\Services\SettlementCalculator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Reactive;

/**
 * Shares reactive dashboard data and resolves live or frozen reports for standalone widgets.
 */
trait InteractsWithSettlementReport
{
    #[Reactive]
    public string $month = '';

    /** @var array<string, mixed>|null */
    #[Reactive]
    public ?array $report = null;

    /** @var array<int, string|null> */
    #[Reactive]
    public array $emails = [];

    /**
     * Dashboard widgets share the page's report; standalone widgets resolve
     * the same frozen snapshot or live calculation for their selected month.
     *
     * @return array<string, mixed>
     */
    #[Computed]
    public function settlementReport(): array
    {
        if ($this->report !== null) {
            return $this->report;
        }

        $closing = MonthlyClosing::where('month', $this->month)->whereNotNull('closed_at')->first();

        return $closing?->snapshot ?? app(SettlementCalculator::class)->calculate($this->month);
    }
}
