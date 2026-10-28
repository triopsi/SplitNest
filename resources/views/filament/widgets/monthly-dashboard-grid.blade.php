@php
    $dashboard = $getLivewire();
    $layout = $dashboard->dashboardLayout;
    $widgets = \App\Support\MonthlyDashboardLayout::widgets();
    $widgetData = $dashboard->getWidgetData();
@endphp
<div
    class="hh-dashboard"
    x-data="monthlyDashboard(@js($layout), @js(['saved' => __('settlement.layout.saved'), 'error' => __('settlement.layout.error')]))"
    :class="{ 'is-editing': editing }"
    @keydown.escape.window="if (editing) cancel()"
>
    <div class="hh-dashboard-toolbar">
        <div class="hh-dashboard-hint">
            <span x-show="editing" x-cloak>{{ __('settlement.layout.hint') }}</span>
            <span x-show="mobile" x-cloak>{{ __('settlement.layout.mobile') }}</span>
            <span role="status" aria-live="polite" class="hh-dashboard-status" :class="{ 'is-error': failed }" x-text="status"></span>
        </div>
        <div class="hh-dashboard-actions">
            <x-filament::button color="gray" icon="heroicon-o-adjustments-horizontal" x-show="!editing && !mobile" x-on:click="edit()" x-bind:disabled="saving">
                {{ __('settlement.layout.edit') }}
            </x-filament::button>
            <div class="hh-dashboard-actions" x-show="editing" x-cloak>
                <x-filament::button icon="heroicon-o-check" x-on:click="save()" x-bind:disabled="saving || mobile">
                    {{ __('settlement.layout.save') }}
                </x-filament::button>
                <x-filament::button color="gray" x-on:click="cancel()" x-bind:disabled="saving">
                    {{ __('settlement.layout.cancel') }}
                </x-filament::button>
                <x-filament::button color="gray" icon="heroicon-o-arrow-path" x-on:click="reset()" x-bind:disabled="saving">
                    {{ __('settlement.layout.reset') }}
                </x-filament::button>
            </div>
        </div>
    </div>
    <div class="grid-stack" x-ref="grid" wire:ignore.self>
        @foreach($layout as $item)
            @php $definition = $widgets[$item['id']]; @endphp
            <div
                class="grid-stack-item"
                wire:key="monthly-layout-{{ $item['id'] }}"
                wire:ignore.self
                gs-id="{{ $item['id'] }}"
                gs-x="{{ $item['x'] }}" gs-y="{{ $item['y'] }}"
                gs-w="{{ $item['w'] }}" gs-h="{{ $item['h'] }}"
                gs-min-w="{{ $definition['minW'] }}" gs-min-h="{{ $definition['minH'] }}"
                gs-max-h="20"
                @if($item['id'] === 'transfers')
                    gs-size-to-content="true"
                @endif
            >
                <div class="grid-stack-item-content" wire:ignore.self>
                    <button
                        type="button" class="hh-widget-drag" x-show="editing" x-cloak
                        aria-label="{{ __('settlement.layout.move', ['widget' => __($definition['label'])]) }}"
                        x-bind:disabled="saving"
                        x-on:keydown="keyboard($event, @js($item['id']))"
                    >
                        <x-filament::icon icon="heroicon-o-arrows-pointing-out" style="width: 1rem; height: 1rem" />
                        {{ __($definition['label']) }}
                    </button>
                    <div class="hh-widget-body">
                        @livewire($definition['widget'], $widgetData, key('monthly-widget-'.$item['id']))
                    </div>
                </div>
            </div>
        @endforeach
    </div>
</div>
