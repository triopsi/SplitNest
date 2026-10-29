<div class="hh-cost-share">
    <span class="hh-payment-label">{{ __('funding.own_remainder') }}</span>
    <strong>{{ \App\Support\Money::format($ownCents) }}</strong>
    @if($remainderCents > 0)
        <span class="hh-muted">{{ __('funding.remainder_percentage', ['percent' => number_format($ownCents * 100 / $remainderCents, 2, ',', '.'), 'amount' => \App\Support\Money::format($remainderCents)]) }}</span>
    @else
        <span class="hh-muted">{{ __('funding.fully_funded') }}</span>
    @endif
    @if($fundingCents > 0)
        <div class="hh-cost-funded"><span>{{ __('funding.from_funding') }}</span><span>+ {{ \App\Support\Money::format($fundingCents) }}</span></div>
        <div class="hh-cost-assigned"><span>{{ __('funding.total_assigned') }}</span><b>{{ \App\Support\Money::format($totalCents) }}</b></div>
    @endif
</div>
