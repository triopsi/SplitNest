<x-filament-panels::page>
    @php $money = fn ($cents) => \App\Support\Money::format($cents); @endphp
    <div class="hh-stack">
        <div class="hh-hero">
            <div><div class="hh-kicker">Gemeinsam planen · Fair aufteilen</div><h2>Ein Monat. Alles im Blick.</h2><p>Einkünfte, Kosten und die Zahlungen, die euren Haushalt ausgleichen.</p></div>
            <div class="hh-picker">
                @php $selectedYear = (int) substr($month, 0, 4); $years = range(min(2020, $selectedYear), max((int) now()->format('Y') + 3, $selectedYear)); @endphp
                <label>Abrechnungsmonat<br><select aria-label="Abrechnungsmonat" class="hh-input" wire:model.live="monthPicker">
                    @foreach(['Januar', 'Februar', 'März', 'April', 'Mai', 'Juni', 'Juli', 'August', 'September', 'Oktober', 'November', 'Dezember'] as $i => $name)
                        <option value="{{ $i + 1 }}">{{ $name }}</option>
                    @endforeach
                </select></label>
                <label>Jahr<br><select aria-label="Jahr" class="hh-input" wire:model.live="yearPicker">
                    @foreach($years as $year)
                        <option value="{{ $year }}">{{ $year }}</option>
                    @endforeach
                </select></label>
            </div>
        </div>
        @if($problem)
            <div role="alert" class="hh-warning">{{ $problem }} <a class="hh-link" href="{{ \App\Filament\Resources\Participants\ParticipantResource::getUrl() }}">Teilnehmer verwalten</a></div>
        @else
            @php $names = array_column($report['participants'], 'name', 'id'); @endphp
            <div class="hh-control">
                @if($closing)
                    <span class="hh-badge">Abgeschlossen am {{ $closing->closed_at->format('d.m.Y H:i') }}</span>
                    <x-filament::button tag="a" href="{{ route('closings.pdf', $closing) }}" icon="heroicon-o-arrow-down-tray">Abrechnung als PDF</x-filament::button>
                    {{ $this->reopenMonthAction }}
                @else
                    <span class="hh-badge">Offener Monatsplan {{ $month }}</span>
                    {{ $this->closeMonthAction }}
                    <span class="hh-muted">„Monat abschließen“ friert Einkünfte, Kosten und deren Aufteilung für diesen Monat ein: Der Abschluss ist danach unveränderlich und die PDF-Abrechnung bleibt bei späteren Änderungen identisch.</span>
                @endif
                <span class="hh-muted">Geplante Zahlungen, keine Bankumsätze. Überweisungen werden noch nicht als bezahlt erfasst.</span>
            </div>
            <div class="hh-cards">
                <div class="hh-card">
                    <div class="hh-card-head"><x-filament::icon icon="heroicon-o-banknotes" class="hh-icon" /><label>Einkünfte zusammen</label></div>
                    <div class="hh-number">{{ $money($report['total_income_cents']) }}</div>
                    <div class="hh-muted">Alle Einkünfte, die in diesem Monat geplant sind.</div>
                </div>
                <div class="hh-card">
                    <div class="hh-card-head"><x-filament::icon icon="heroicon-o-receipt-percent" class="hh-icon" /><label>Kosten zusammen</label></div>
                    <div class="hh-number">{{ $money($report['total_expense_cents']) }}</div>
                    <div class="hh-muted">Alle Kostenpositionen, die in diesem Monat fällig werden.</div>
                </div>
                <div class="hh-card">
                    <div class="hh-card-head"><x-filament::icon icon="heroicon-o-wallet" class="hh-icon" /><label>Nach allen Kosten übrig</label></div>
                    <div class="hh-number {{ $report['total_income_cents'] < $report['total_expense_cents'] ? 'hh-negative' : '' }}">{{ $money($report['total_income_cents'] - $report['total_expense_cents']) }}</div>
                    <div class="hh-muted">Einkünfte minus Kosten — euer frei verfügbarer Rest.</div>
                </div>
            </div>
            <section class="hh-section">
                <div class="hh-section-head"><h3>Diese Beträge sind zu überweisen</h3><span class="hh-muted">{{ count($report['transfers']) }} Ausgleichszahlungen</span></div>
                @forelse($report['transfers'] as $transfer)
                    <div class="hh-transfer"><div class="hh-person-line"><x-person :name="$transfer['from_name']" :email="$emails[$transfer['from_id']] ?? null" /><span>an</span><x-person :name="$transfer['to_name']" :email="$emails[$transfer['to_id']] ?? null" /></div><div class="hh-muted">Haushaltsanteil {{ $month }}</div><strong>{{ $money($transfer['amount_cents']) }}</strong></div>
                @empty
                    <p class="hh-note">Der Haushalt ist ausgeglichen. Es ist keine Ausgleichszahlung nötig.</p>
                @endforelse
            </section>
            <section class="hh-section">
                <div class="hh-section-head"><h3>Eure persönliche Übersicht</h3></div>
                <div class="hh-scroll"><table class="hh-table">
                    <thead><tr><th>Teilnehmer</th><th class="num">Einkünfte</th><th class="num">Eigener Kostenanteil</th><th class="num">Anteil an Kosten</th><th class="num">Rechnungen bezahlt*</th><th class="num">Ausgleich**</th><th class="num">Übrig</th></tr></thead>
                    <tbody>@foreach($report['participants'] as $person)<tr><td><x-person :name="$person['name']" :email="$emails[$person['id']] ?? null" :href="\App\Filament\Pages\ParticipantMonth::getUrl(['participant' => $person['id'], 'month' => $month])" /></td><td class="num">{{ $money($person['income_cents']) }}</td><td class="num">{{ $money($person['share_cents']) }}</td><td class="num">{{ $report['total_expense_cents'] > 0 ? number_format(round($person['share_cents'] * 100 / $report['total_expense_cents']), 0, ',', '').' %' : '–' }}</td><td class="num">{{ $money($person['paid_cents']) }}</td><td class="num">{{ $money($person['balance_cents']) }}</td><td class="num {{ $person['remaining_cents'] < 0 ? 'hh-negative' : '' }}"><b>{{ $money($person['remaining_cents']) }}</b></td></tr>@endforeach</tbody>
                </table></div>
                <p class="hh-note">Anteil an Kosten = prozentualer Anteil am gemeinsamen Kostentopf. * Laut geplantem Bezahler. ** Positiv = erhält Geld, negativ = zahlt Geld. Übrig = Einkommen minus eigener Kostenanteil.</p>
            </section>
            <section class="hh-section">
                <div class="hh-section-head"><h3>So teilen sich die Kosten auf</h3><a class="hh-link" href="{{ \App\Filament\Resources\Expenses\ExpenseResource::getUrl() }}">Kosten verwalten</a></div>
                <div class="hh-scroll"><table class="hh-table">
                    <thead><tr><th>Posten</th><th>Bezahler</th><th>Regel</th><th class="num">Gesamt</th>@foreach($report['participants'] as $p)<th class="num"><x-person :name="$p['name']" :email="$emails[$p['id']] ?? null" /></th>@endforeach</tr></thead>
                    <tbody>@forelse($report['expenses'] as $expense)<tr><td>{{ $expense['name'] }}</td><td><x-person :name="$names[$expense['payer_id']]" :email="$emails[$expense['payer_id']] ?? null" /></td><td>{{ \App\Models\Expense::MODES[$expense['split_mode']] }}</td><td class="num">{{ $money($expense['amount_cents']) }}</td>@foreach($report['participants'] as $p)@php $shareCents = $expense['allocations'][$p['id']] ?? 0; @endphp<td class="num">{{ $money($shareCents) }}<br><span class="hh-muted">{{ $expense['amount_cents'] > 0 ? number_format(round($shareCents * 100 / $expense['amount_cents']), 0, ',', '').' %' : '–' }}</span></td>@endforeach</tr>@empty<tr><td colspan="{{ 4 + count($report['participants']) }}">Für diesen Monat sind noch keine Kosten erfasst.</td></tr>@endforelse</tbody>
                </table></div>
            </section>
        @endif
    </div>
</x-filament-panels::page>