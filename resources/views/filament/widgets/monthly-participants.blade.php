<x-filament-widgets::widget class="hh-personal-widget">
    @php
        $report = $this->settlementReport;
        $forecasts = $this->accountForecast;
        $signed = fn ($cents) => ($cents > 0 ? '+' : '').\App\Support\Money::format($cents);
    @endphp
    <x-filament::section :heading="__('settlement.overview.title')">
        <div class="hh-personal-overview">
            <div class="hh-personal-cards">
                @foreach($report['participants'] as $person)
                    @php $forecast = $forecasts[$person['id']] ?? null; @endphp
                    <article class="hh-personal-card">
                        <header class="hh-personal-head">
                            <x-person :name="$person['name']" :email="$emails[$person['id']] ?? null" :href="\App\Filament\Pages\ParticipantMonth::getUrl(['participant' => $person['id'], 'month' => $month])" />
                            <span class="hh-personal-period">{{ \App\Support\Month::format($month) }}</span>
                        </header>
                        <div class="hh-personal-month">
                            <h3>{{ __('participants.account.monthly') }}</h3>
                            <x-monthly-calculation :person="$person" />
                        </div>
                        <div class="hh-personal-account">
                            @if($forecast)
                                <h3>{{ __('participants.account.title') }}</h3>
                                <div class="hh-personal-start">
                                    <span>{{ __('participants.account.observed') }}<small>{{ \App\Support\Month::format($forecast['observed_month']) }}</small></span>
                                    <strong class="{{ $forecast['observed_balance_cents'] < 0 ? 'hh-negative' : '' }}">{{ $signed($forecast['observed_balance_cents']) }}</strong>
                                </div>
                                @if($forecast['balance_cents'] !== null)
                                    <div class="hh-personal-result">
                                        <span>{{ $forecast['available_cents'] !== null ? __('participants.account.available') : __('participants.account.projected') }}</span>
                                        <strong class="{{ ($forecast['available_cents'] ?? $forecast['balance_cents']) < 0 ? 'hh-negative' : '' }}">{{ $signed($forecast['available_cents'] ?? $forecast['balance_cents']) }}</strong>
                                    </div>
                                @endif
                                @if($forecast['error'])<p role="alert" class="hh-warning">{{ $forecast['error'] }}</p>@endif
                                <details class="hh-personal-disclosure">
                                    <summary>{{ __('participants.account.calculation_details') }}</summary>
                                    <x-account-calculation :forecast="$forecast" />
                                </details>
                            @else
                                <div class="hh-personal-empty">
                                    <x-filament::icon icon="heroicon-o-wallet" class="hh-icon" />
                                    <div><h3>{{ __('participants.account.no_balance') }}</h3><p>{{ __('participants.account.no_balance_help') }}</p>
                                    <a class="hh-link" href="{{ \App\Filament\Resources\Participants\ParticipantResource::getUrl() }}">{{ __('participants.account.edit_balance') }} →</a></div>
                                </div>
                            @endif
                        </div>
                    </article>
                @endforeach
            </div>
            <div class="hh-personal-footer">
                <details class="hh-personal-disclosure hh-personal-help">
                    <summary>{{ __('participants.account.explanation') }}</summary>
                    <p class="hh-muted">{{ isset($report['allocation_income_cents']) ? __('funding.overview_help') : __('settlement.overview.footnote') }}</p>
                    <p class="hh-muted">{{ __('participants.account.forecast_help') }}</p>
                </details>
                <details class="hh-personal-disclosure hh-personal-table">
                    <summary>{{ __('participants.account.more_figures') }}</summary>
                    {{ $this->table }}
                </details>
            </div>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
