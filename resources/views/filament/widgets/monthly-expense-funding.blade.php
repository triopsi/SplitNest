<div class="hh-cost-funding">
    @if(($record['budget_cents'] ?? 0) > 0)
        <div><span>{{ __('funding.budget') }}</span><span>− {{ \App\Support\Money::format($record['budget_cents']) }}</span></div>
    @endif
    @if(($record['reimbursed_cents'] ?? 0) > 0)
        <div><span>{{ __('funding.reimbursements') }}</span><span>− {{ \App\Support\Money::format($record['reimbursed_cents']) }}</span></div>
    @endif
    <div class="hh-cost-remainder"><b>{{ __('funding.remainder_to_split') }}</b><b>{{ \App\Support\Money::format($record['uncovered_cents'] ?? $record['amount_cents']) }}</b></div>
</div>
