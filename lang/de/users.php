<?php

/**
 * Benutzerverwaltung, E-Mail-Einladungen, Ersteinrichtung und das
 * splitnest:user-Kommando.
 */
return [
    'label' => 'Benutzer',
    'plural' => 'Benutzer',
    'menu' => 'Benutzer',
    'edit_profile' => 'Profil ändern',
    'or' => 'ODER',
    'fields' => [
        'name' => 'Name',
        'email' => 'E-Mail',
        'password' => 'Passwort',
        'password_hint' => 'Mindestens 12 Zeichen. Beim Bearbeiten leer lassen, um das Passwort zu behalten.',
        'is_admin' => 'Administrator',
        'is_admin_hint' => 'Nur Administratoren dürfen sich einloggen und den Haushalt verwalten.',
    ],
    'table' => [
        'name' => 'Name',
        'email' => 'E-Mail',
        'admin' => 'Admin',
        'created' => 'Erstellt',
    ],
    'invite' => [
        'action' => 'Per E-Mail einladen',
        'success' => 'Einladung verschickt. Der Link ist :hours Stunden gültig.',
        'error' => 'Einladung nicht möglich',
        'account_exists' => 'Für diese E-Mail-Adresse besteht bereits ein Konto.',
    ],
    'first_run' => [
        'title' => 'Ersteinrichtung',
        'heading' => 'Ersten Administrator anlegen',
        'done' => 'Die Ersteinrichtung ist bereits abgeschlossen.',
        'password_hint' => 'Mindestens 12 Zeichen.',
    ],
    'invitation' => [
        'title' => 'Einladung annehmen',
        'heading' => 'Konto für den Haushalt anlegen',
        'email_hint' => 'Die Einladung gilt für diese E-Mail-Adresse.',
        'invalid' => 'Diese Einladung ist ungültig, abgelaufen oder wurde bereits verwendet.',
        'password_hint' => 'Mindestens 12 Zeichen.',
    ],
    'command' => [
        'description' => 'Legt einen Haushaltsadministrator an (Passwort wird verdeckt abgefragt).',
        'name_option' => 'Anzeigename',
        'email' => 'E-Mail-Adresse',
        'name' => 'Name',
        'password' => 'Passwort (mindestens 12 Zeichen)',
        'password_repeat' => 'Passwort wiederholen',
        'success' => 'Login für :email erstellt. Dieser Benutzer darf den gesamten Haushalt verwalten.',
    ],
    'list_command' => [
        'empty' => 'Keine passenden Benutzer gefunden. Mit splitnest:user kannst du einen Administrator erstellen.',
        'yes' => 'Ja',
        'no' => 'Nein',
    ],
    'edit_command' => [
        'not_found' => 'Benutzer :user wurde nicht gefunden. Mit splitnest:users kannst du die Konten auflisten.',
        'conflicting_roles' => 'Verwende entweder --admin oder --no-admin, nicht beide Optionen.',
        'interaction_required' => 'Für die Passworteingabe oder das Bearbeiten ohne Optionen wird ein interaktives Terminal benötigt. Lass --no-interaction weg.',
        'optional_password' => 'Neues Passwort (mindestens 12 Zeichen; leer lassen, um das bisherige zu behalten)',
        'admin_question' => 'Darf dieser Benutzer den Haushalt verwalten?',
        'last_admin' => 'Der letzte Administrator muss seine Admin-Rechte behalten. Vergib sie zuerst an einen anderen Benutzer.',
        'success' => 'Benutzer #:id (:email) aktualisiert.',
    ],
    'passkeys' => [
        'heading' => 'Passkeys',
        'description' => 'Ohne Passwort einloggen: Erstelle einen Passkey mit deinem Passwort-Manager oder Gerät (z. B. 1Password, iCloud-Schlüsselbund, Windows Hello). Du kannst mehrere Passkeys hinterlegen und hier wieder entfernen.',
        'default_names' => [
            'windows' => 'Windows-PC',
            'mac' => 'Mac',
            'iphone' => 'iPhone',
            'ipad' => 'iPad',
            'android' => 'Android-Gerät',
            'linux' => 'Linux-PC',
            'fallback' => 'Dieses Gerät',
        ],
    ],
];
