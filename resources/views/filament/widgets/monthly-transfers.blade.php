<x-filament-widgets::widget>
    @php $report = $this->settlementReport; $payments = $report['payment_instructions'] ?? $report['transfers']; @endphp
    <x-filament::section :heading="__('settlement.transfers.title')" :description="__('funding.payment_count', ['count' => count($payments)])">
        <div class="hh-payments">
            <p class="hh-muted">{{ __('settlement.planned_note') }}</p>
            <div class="hh-payment-list">
                @forelse($payments as $transfer)
                    <article class="hh-payment-card">
                        <header class="hh-payment-head">
                            <div class="hh-payment-route">
                                <div class="hh-payment-person">
                                    <span class="hh-payment-label">{{ __('funding.payment_payer') }}</span>
                                    <x-person :name="$transfer['from_name']" :email="$emails[$transfer['from_id']] ?? null" />
                                </div>
                                <span class="hh-payment-arrow" aria-hidden="true">→</span>
                                <div class="hh-payment-person">
                                    <span class="hh-payment-label">{{ __('funding.payment_recipient') }}</span>
                                    <x-person :name="$transfer['to_name']" :email="$emails[$transfer['to_id']] ?? null" />
                                </div>
                            </div>
                        </header>
                        <div class="hh-payment-breakdown">
                            <p class="hh-payment-label">{{ __('funding.payment_breakdown') }}</p>
                            <dl>
                                @if(($transfer['settlement_cents'] ?? $transfer['amount_cents']) > 0)
                                    <div><dt><span class="hh-payment-sign" aria-hidden="true"></span>{{ __('funding.settlement_part') }}</dt><dd>{{ \App\Support\Money::format($transfer['settlement_cents'] ?? $transfer['amount_cents']) }}</dd></div>
                                @endif
                                @foreach($transfer['reasons'] ?? [] as $reason)
                                    <div><dt><span class="hh-payment-sign">+</span>{{ $reason['name'] }}</dt><dd>{{ \App\Support\Money::format($reason['amount_cents']) }}</dd></div>
                                @endforeach
                                <div class="hh-payment-equation-total"><dt><span class="hh-payment-sign">=</span>{{ __('funding.payment_amount') }}</dt><dd><strong>{{ \App\Support\Money::format($transfer['amount_cents']) }}</strong></dd></div>
                            </dl>
                        </div>
                        <footer class="hh-payment-reference">
                            <span class="hh-payment-label">{{ __('funding.payment_reference') }}</span>
                            <span>{{ __('settlement.transfers.reference', ['month' => \App\Support\Month::format($month)]) }}</span>
                        </footer>
                    </article>
                @empty
                    <div class="hh-payment-empty">
                        <x-filament::icon icon="heroicon-o-check-circle" class="hh-icon" />
                        <p>{{ __('settlement.transfers.balanced') }}</p>
                    </div>
                @endforelse
            </div>
            <details class="hh-payment-help">
                <summary>{{ __('funding.payment_help') }}</summary>
                <p class="hh-muted">{{ __('funding.payment_plan_note') }}</p>
            </details>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
