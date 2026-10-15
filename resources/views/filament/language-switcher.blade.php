<x-filament::dropdown placement="bottom-end">
    <x-slot name="trigger">
        <button type="button" class="fi-icon-btn" title="{{ __('nav.language') }}" aria-label="{{ __('nav.language') }}">
            <x-filament::icon icon="heroicon-o-language" class="fi-icon fi-size-lg" />
        </button>
    </x-slot>
    <x-filament::dropdown.list>
        @foreach(\App\Support\Locales::available() as $code => $name)
            <x-filament::dropdown.list.item :href="route('language.switch', $code)" tag="a" :icon="$code === app()->getLocale() ? 'heroicon-o-check' : null">
                {{ $name }}
            </x-filament::dropdown.list.item>
        @endforeach
    </x-filament::dropdown.list>
</x-filament::dropdown>
