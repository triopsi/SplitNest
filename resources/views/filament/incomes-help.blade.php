<x-filament::section>
    <x-slot name="heading">{{ __('incomes.help.title') }}</x-slot>
    <x-slot name="description">{{ __('incomes.help.intro') }}</x-slot>

    <div class="hh-stack">
        @foreach(['recipient', 'forwarding', 'payments', 'allocation'] as $topic)
            <div>
                <h3><strong>{{ __('incomes.help.'.$topic.'_title') }}</strong></h3>
                <p class="hh-muted">{{ __('incomes.help.'.$topic.'_text') }}</p>
            </div>
        @endforeach
    </div>
</x-filament::section>
