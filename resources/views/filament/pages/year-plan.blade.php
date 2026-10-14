<x-filament-panels::page>
    @php $money = fn ($c) => \App\Support\Money::format($c); @endphp
    <div class="hh-stack">
        <div class="hh-hero"><div><div class="hh-kicker">Zwölf Monate voraus</div><h2>Euer Haushaltsjahr.</h2><p>Abgeschlossene Monate zeigen den gespeicherten Stand. Offene Monate rechnen mit euren aktuellen Einträgen.</p></div><label>Jahr<br><input class="hh-input" aria-label="Jahr" type="number" min="2000" max="2100" wire:model.live.debounce.500ms="year"></label></div>
        @if($problem)<p class="hh-warning">{{ $problem }}</p>@endif
        <section class="hh-section"><div class="hh-scroll"><table class="hh-table">
            <thead><tr><th>Monat</th><th>Status</th><th class="num">Einkünfte</th><th class="num">Kosten</th><th class="num">Übrig</th><th>Ausgleichszahlungen</th></tr></thead>
            <tbody>@foreach($rows as $row)<tr>
                <td><a class="hh-link" href="{{ \App\Filament\Pages\Settlement::getUrl(['month'=>$row['month']]) }}">{{ $row['month'] }}</a></td><td>{{ $row['closed'] ? 'Abgeschlossen' : 'Planung' }}</td>
                @if($row['error'])<td colspan="4">{{ $row['error'] }}</td>@else
                <td class="num">{{ $money($row['report']['total_income_cents']) }}</td><td class="num">{{ $money($row['report']['total_expense_cents']) }}</td><td class="num">{{ $money($row['report']['total_income_cents']-$row['report']['total_expense_cents']) }}</td>
                <td>@forelse($row['report']['transfers'] as $t)<div>{{ $t['from_name'] }} an {{ $t['to_name'] }}: <b>{{ $money($t['amount_cents']) }}</b></div>@empty Ausgeglichen @endforelse</td>@endif
            </tr>@endforeach</tbody>
            @if(count($rows) && !collect($rows)->contains(fn($row)=>$row['error']))
            <tfoot><tr><th>Gesamt</th><th></th><th class="num">{{ $money(collect($rows)->sum('report.total_income_cents')) }}</th><th class="num">{{ $money(collect($rows)->sum('report.total_expense_cents')) }}</th><th class="num">{{ $money(collect($rows)->sum('report.total_income_cents') - collect($rows)->sum('report.total_expense_cents')) }}</th><th></th></tr></tfoot>
            @endif
        </table></div></section>
    </div>
</x-filament-panels::page>