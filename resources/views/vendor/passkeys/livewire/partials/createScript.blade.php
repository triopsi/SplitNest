@script
<script>
    Livewire.on('passkeyPropertiesValidated', async function (eventData) {
        if (typeof startRegistration !== 'function') {
            console.error('startRegistration fehlt: Das Passkey-JavaScript wurde nicht geladen. Bitte die Skript-Einbindung und Netzwerkanfragen prüfen.');
            return;
        }
        const passkeyOptions = eventData[0].passkeyOptions;

        const passkey = await startRegistration({ optionsJSON: passkeyOptions });

        @this.call('storePasskey', JSON.stringify(passkey));
    });
</script>
@endscript
