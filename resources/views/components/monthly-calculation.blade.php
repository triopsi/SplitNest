@props(['person', 'budgets' => []])
            <dl class="hh-calculation">
                <div><dt>{{ __('participants.account.income') }}</dt><dd>+{{ \App\Support\Money::format($person['income_cents']) }}</dd></div>
                <div><dt>{{ __('participants.account.share') }}</dt><dd>{{ \App\Support\Money::format(-$person['share_cents']) }}</dd></div>
                @foreach(['opening_reserved_cents' => 'opening_reserves', 'savings_deposited_cents' => 'savings', 'reserved_cents' => 'reserves'] as $field => $label)
                    @if(($person[$field] ?? 0) !== 0)
                        @php $amount = $field === 'reserved_cents' ? -$person[$field] : $person[$field]; @endphp
                        <div><dt>{{ __('participants.account.'.$label) }}</dt><dd>{{ $amount > 0 ? '+' : '' }}{{ \App\Support\Money::format($amount) }}</dd></div>
                        @if(in_array($field, ['reserved_cents', 'opening_reserved_cents'], true))
                            @foreach($budgets as $budget)
                                @php $reserved = $budget[$field === 'opening_reserved_cents' ? 'opening_balances' : 'balances'][$person['id']] ?? 0; @endphp
                                @if($reserved !== 0)
                                    <div class="hh-reserve-item">
                                        <dt>{{ $budget['name'] }}</dt>
                                        <dd>{{ $field === 'reserved_cents' ? '-' : '+' }}{{ \App\Support\Money::format($reserved) }}</dd>
                                    </div>
                                @endif
                            @endforeach
                        @endif
                    @endif
                @endforeach
                <div class="hh-calculation-total"><dt>{{ __('participants.detail.left') }}</dt><dd class="{{ $person['remaining_cents'] < 0 ? 'hh-negative' : '' }}">{{ $person['remaining_cents'] > 0 ? '+' : '' }}{{ \App\Support\Money::format($person['remaining_cents']) }}</dd></div>
            </dl>

<x-savings-explanation />
