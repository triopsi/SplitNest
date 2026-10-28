<x-filament-widgets::widget>
    @php $report = $this->settlementReport; $payments = $report['payment_instructions'] ?? $report['transfers']; @endphp
    <x-filament::section :heading="__('settlement.transfers.title')" :description="__('funding.payment_count', ['count' => count($payments)])">
        <p class="hh-muted">{{ __('funding.payment_plan_note') }}</p>
        @forelse($payments as $transfer)
            <div class="hh-transfer">
                <div class="hh-person-line">
                    <x-person :name="$transfer['from_name']" :email="$emails[$transfer['from_id']] ?? null" />
                    <span>{{ __('settlement.transfers.to') }}</span>
                    <x-person :name="$transfer['to_name']" :email="$emails[$transfer['to_id']] ?? null" />
                </div>
                <div class="hh-muted">
                    @if(($transfer['settlement_cents'] ?? $transfer['amount_cents']) > 0)
                        <div>{{ __('funding.settlement_part') }}: {{ \App\Support\Money::format($transfer['settlement_cents'] ?? $transfer['amount_cents']) }}</div>
                    @endif
                    @foreach($transfer['reasons'] ?? [] as $reason)
                        <div>{{ $reason['name'] }}: {{ \App\Support\Money::format($reason['amount_cents']) }}</div>
                    @endforeach
                    <div>{{ __('settlement.transfers.reference', ['month' => \App\Support\Month::format($month)]) }}</div>
                </div>
                <strong>{{ \App\Support\Money::format($transfer['amount_cents']) }}</strong>
            </div>
        @empty
            <p class="hh-muted">{{ __('settlement.transfers.balanced') }}</p>
        @endforelse
    </x-filament::section>
</x-filament-widgets::widget>
