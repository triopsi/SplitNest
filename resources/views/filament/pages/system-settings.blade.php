<x-filament-panels::page>
    <div class="hh-stack">
        <section class="hh-section">
            <div class="hh-section-head"><h3>Systemeinstellungen</h3></div>
            <form wire:submit="save" class="hh-form">
                {{ $this->form }}
                <div class="hh-note"><x-filament::button type="submit">Speichern</x-filament::button></div>
            </form>
        </section>
    </div>
</x-filament-panels::page>
