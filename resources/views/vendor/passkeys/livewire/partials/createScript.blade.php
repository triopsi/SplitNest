@script
<script>
    Livewire.on('passkeyPropertiesValidated', async function (eventData) {
        if (typeof startRegistration !== 'function') {
            console.error('startRegistration fehlt: veraltetes Frontend-Bundle. Bitte Assets neu bauen/deployen (npm run build).');
            return;
        }
        const passkeyOptions = eventData[0].passkeyOptions;

        const passkey = await startRegistration({ optionsJSON: passkeyOptions });

        @this.call('storePasskey', JSON.stringify(passkey));
    });
</script>
@endscript
