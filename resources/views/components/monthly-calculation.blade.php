@props(['person'])
            <dl class="hh-calculation">
                <div><dt>{{ __('participants.account.income') }}</dt><dd>+{{ \App\Support\Money::format($person['income_cents']) }}</dd></div>
                <div><dt>{{ __('participants.account.share') }}</dt><dd>{{ \App\Support\Money::format(-$person['share_cents']) }}</dd></div>
                @foreach(['opening_reserved_cents' => 'opening_reserves', 'savings_deposited_cents' => 'savings', 'reserved_cents' => 'reserves'] as $field => $label)
                    @if(($person[$field] ?? 0) !== 0)
                        @php $amount = $field === 'reserved_cents' ? -$person[$field] : $person[$field]; @endphp
                        <div><dt>{{ __('participants.account.'.$label) }}</dt><dd>{{ $amount > 0 ? '+' : '' }}{{ \App\Support\Money::format($amount) }}</dd></div>
                    @endif
                @endforeach
                <div class="hh-calculation-total"><dt>{{ __('participants.detail.left') }}</dt><dd class="{{ $person['remaining_cents'] < 0 ? 'hh-negative' : '' }}">{{ $person['remaining_cents'] > 0 ? '+' : '' }}{{ \App\Support\Money::format($person['remaining_cents']) }}</dd></div>
            </dl>
