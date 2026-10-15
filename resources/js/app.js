// simplewebauthn: Passkey-Registrierung/-Login (spatie/laravel-passkeys ruft die
// globalen Funktionen startRegistration/startAuthentication in seinen Blade-Views auf).
import { startAuthentication, startRegistration } from '@simplewebauthn/browser';

window.startAuthentication = startAuthentication;
window.startRegistration = startRegistration;
