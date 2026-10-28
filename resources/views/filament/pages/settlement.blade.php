<x-filament-panels::page>
    <div class="hh-stack">
        <div class="hh-hero">
            <div><div class="hh-kicker">{{ __('settlement.hero.kicker') }}</div><h2>{{ __('settlement.hero.title') }}</h2><p>{{ __('settlement.hero.text') }}</p></div>
            <div class="hh-picker">
                @php $selectedYear = (int) substr($month, 0, 4); $years = range(min(2020, $selectedYear), max((int) now()->format('Y') + 3, $selectedYear)); @endphp
                <label>{{ __('settlement.picker.month') }}<br><select aria-label="{{ __('settlement.picker.month') }}" class="hh-input" wire:model.live="monthPicker">
                    @foreach(__('settlement.months') as $i => $name)
                        <option value="{{ $i + 1 }}">{{ $name }}</option>
                    @endforeach
                </select></label>
                <label>{{ __('settlement.picker.year') }}<br><select aria-label="{{ __('settlement.picker.year') }}" class="hh-input" wire:model.live="yearPicker">
                    @foreach($years as $year)
                        <option value="{{ $year }}">{{ $year }}</option>
                    @endforeach
                </select></label>
            </div>
        </div>
        @if($problem)
            <div role="alert" class="hh-warning">{{ $problem }} <a class="hh-link" href="{{ \App\Filament\Resources\Participants\ParticipantResource::getUrl() }}">{{ __('settlement.manage_participants') }}</a></div>
        @else
            <div class="hh-control">
                @if($closing)
                    <span class="hh-badge">{{ __('settlement.closed_badge', ['date' => $closing->closed_at->format('d.m.Y H:i')]) }}</span>
                    <x-filament::button tag="a" href="{{ route('closings.pdf', $closing) }}" icon="heroicon-o-arrow-down-tray">{{ __('settlement.pdf_button') }}</x-filament::button>
                    {{ $this->reopenMonthAction }}
                @else
                    <span class="hh-badge">{{ __('settlement.open_badge', ['month' => \App\Support\Month::format($month)]) }}</span>
                    {{ $this->closeMonthAction }}
                    <span class="hh-muted">{{ __('settlement.close_hint') }}</span>
                @endif
                <span class="hh-muted">{{ __('settlement.planned_note') }}</span>
            </div>
            {{ $this->content }}
        @endif
    </div>
</x-filament-panels::page>
