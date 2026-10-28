<x-filament::section collapsible :collapsed="$collapsed ?? false">
    <x-slot name="heading">{{ __('funding.help.title') }}</x-slot>
    <x-slot name="description">{{ __('funding.help.intro') }}</x-slot>

    <div class="hh-stack">
        @foreach(['purpose', 'assign', 'example', 'remainder'] as $topic)
            <div>
                <h3><strong>{{ __('funding.help.'.$topic.'_title') }}</strong></h3>
                <p class="hh-muted">{{ __('funding.help.'.$topic.'_text') }}</p>
            </div>
        @endforeach
    </div>
</x-filament::section>
