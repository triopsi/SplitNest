{{-- Passkey-Verwaltung im Filament-Look (Paket-View, überschrieben). --}}
<div>
    <form id="passkeyForm" wire:submit="validatePasskeyProperties">
        <div style="display:flex;gap:.75rem;align-items:flex-end;flex-wrap:wrap;">
            <div style="flex:1;min-width:220px;">
                <label for="passkey-name" class="fi-fo-field-label" style="display:block;margin-bottom:.375rem;">
                    <span class="fi-fo-field-label-content">{{ __('passkeys::passkeys.name') }}</span>
                </label>
                <x-filament::input.wrapper>
                    <x-filament::input id="passkey-name" type="text" wire:model="name" autocomplete="off" :placeholder="__('passkeys::passkeys.name_placeholder')" />
                </x-filament::input.wrapper>
                @error('name', 'passkeyForm')
                    <p style="margin-top:.375rem;font-size:.875rem;color:#e11d48;">{{ $message }}</p>
                @enderror
            </div>
            <x-filament::button type="submit" icon="heroicon-o-key">
                {{ __('passkeys::passkeys.create') }}
            </x-filament::button>
        </div>
    </form>

    <div style="margin-top:1.25rem;">
        @if($passkeys->isEmpty())
            <p class="hh-note" style="text-align:center;color:#e11d48;">{{ __('passkeys::passkeys.no_passkeys_registered') }}</p>
        @else
            <div class="hh-scroll">
                <table class="hh-table">
                    <thead>
                        <tr>
                            <th>{{ __('passkeys::passkeys.name') }}</th>
                            <th>{{ __('passkeys::passkeys.last_used') }}</th>
                            <th class="num"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($passkeys as $passkey)
                            <tr>
                                <td>{{ $passkey->name }}</td>
                                <td>{{ $passkey->last_used_at?->diffForHumans() ?? __('passkeys::passkeys.not_used_yet') }}</td>
                                <td class="num">
                                    <x-filament::icon-button icon="heroicon-o-trash" color="danger" wire:click="deletePasskey('{{ $passkey->id }}')" :label="__('passkeys::passkeys.delete')" />
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>

@include('passkeys::livewire.partials.createScript')

{{-- Sinnvoller Standard-Name je nach Gerät, solange das Feld leer ist. --}}
@script
<script>
    if (! $wire.name) {
        const ua = navigator.userAgent || '';
        const names = @js(__('users.passkeys.default_names'));
        let device = names.fallback;
        if (ua.includes('Windows')) device = names.windows;
        else if (ua.includes('Android')) device = names.android;
        else if (ua.includes('iPhone')) device = names.iphone;
        else if (ua.includes('iPad')) device = names.ipad;
        else if (ua.includes('Mac')) device = names.mac;
        else if (ua.includes('Linux')) device = names.linux;
        $wire.set('name', device);
    }
</script>
@endscript
