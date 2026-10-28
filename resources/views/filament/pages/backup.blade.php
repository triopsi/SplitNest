<x-filament-panels::page>
    <div class="hh-stack">
        <section class="hh-section">
            <div class="hh-section-head"><h3>{{ __('backup.view.title') }}</h3></div>
            <p class="hh-note">{!! __('backup.view.intro') !!}</p>
            <p class="hh-note">{{ __('backup.view.users') }}</p>
            <p class="hh-note">{{ __('backup.scope.limits') }}</p>
            <p class="hh-note">{{ __('backup.scope.legacy') }}</p>
            <div class="hh-note" style="display:flex;gap:.75rem;flex-wrap:wrap">{{ $this->exportAction }}{{ $this->importAction }}</div>
        </section>
    </div>
</x-filament-panels::page>
