<x-filament-panels::page>
    <form wire:submit="save">
        {{ $this->form }}
        <div style="margin-top:1rem;"><x-filament::button type="submit">{{ __('settings.save') }}</x-filament::button></div>
    </form>
</x-filament-panels::page>
