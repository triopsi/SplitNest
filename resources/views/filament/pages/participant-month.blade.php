<x-filament-panels::page>
    @php $money = fn ($cents) => \App\Support\Money::format($cents); $monthName = \Illuminate\Support\Carbon::createFromFormat('Y-m', $month)->translatedFormat('F Y'); @endphp
    <div class="hh-stack">
        <div class="hh-hero">
            <div><div class="hh-kicker">Teilnehmer-Detail</div><h2>{{ $participant->name }} · {{ $monthName }}</h2><p>Alle Einkünfte und Kostenanteile, die {{ $participant->name }} in diesem Monat betreffen.</p></div>
            <a class="hh-link" href="{{ \App\Filament\Pages\Settlement::getUrl(['month' => $month]) }}">Zurück zur Monatsübersicht</a>
        </div>
        @if($problem)
            <div role="alert" class="hh-warning">{{ $problem }}</div>
        @elseif($person)
            <div class="hh-cards">
                <div class="hh-card">
                    <div class="hh-card-head"><x-filament::icon icon="heroicon-o-banknotes" class="hh-icon" /><label>Einkünfte</label></div>
                    <div class="hh-number">{{ $money($person['income_cents']) }}</div>
                    <div class="hh-muted">Geplante Einnahmen in diesem Monat.</div>
                </div>
                <div class="hh-card">
                    <div class="hh-card-head"><x-filament::icon icon="heroicon-o-receipt-percent" class="hh-icon" /><label>Eigener Kostenanteil</label></div>
                    <div class="hh-number">{{ $money($person['share_cents']) }}</div>
                    <div class="hh-muted">Anteil an den Monatskosten laut Aufteilungsregeln.</div>
                </div>
                <div class="hh-card">
                    <div class="hh-card-head"><x-filament::icon icon="heroicon-o-credit-card" class="hh-icon" /><label>Rechnungen bezahlt</label></div>
                    <div class="hh-number">{{ $money($person['paid_cents']) }}</div>
                    <div class="hh-muted">Rechnungen, die {{ $participant->name }} bezahlt hat.</div>
                </div>
                <div class="hh-card">
                    <div class="hh-card-head"><x-filament::icon icon="heroicon-o-arrows-right-left" class="hh-icon" /><label>Ausgleich</label></div>
                    <div class="hh-number {{ $person['balance_cents'] < 0 ? 'hh-negative' : '' }}">{{ $money($person['balance_cents']) }}</div>
                    <div class="hh-muted">Positiv = erhält Geld, negativ = zahlt Geld.</div>
                </div>
                <div class="hh-card">
                    <div class="hh-card-head"><x-filament::icon icon="heroicon-o-wallet" class="hh-icon" /><label>Übrig</label></div>
                    <div class="hh-number {{ $person['remaining_cents'] < 0 ? 'hh-negative' : '' }}">{{ $money($person['remaining_cents']) }}</div>
                    <div class="hh-muted">Einkommen minus eigener Kostenanteil.</div>
                </div>
            </div>
            <section class="hh-section">
                <div class="hh-section-head"><h3>Kosten im {{ $monthName }}</h3><span class="hh-muted">{{ count($expenses) }} Posten betreffen {{ $participant->name }}</span></div>
                <div class="hh-scroll"><table class="hh-table">
                    <thead><tr><th>Posten</th><th>Bezahlt von</th><th>Regel</th><th class="num">Gesamt</th><th class="num">Anteil {{ $participant->name }}</th></tr></thead>
                    <tbody>@forelse($expenses as $expense)@php $shareCents = $expense['allocations'][$participant->id] ?? 0; @endphp<tr><td>{{ $expense['name'] }}</td><td>{{ $names[$expense['payer_id']] }}</td><td>{{ \App\Models\Expense::MODES[$expense['split_mode']] }}</td><td class="num">{{ $money($expense['amount_cents']) }}</td><td class="num"><b>{{ $money($shareCents) }}</b><br><span class="hh-muted">{{ $expense['amount_cents'] > 0 ? number_format(round($shareCents * 100 / $expense['amount_cents']), 0, ',', '').' %' : '–' }}</span></td></tr>@empty<tr><td colspan="5">Keine Kosten in diesem Monat.</td></tr>@endforelse</tbody>
                </table></div>
            </section>
            <section class="hh-section">
                <div class="hh-section-head"><h3>Einkünfte im {{ $monthName }}</h3><span class="hh-muted">{{ count($incomes) }} Positionen</span></div>
                <div class="hh-scroll"><table class="hh-table">
                    <thead><tr><th>Bezeichnung</th><th class="num">Betrag</th></tr></thead>
                    <tbody>@forelse($incomes as $income)<tr><td>{{ $income['name'] }}</td><td class="num">{{ $money($income['amount_cents']) }}</td></tr>@empty<tr><td colspan="2">Keine Einkünfte in diesem Monat.</td></tr>@endforelse</tbody>
                </table></div>
            </section>
        @endif
    </div>
</x-filament-panels::page>
