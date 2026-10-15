<x-filament-panels::page>
    @php $money = fn ($cents) => \App\Support\Money::format($cents); @endphp
    <div class="hh-stack">
        <div class="hh-hero">
            <div><div class="hh-kicker">{{ __('settlement.hero.kicker') }}</div><h2>{{ __('settlement.hero.title') }}</h2><p>{{ __('settlement.hero.text') }}</p></div>
            <div class="hh-picker">
                @php $selectedYear = (int) substr($month, 0, 4); $years = range(min(2020, $selectedYear), max((int) now()->format('Y') + 3, $selectedYear)); @endphp
                <label>{{ __('settlement.picker.month') }}<br><select aria-label="{{ __('settlement.picker.month') }}" class="hh-input" wire:model.live="monthPicker">
                    @foreach(__('settlement.months') as $i => $name)
                        <option value="{{ $i + 1 }}">{{ $name }}</option>
                    @endforeach
                </select></label>
                <label>{{ __('settlement.picker.year') }}<br><select aria-label="{{ __('settlement.picker.year') }}" class="hh-input" wire:model.live="yearPicker">
                    @foreach($years as $year)
                        <option value="{{ $year }}">{{ $year }}</option>
                    @endforeach
                </select></label>
            </div>
        </div>
        @if($problem)
            <div role="alert" class="hh-warning">{{ $problem }} <a class="hh-link" href="{{ \App\Filament\Resources\Participants\ParticipantResource::getUrl() }}">{{ __('settlement.manage_participants') }}</a></div>
        @else
            @php $names = array_column($report['participants'], 'name', 'id'); @endphp
            <div class="hh-control">
                @if($closing)
                    <span class="hh-badge">{{ __('settlement.closed_badge', ['date' => $closing->closed_at->format('d.m.Y H:i')]) }}</span>
                    <x-filament::button tag="a" href="{{ route('closings.pdf', $closing) }}" icon="heroicon-o-arrow-down-tray">{{ __('settlement.pdf_button') }}</x-filament::button>
                    {{ $this->reopenMonthAction }}
                @else
                    <span class="hh-badge">{{ __('settlement.open_badge', ['month' => $month]) }}</span>
                    {{ $this->closeMonthAction }}
                    <span class="hh-muted">{{ __('settlement.close_hint') }}</span>
                @endif
                <span class="hh-muted">{{ __('settlement.planned_note') }}</span>
            </div>
            <div class="hh-cards">
                <div class="hh-card">
                    <div class="hh-card-head"><x-filament::icon icon="heroicon-o-banknotes" class="hh-icon" /><label>{{ __('settlement.cards.income') }}</label></div>
                    <div class="hh-number">{{ $money($report['total_income_cents']) }}</div>
                    <div class="hh-muted">{{ __('settlement.cards.income_hint') }}</div>
                </div>
                <div class="hh-card">
                    <div class="hh-card-head"><x-filament::icon icon="heroicon-o-receipt-percent" class="hh-icon" /><label>{{ __('settlement.cards.costs') }}</label></div>
                    <div class="hh-number">{{ $money($report['total_expense_cents']) }}</div>
                    <div class="hh-muted">{{ __('settlement.cards.costs_hint') }}</div>
                </div>
                <div class="hh-card">
                    <div class="hh-card-head"><x-filament::icon icon="heroicon-o-wallet" class="hh-icon" /><label>{{ __('settlement.cards.left') }}</label></div>
                    <div class="hh-number {{ $report['total_income_cents'] < $report['total_expense_cents'] ? 'hh-negative' : '' }}">{{ $money($report['total_income_cents'] - $report['total_expense_cents']) }}</div>
                    <div class="hh-muted">{{ __('settlement.cards.left_hint') }}</div>
                </div>
            </div>
            @livewire(\App\Filament\Widgets\MonthlyCostSharesChart::class, ['month' => $month])
            <section class="hh-section">
                <div class="hh-section-head"><h3>{{ __('settlement.transfers.title') }}</h3><span class="hh-muted">{{ __('settlement.transfers.count', ['count' => count($report['transfers'])]) }}</span></div>
                @forelse($report['transfers'] as $transfer)
                    <div class="hh-transfer"><div class="hh-person-line"><x-person :name="$transfer['from_name']" :email="$emails[$transfer['from_id']] ?? null" /><span>{{ __('settlement.transfers.to') }}</span><x-person :name="$transfer['to_name']" :email="$emails[$transfer['to_id']] ?? null" /></div><div class="hh-muted">{{ __('settlement.transfers.reference', ['month' => $month]) }}</div><strong>{{ $money($transfer['amount_cents']) }}</strong></div>
                @empty
                    <p class="hh-note">{{ __('settlement.transfers.balanced') }}</p>
                @endforelse
            </section>
            <section class="hh-section">
                <div class="hh-section-head"><h3>{{ __('settlement.overview.title') }}</h3></div>
                <div class="hh-scroll"><table class="hh-table">
                    <thead><tr><th>{{ __('settlement.overview.participant') }}</th><th class="num">{{ __('settlement.overview.income') }}</th><th class="num">{{ __('settlement.overview.share') }}</th><th class="num">{{ __('settlement.overview.share_pct') }}</th><th class="num">{{ __('settlement.overview.paid') }}</th><th class="num">{{ __('settlement.overview.balance') }}</th><th class="num">{{ __('settlement.overview.left') }}</th></tr></thead>
                    <tbody>@foreach($report['participants'] as $person)<tr><td><x-person :name="$person['name']" :email="$emails[$person['id']] ?? null" :href="\App\Filament\Pages\ParticipantMonth::getUrl(['participant' => $person['id'], 'month' => $month])" /></td><td class="num">{{ $money($person['income_cents']) }}</td><td class="num">{{ $money($person['share_cents']) }}</td><td class="num">{{ $report['total_expense_cents'] > 0 ? number_format(round($person['share_cents'] * 100 / $report['total_expense_cents']), 0, ',', '').' %' : '–' }}</td><td class="num">{{ $money($person['paid_cents']) }}</td><td class="num">{{ $money($person['balance_cents']) }}</td><td class="num {{ $person['remaining_cents'] < 0 ? 'hh-negative' : '' }}"><b>{{ $money($person['remaining_cents']) }}</b></td></tr>@endforeach</tbody>
                </table></div>
                <p class="hh-note">{{ __('settlement.overview.footnote') }}</p>
            </section>
            <section class="hh-section">
                <div class="hh-section-head"><h3>{{ __('settlement.costs.title') }}</h3><a class="hh-link" href="{{ \App\Filament\Resources\Expenses\ExpenseResource::getUrl() }}">{{ __('settlement.costs.manage') }}</a></div>
                <div class="hh-scroll"><table class="hh-table">
                    <thead><tr><th>{{ __('settlement.costs.item') }}</th><th>{{ __('settlement.costs.payer') }}</th><th>{{ __('settlement.costs.rule') }}</th><th class="num">{{ __('settlement.costs.total') }}</th>@foreach($report['participants'] as $p)<th class="num"><x-person :name="$p['name']" :email="$emails[$p['id']] ?? null" /></th>@endforeach</tr></thead>
                    <tbody>@forelse($report['expenses'] as $expense)<tr><td>{{ $expense['name'] }}</td><td><x-person :name="$names[$expense['payer_id']]" :email="$emails[$expense['payer_id']] ?? null" /></td><td>{{ \App\Models\Expense::modeLabels()[$expense['split_mode']] }}</td><td class="num">{{ $money($expense['amount_cents']) }}</td>@foreach($report['participants'] as $p)@php $shareCents = $expense['allocations'][$p['id']] ?? 0; @endphp<td class="num">{{ $money($shareCents) }}<br><span class="hh-muted">{{ $expense['amount_cents'] > 0 ? number_format(round($shareCents * 100 / $expense['amount_cents']), 0, ',', '').' %' : '–' }}</span></td>@endforeach</tr>@empty<tr><td colspan="{{ 4 + count($report['participants']) }}">{{ __('settlement.costs.empty') }}</td></tr>@endforelse</tbody>
                </table></div>
            </section>
        @endif
    </div>
</x-filament-panels::page>