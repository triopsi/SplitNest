<x-filament-panels::page>
    @php $money = fn ($c) => \App\Support\Money::format($c); @endphp
    <div class="hh-stack">
        <div class="hh-hero"><div><div class="hh-kicker">{{ __('yearplan.hero.kicker') }}</div><h2>{{ __('yearplan.hero.title') }}</h2><p>{{ __('yearplan.hero.text') }}</p></div><label>{{ __('yearplan.year') }}<br><input class="hh-input" aria-label="{{ __('yearplan.year') }}" type="number" min="2000" max="2100" wire:model.live.debounce.500ms="year"></label></div>
        @if($problem)<p class="hh-warning">{{ $problem }}</p>@endif
        @if(! $problem)
            @livewire(\App\Filament\Widgets\MonthlyCostsChart::class, ['year' => $year])
        @endif
        <section class="hh-section"><div class="hh-scroll"><table class="hh-table">
            <thead><tr><th>{{ __('yearplan.table.month') }}</th><th>{{ __('yearplan.table.status') }}</th><th class="num">{{ __('yearplan.table.incomes') }}</th><th class="num">{{ __('yearplan.table.costs') }}</th><th class="num">{{ __('yearplan.table.left') }}</th><th>{{ __('yearplan.table.transfers') }}</th></tr></thead>
            <tbody>@foreach($rows as $row)<tr>
                <td><a class="hh-link" href="{{ \App\Filament\Pages\Settlement::getUrl(['month'=>$row['month']]) }}">{{ \App\Support\Month::format($row['month']) }}</a></td><td>{{ $row['closed'] ? __('yearplan.table.closed') : __('yearplan.table.planned') }}</td>
                @if($row['error'])<td colspan="4">{{ $row['error'] }}</td>@else
                <td class="num">{{ $money($row['report']['total_income_cents']) }}</td><td class="num">{{ $money($row['report']['total_expense_cents']) }}</td><td class="num">{{ $money(($row['report']['remaining_cents'] ?? $row['report']['total_income_cents']-$row['report']['total_expense_cents'])) }}</td>
                <td>@forelse(($row['report']['payment_instructions'] ?? $row['report']['transfers']) as $t)<div>{{ $t['from_name'] }} {{ __('settlement.transfers.to') }} {{ $t['to_name'] }}: <b>{{ $money($t['amount_cents']) }}</b></div>@empty {{ __('yearplan.table.balanced') }} @endforelse</td>@endif
            </tr>@endforeach</tbody>
            @if(count($rows) && !collect($rows)->contains(fn($row)=>$row['error']))
            <tfoot><tr><th>{{ __('yearplan.table.total') }}</th><th></th><th class="num">{{ $money(collect($rows)->sum('report.total_income_cents')) }}</th><th class="num">{{ $money(collect($rows)->sum('report.total_expense_cents')) }}</th><th class="num">{{ $money(collect($rows)->sum(fn($row) => $row['report']['remaining_cents'] ?? $row['report']['total_income_cents'] - $row['report']['total_expense_cents'])) }}</th><th></th></tr></tfoot>
            @endif
        </table></div></section>
    </div>
</x-filament-panels::page>