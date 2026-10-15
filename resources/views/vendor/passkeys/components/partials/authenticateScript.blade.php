<script>
    async function authenticateWithPasskey(remember = false) {
        if (typeof startAuthentication !== 'function') {
            console.error('startAuthentication fehlt: Das Passkey-JavaScript wurde nicht geladen. Bitte die Skript-Einbindung und Netzwerkanfragen prüfen.');
            return;
        }
        const response = await fetch('{{ route('passkeys.authentication_options') }}')

        const options = await response.json();

        const startAuthenticationResponse = await startAuthentication({ optionsJSON: options, });

        const form = document.getElementById('passkey-login-form');
    
        form.addEventListener('formdata', ({formData}) => {
            formData.set('remember', remember);
            formData.set('start_authentication_response', JSON.stringify(startAuthenticationResponse));
        });

        form.submit();
    }
</script>
