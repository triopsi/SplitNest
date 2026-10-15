{{-- Trenner zwischen Passwort-Login und Passkey-Login, dann Passkey-Button (Render-Hook unter dem Formular). --}}
<div style="display:flex;align-items:center;gap:.75rem;margin-top:1rem;color:rgb(156 163 175);font-size:.875rem;">
    <span style="flex:1;border-top:1px solid rgba(156,163,175,.35);"></span>
    <span>{{ __('users.or') }}</span>
    <span style="flex:1;border-top:1px solid rgba(156,163,175,.35);"></span>
</div>
<div class="mt-4" style="text-align:center;">
    <x-authenticate-passkey>
        <x-filament::button type="button" color="gray" icon="heroicon-o-key" outlined style="width:100%;">
            {{ __('passkeys::passkeys.authenticate_using_passkey') }}
        </x-filament::button>
    </x-authenticate-passkey>
</div>
