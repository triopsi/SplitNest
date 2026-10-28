<x-filament::section collapsible>
    <x-slot name="heading">{{ __('expenses.help.title') }}</x-slot>
    <x-slot name="description">{{ __('expenses.help.intro') }}</x-slot>

    <div class="hh-stack">
        @foreach(['payer', 'schedule', 'split', 'funding', 'settlement'] as $topic)
            <div>
                <h3><strong>{{ __('expenses.help.'.$topic.'_title') }}</strong></h3>
                <p class="hh-muted">{{ __('expenses.help.'.$topic.'_text') }}</p>
                @if($topic === 'split')
                    <dl>
                        @foreach(['percentage', 'income', 'equal', 'personal'] as $mode)
                            <div class="hh-note">
                                <dt><strong>{{ __('expenses.modes.'.$mode) }}</strong></dt>
                                <dd class="hh-muted">{{ __('expenses.help.examples.'.$mode) }}</dd>
                            </div>
                        @endforeach
                    </dl>
                @endif
            </div>
        @endforeach
    </div>
</x-filament::section>
