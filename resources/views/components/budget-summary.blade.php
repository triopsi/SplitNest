@if(!empty($budgets))
    <section class="hh-section">
        <div class="hh-section-head"><h2>{{ __('funding.budget_summary') }}</h2></div>
        <div class="hh-scroll"><table class="hh-table">
            <thead><tr><th>{{ __('funding.budget') }}</th><th class="num">{{ __('funding.opening') }}</th><th class="num">{{ __('funding.receipts') }}</th><th class="num">{{ __('funding.spent') }}</th><th class="num">{{ __('funding.closing') }}</th></tr></thead>
            <tbody>
                @foreach($budgets as $budget)
                    <tr><td>{{ $budget['name'] }}</td><td class="num">{{ \App\Support\Money::format($budget['opening_cents']) }}</td><td class="num">{{ \App\Support\Money::format($budget['income_cents']) }}</td><td class="num">{{ \App\Support\Money::format($budget['spent_cents']) }}</td><td class="num">{{ \App\Support\Money::format($budget['closing_cents']) }}</td></tr>
                @endforeach
            </tbody>
        </table></div>
        <p class="hh-note">{{ __('funding.budget_note') }}</p>
    </section>
@endif
