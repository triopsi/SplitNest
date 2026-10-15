<?php

/**
 * Sicherungs-Seite und der ZIP-Export/-Import des Haushalts.
 */
return [
    'title' => 'Sicherung',
    'export' => 'Backup herunterladen',
    'import' => 'Backup einspielen',
    'import_heading' => 'Backup einspielen und alle Daten ersetzen?',
    'import_description' => 'Alle aktuell gespeicherten Teilnehmer, Einkünfte, Kosten und Monatsabschlüsse werden durch den Inhalt der Datei ersetzt. Das kann nicht rückgängig gemacht werden.',
    'file' => 'Backup-Datei (.zip)',
    'imported' => 'Backup eingespielt. Alle Daten wurden ersetzt.',
    'import_failed' => 'Import fehlgeschlagen',
    'view' => [
        'title' => 'Backup & Umzug',
        'intro' => 'Der Export lädt <b>Teilnehmer, Einkünfte, Kosten und Monatsabschlüsse</b> als ZIP-Datei herunter. Bewahre die Datei als Sicherung auf oder spiele sie in einer anderen SplitNest-Instanz wieder ein — dort ersetzt sie den kompletten Datenbestand.',
        'users' => 'Benutzerkonten werden aus Sicherheitsgründen nicht übertragen. In der Zielinstanz registriert sich der erste Administrator über das Webformular, weitere Benutzer werden per E-Mail eingeladen.',
    ],
    'errors' => [
        'not_zip' => 'Die Datei ist kein lesbares ZIP-Archiv.',
        'missing_table' => 'In der Datei fehlt der Datenbestand „:table" oder er ist beschädigt.',
        'invalid_rows' => 'Der Datenbestand „:table" enthält ungültige Zeilen.',
    ],
];
