<!DOCTYPE html>
<html lang="de"><head><meta charset="UTF-8"><title>Haushaltsabrechnung {{ $report['month'] }}</title>
<style>
@page {margin:35px 35px 40px}body{font-family:"DejaVu Sans",sans-serif;font-size:10px;color:#213d41}h1{font-size:25px;margin-bottom:5px}h2{font-size:15px;margin-top:24px}p{line-height:1.5}.muted{color:#657c7e}.hero{background:#143f46;color:white;padding:22px}.hero p{color:#cce4de}.totals{margin:18px 0;background:#edf5f3;padding:12px}.transfer{padding:12px;background:#e6f3ed;margin-bottom:6px;font-size:12px}table{border-collapse:collapse;width:100%;margin-top:10px}th,td{border:1px solid #d5e1df;padding:7px;text-align:left;vertical-align:top}th{background:#edf3f2;font-size:9px}td.num,th.num{text-align:right;white-space:nowrap}tr{page-break-inside:avoid}.expense{page-break-inside:avoid;margin:12px 0;border:1px solid #d5e1df;padding:10px}.expense strong{font-size:11px}.small{font-size:9px}footer{margin-top:28px;border-top:1px solid #d5e1df;padding-top:10px}
</style></head><body>
@php $money=fn($c)=>\App\Support\Money::format($c); $names=array_column($report['participants'],'name','id'); @endphp
<div class="hero"><div>SplitNest</div><h1>Abrechnung {{ $report['month'] }}</h1><p>Verbindlicher Monatsplan · gespeichert am {{ $closing->closed_at->format('d.m.Y H:i') }}</p></div>
<div class="totals">Einkünfte: <b>{{ $money($report['total_income_cents']) }}</b> &nbsp; Kosten: <b>{{ $money($report['total_expense_cents']) }}</b> &nbsp; Übrig: <b>{{ $money($report['total_income_cents']-$report['total_expense_cents']) }}</b></div>
<h2>Diese Beträge bitte überweisen</h2>
@forelse($report['transfers'] as $t)<div class="transfer"><b>{{ $t['from_name'] }}</b> an <b>{{ $t['to_name'] }}</b>: <b>{{ $money($t['amount_cents']) }}</b><br><span class="small">Verwendungszweck: Haushaltsanteil {{ $report['month'] }}</span></div>@empty<p>Es sind keine Ausgleichszahlungen erforderlich.</p>@endforelse
<h2>Persönliche Übersicht</h2><table><thead><tr><th>Teilnehmer</th><th class="num">Einkünfte</th><th class="num">Eigener Anteil</th><th class="num">Bezahlt*</th><th class="num">Ausgleich**</th><th class="num">Übrig</th></tr></thead><tbody>
@foreach($report['participants'] as $p)<tr><td>{{ $p['name'] }}</td><td class="num">{{ $money($p['income_cents']) }}</td><td class="num">{{ $money($p['share_cents']) }}</td><td class="num">{{ $money($p['paid_cents']) }}</td><td class="num">{{ $money($p['balance_cents']) }}</td><td class="num">{{ $money($p['remaining_cents']) }}</td></tr>@endforeach
</tbody></table><p class="small muted">* Laut geplantem Bezahler, keine Bestätigung einer Bankzahlung. ** Positiv: erhält Geld. Negativ: zahlt Geld.</p>
<h2>Kosten und Aufteilung</h2>
@foreach($report['expenses'] as $e)<div class="expense"><strong>{{ $e['name'] }} · {{ $money($e['amount_cents']) }}</strong><p>Bezahler: {{ $names[$e['payer_id']] }} · {{ \App\Models\Expense::MODES[$e['split_mode']] }}</p>
@foreach($e['allocations'] as $id=>$amount)<span>{{ $names[$id] }}: <b>{{ $money($amount) }}</b> ({{ number_format($e['weights'][$id]/array_sum($e['weights'])*100,2,',','.') }} %) &nbsp; </span>@endforeach
</div>@endforeach
<h2>Berücksichtigte Einkünfte</h2><table><thead><tr><th>Teilnehmer</th><th>Einkunft</th><th class="num">Betrag</th></tr></thead><tbody>@foreach($report['incomes'] as $income)<tr><td>{{ $names[$income['participant_id']] }}</td><td>{{ $income['name'] }}</td><td class="num">{{ $money($income['amount_cents']) }}</td></tr>@endforeach</tbody></table>
<footer class="small muted">Beträge in EUR. Aufteilung pro Kostenposition auf ganze Cent, Restcents nach größtem Nachkommaanteil (bei Gleichstand nach Teilnehmer-ID). Der gespeicherte Stand wird durch spätere Änderungen an Stammdaten nicht verändert.</footer>
</body></html>