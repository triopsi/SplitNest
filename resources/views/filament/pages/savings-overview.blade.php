<x-filament-panels::page>
    @php $money = fn ($cents) => \App\Support\Money::format($cents); @endphp
    <div class="hh-stack">
        <div class="hh-hero">
            <div><div class="hh-kicker">{{ __('funding.savings.title') }}</div><h2>{{ __('funding.savings.hero') }}</h2><p>{{ __('funding.savings.intro') }}</p></div>
            <label>{{ __('funding.savings.month') }}<br><input class="hh-input" type="month" wire:model.live="month" aria-label="{{ __('funding.savings.month') }}"></label>
        </div>
        <x-savings-explanation />
        @if($problem)
            <p role="alert" class="hh-warning">{{ $problem }}</p>
        @else
            <div class="hh-card">
                <div class="hh-card-head"><x-filament::icon icon="heroicon-o-wallet" class="hh-icon" /><label>{{ __('funding.savings.total') }} · {{ \App\Support\Month::format($month) }}</label></div>
                <div class="hh-number">{{ $money($total) }}</div>
                <p class="hh-muted">{{ $closed ? __('funding.savings.closed') : __('funding.savings.planned') }}</p>
            </div>
            <div class="hh-savings-grid">
                @forelse($budgets as $budget)
                    <section class="hh-section">
                        <div class="hh-section-head"><h3>{{ $budget['name'] }}</h3><x-filament::icon icon="heroicon-o-wallet" class="hh-icon" /></div>
                        <div class="hh-account-calculation">
                            <div><span class="hh-muted">{{ __('funding.closing') }}</span><div class="hh-number">{{ $money($budget['closing_cents']) }}</div></div>
                            <dl class="hh-calculation">
                                <div><dt>{{ __('funding.opening') }}</dt><dd>{{ $money($budget['opening_cents']) }}</dd></div>
                                <div><dt>{{ __('funding.receipts') }}</dt><dd>+{{ $money($budget['income_cents']) }}</dd></div>
                                <div><dt>{{ __('funding.spent') }}</dt><dd>{{ $money(-$budget['spent_cents']) }}</dd></div>
                            </dl>
                            <div class="hh-savings-holders">
                                <h4>{{ __('funding.savings.holders') }}</h4>
                                <dl class="hh-calculation">
                                    @foreach($budget['balances'] as $participantId => $cents)
                                        @if($cents !== 0)
                                            <div><dt>{{ $names[$participantId] ?? '–' }}</dt><dd>{{ $money($cents) }}</dd></div>
                                        @endif
                                    @endforeach
                                </dl>
                                @if($budget['closing_cents'] === 0)<p class="hh-muted">{{ __('funding.savings.empty_pot') }}</p>@endif
                            </div>
                        </div>
                    </section>
                @empty
                    <div class="hh-card"><p>{{ __('funding.no_budgets') }}</p><p class="hh-muted">{{ __('funding.budget_help') }}</p></div>
                @endforelse
            </div>
        @endif
        <a class="hh-link" href="{{ \App\Filament\Resources\Budgets\BudgetResource::getUrl() }}">{{ __('funding.savings.manage') }} →</a>
    </div>
</x-filament-panels::page>
