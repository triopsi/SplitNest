<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="UTF-8">
    <title>{{ __('pdf.personal.title', ['month' => \App\Support\Month::format($statement['month'])]) }}</title>
    <style>
        @page { margin: 35px 35px 40px; }
        body { font-family: "DejaVu Sans", sans-serif; font-size: 11px; color: #213d41; }
        h1 { font-size: 24px; margin: 8px 0; }
        h2 { font-size: 15px; margin-top: 26px; }
        p { line-height: 1.5; }
        .hero { background: #143f46; color: white; padding: 22px; }
        .hero p { color: #cce4de; margin: 6px 0; }
        .totals { background: #edf5f3; padding: 14px; margin-top: 18px; }
        .amount { font-size: 22px; font-weight: bold; margin: 4px 0; }
        .muted { color: #657c7e; font-size: 10px; }
        table { border-collapse: collapse; width: 100%; margin-top: 10px; }
        th, td { border: 1px solid #d5e1df; padding: 9px; text-align: left; vertical-align: top; }
        th { background: #edf3f2; }
        .num { text-align: right; white-space: nowrap; }
        tr, .payment { page-break-inside: avoid; }
        .payment { padding: 12px; background: #e6f3ed; margin-bottom: 8px; }
        .payment p { margin: 4px 0; }
        footer { margin-top: 28px; border-top: 1px solid #d5e1df; padding-top: 10px; }
    </style>
</head>
<body>
    @php $money = fn (int $cents): string => \App\Support\Money::format($cents); @endphp
    <div class="hero">
        <div>SplitNest</div>
        <h1>{{ __('pdf.personal.title', ['month' => \App\Support\Month::format($statement['month'])]) }}</h1>
        <p>{{ $statement['name'] }}</p>
        <p>{{ __('pdf.saved_on', ['date' => $statement['saved_on']]) }}</p>
    </div>
    <p class="muted">{{ __('pdf.personal.scope') }}</p>
    <div class="totals">
        <div>{{ __('pdf.personal.to_pay') }}</div>
        <div class="amount">{{ $money($statement['outgoing_cents']) }}</div>
        @if($statement['incoming_cents'] > 0)
            <p>{{ __('pdf.personal.to_receive') }}: <b>{{ $money($statement['incoming_cents']) }}</b></p>
        @endif
    </div>

    <h2>{{ __('pdf.personal.payments') }}</h2>
    @forelse($statement['payments'] as $payment)
        <div class="payment">
            <p><b>{{ $payment['from_name'] }}</b> {{ __('pdf.to') }} <b>{{ $payment['to_name'] }}</b>: <b>{{ $money($payment['amount_cents']) }}</b></p>
            @foreach($payment['reasons'] as $reason)
                <p class="muted">{{ $reason['name'] }}: {{ $money($reason['amount_cents']) }}</p>
            @endforeach
            <p class="muted">{{ __('pdf.reference', ['month' => \App\Support\Month::format($statement['month'])]) }}</p>
        </div>
    @empty
        <p>{{ __('pdf.personal.no_payments') }}</p>
    @endforelse

    <h2>{{ __('pdf.personal.costs') }}</h2>
    <table>
        <thead><tr><th>{{ __('participants.detail.item') }}</th><th>{{ __('pdf.personal.kind') }}</th><th class="num">{{ __('pdf.personal.your_amount') }}</th></tr></thead>
        <tbody>
            @forelse($statement['costs'] as $cost)
                <tr><td>{{ $cost['name'] }}</td><td>{{ __('pdf.personal.'.$cost['kind']) }}</td><td class="num">{{ $money($cost['amount_cents']) }}</td></tr>
            @empty
                <tr><td colspan="3">{{ __('pdf.personal.no_costs') }}</td></tr>
            @endforelse
        </tbody>
    </table>
    <footer class="muted">{{ __('pdf.personal.note') }}</footer>
</body>
</html>
