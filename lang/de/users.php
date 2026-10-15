<?php

/**
 * Benutzerverwaltung, E-Mail-Einladungen, Ersteinrichtung und das
 * splitnest:user-Kommando.
 */
return [
    'label' => 'Benutzer',
    'plural' => 'Benutzer',
    'menu' => 'Benutzer',
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
];
