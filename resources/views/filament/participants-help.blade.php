<x-filament::section collapsible>
    <x-slot name="heading">{{ __('participants.help.title') }}</x-slot>
    <x-slot name="description">{{ __('participants.help.intro') }}</x-slot>

    <div class="hh-stack">
        @foreach(['assignment', 'email', 'privacy', 'accounts', 'history'] as $topic)
            <div>
                <h3><strong>{{ __('participants.help.'.$topic.'_title') }}</strong></h3>
                <p class="hh-muted">{{ __('participants.help.'.$topic.'_text') }}</p>
            </div>
        @endforeach
    </div>
</x-filament::section>
