<x-filament-panels::page>
    @php $money = fn ($cents) => \App\Support\Money::format($cents); $monthName = \Illuminate\Support\Carbon::createFromFormat('Y-m', $month)->translatedFormat('F Y'); @endphp
    <div class="hh-stack">
        <div class="hh-hero">
            <div><div class="hh-kicker">{{ __('participants.detail.kicker') }}</div><h2><x-person :name="$participant->name.' · '.$monthName" :email="$participant->email" /></h2><p>{{ __('participants.detail.intro', ['name' => $participant->name]) }}</p></div>
            <a class="hh-link" href="{{ \App\Filament\Pages\Settlement::getUrl(['month' => $month]) }}">{{ __('participants.detail.back') }}</a>
        </div>
        @if($problem)
            <div role="alert" class="hh-warning">{{ $problem }}</div>
        @elseif($person)
            <div class="hh-cards">
                <div class="hh-card">
                    <div class="hh-card-head"><x-filament::icon icon="heroicon-o-banknotes" class="hh-icon" /><label>{{ __('participants.detail.income') }}</label></div>
                    <div class="hh-number">{{ $money($person['income_cents']) }}</div>
                    <div class="hh-muted">{{ __('participants.detail.income_hint') }}</div>
                </div>
                <div class="hh-card">
                    <div class="hh-card-head"><x-filament::icon icon="heroicon-o-receipt-percent" class="hh-icon" /><label>{{ __('participants.detail.share') }}</label></div>
                    <div class="hh-number">{{ $money($person['share_cents']) }}</div>
                    <div class="hh-muted">{{ __('participants.detail.share_hint') }}</div>
                </div>
                <div class="hh-card">
                    <div class="hh-card-head"><x-filament::icon icon="heroicon-o-credit-card" class="hh-icon" /><label>{{ __('participants.detail.paid') }}</label></div>
                    <div class="hh-number">{{ $money($person['paid_cents']) }}</div>
                    <div class="hh-muted">{{ __('participants.detail.paid_hint', ['name' => $participant->name]) }}</div>
                </div>
                <div class="hh-card">
                    <div class="hh-card-head"><x-filament::icon icon="heroicon-o-arrows-right-left" class="hh-icon" /><label>{{ __('participants.detail.balance') }}</label></div>
                    <div class="hh-number {{ $person['balance_cents'] < 0 ? 'hh-negative' : '' }}">{{ $money($person['balance_cents']) }}</div>
                    <div class="hh-muted">{{ __('participants.detail.balance_hint') }}</div>
                </div>
                <div class="hh-card">
                    <div class="hh-card-head"><x-filament::icon icon="heroicon-o-wallet" class="hh-icon" /><label>{{ __('participants.detail.left') }}</label></div>
                    <div class="hh-number {{ $person['remaining_cents'] < 0 ? 'hh-negative' : '' }}">{{ $money($person['remaining_cents']) }}</div>
                    <div class="hh-muted">{{ __('participants.detail.left_hint') }}</div>
                </div>
            </div>
            <section class="hh-section">
                <div class="hh-section-head"><h3>{{ __('participants.detail.costs_title', ['month' => $monthName]) }}</h3><span class="hh-muted">{{ __('participants.detail.costs_count', ['count' => count($expenses), 'name' => $participant->name]) }}</span></div>
                <div class="hh-scroll"><table class="hh-table">
                    <thead><tr><th>{{ __('participants.detail.item') }}</th><th>{{ __('participants.detail.payer') }}</th><th>{{ __('participants.detail.rule') }}</th><th class="num">{{ __('participants.detail.total') }}</th><th class="num">{{ __('participants.detail.share_of', ['name' => $participant->name]) }}</th></tr></thead>
                    <tbody>@forelse($expenses as $expense)@php $shareCents = $expense['allocations'][$participant->id] ?? 0; @endphp<tr><td>{{ $expense['name'] }}</td><td><x-person :name="$names[$expense['payer_id']]" :email="$emails[$expense['payer_id']] ?? null" /></td><td>{{ \App\Models\Expense::modeLabels()[$expense['split_mode']] }}</td><td class="num">{{ $money($expense['amount_cents']) }}</td><td class="num"><b>{{ $money($shareCents) }}</b><br><span class="hh-muted">{{ $expense['amount_cents'] > 0 ? number_format(round($shareCents * 100 / $expense['amount_cents']), 0, ',', '').' %' : '–' }}</span></td></tr>@empty<tr><td colspan="5">{{ __('participants.detail.costs_empty') }}</td></tr>@endforelse</tbody>
                </table></div>
            </section>
            <section class="hh-section">
                <div class="hh-section-head"><h3>{{ __('participants.detail.incomes_title', ['month' => $monthName]) }}</h3><span class="hh-muted">{{ __('participants.detail.incomes_count', ['count' => count($incomes)]) }}</span></div>
                <div class="hh-scroll"><table class="hh-table">
                    <thead><tr><th>{{ __('participants.detail.income_name') }}</th><th class="num">{{ __('participants.detail.amount') }}</th></tr></thead>
                    <tbody>@forelse($incomes as $income)<tr><td>{{ $income['name'] }}</td><td class="num">{{ $money($income['amount_cents']) }}</td></tr>@empty<tr><td colspan="2">{{ __('participants.detail.incomes_empty') }}</td></tr>@endforelse</tbody>
                </table></div>
            </section>
        @endif
    </div>
</x-filament-panels::page>
