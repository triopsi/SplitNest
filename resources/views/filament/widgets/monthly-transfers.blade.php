<x-filament-widgets::widget>
    @php $report = $this->settlementReport; @endphp
    <x-filament::section :heading="__('settlement.transfers.title')" :description="__('settlement.transfers.count', ['count' => count($report['transfers'])])">
        @forelse($report['transfers'] as $transfer)
            <div class="hh-transfer">
                <div class="hh-person-line">
                    <x-person :name="$transfer['from_name']" :email="$emails[$transfer['from_id']] ?? null" />
                    <span>{{ __('settlement.transfers.to') }}</span>
                    <x-person :name="$transfer['to_name']" :email="$emails[$transfer['to_id']] ?? null" />
                </div>
                <div class="hh-muted">{{ __('settlement.transfers.reference', ['month' => $month]) }}</div>
                <strong>{{ \App\Support\Money::format($transfer['amount_cents']) }}</strong>
            </div>
        @empty
            <p class="hh-muted">{{ __('settlement.transfers.balanced') }}</p>
        @endforelse
    </x-filament::section>
</x-filament-widgets::widget>
