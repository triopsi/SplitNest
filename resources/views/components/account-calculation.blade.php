@props(['forecast'])
@php
    $money = fn ($cents) => \App\Support\Money::format($cents);
    $signed = fn ($cents) => ($cents > 0 ? '+' : '').$money($cents);
@endphp
<div class="hh-account-calculation">
    <div class="hh-account-origin">
        <span>{{ __('participants.account.observed') }} · {{ \App\Support\Month::format($forecast['observed_month']) }}</span>
        <strong class="{{ $forecast['observed_balance_cents'] < 0 ? 'hh-negative' : '' }}">{{ $signed($forecast['observed_balance_cents']) }}</strong>
    </div>
    @if($forecast['balance_cents'] !== null)
        <dl class="hh-calculation">
            @if(! $forecast['is_starting_month'])
                <div><dt>{{ __('participants.account.opening') }}</dt><dd>{{ $signed($forecast['opening_balance_cents']) }}</dd></div>
                <div><dt>{{ __('participants.account.income') }}</dt><dd>{{ $signed($forecast['income_cents']) }}</dd></div>
                <div><dt>{{ __('participants.account.share') }}</dt><dd>{{ $signed(-$forecast['share_cents']) }}</dd></div>
                @if($forecast['savings_deposited_cents'] !== 0)
                    <div><dt>{{ __('participants.account.savings') }}</dt><dd>{{ $signed($forecast['savings_deposited_cents']) }}</dd></div>
                @endif
            @endif
            <div class="hh-calculation-total"><dt>{{ __('participants.account.projected') }}</dt><dd class="{{ $forecast['balance_cents'] < 0 ? 'hh-negative' : '' }}">{{ $signed($forecast['balance_cents']) }}</dd></div>
            @if($forecast['available_cents'] !== null)
                <div><dt>{{ __('participants.account.reserves') }}</dt><dd>{{ $signed(-$forecast['reserved_cents']) }}</dd></div>
                <div class="hh-calculation-total hh-calculation-final"><dt>{{ __('participants.account.available') }}</dt><dd class="{{ $forecast['available_cents'] < 0 ? 'hh-negative' : '' }}">{{ $signed($forecast['available_cents']) }}</dd></div>
            @endif
        </dl>
        @if($forecast['is_starting_month'])<p class="hh-muted">{{ __('participants.account.starting') }}</p>@endif
        @if($forecast['savings_deposited_cents'] !== 0)<p class="hh-muted">{{ __('participants.account.savings_help') }}</p>@endif
        @if($forecast['recovered_month'])<p class="hh-muted">{{ __('participants.account.recovered') }}: <strong>{{ \App\Support\Month::format($forecast['recovered_month']) }}</strong></p>@endif
    @endif
    @if($forecast['error'])<p role="alert" class="hh-warning">{{ $forecast['error'] }}</p>@endif
</div>
