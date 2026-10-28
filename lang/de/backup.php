<?php

/**
 * Sicherungs-Seite und der ZIP-Export/-Import des Haushalts.
 */
return [
    'title' => 'Sicherung',
    'export' => 'Backup herunterladen',
    'import' => 'Backup einspielen',
    'import_heading' => 'Backup einspielen und alle Daten ersetzen?',
    'import_description' => 'Die dauerhaften Anwendungsdaten einschließlich Benutzerkonten, Passkeys, Einstellungen, Budgets und Monatsabschlüssen werden durch die Sicherung ersetzt. Danach meldest du dich mit einem Benutzerkonto aus der Sicherung neu an. Alte Haushalts-Backups lassen Benutzer und Einstellungen unverändert.',
    'file' => 'Backup-Datei (.zip)',
    'imported' => 'Backup eingespielt. Alle Daten wurden ersetzt.',
    'imported_accounts' => 'Vollständige Sicherung eingespielt. Bitte mit einem wiederhergestellten Benutzerkonto neu anmelden.',
    'imported_legacy' => 'Alte Haushaltssicherung eingespielt. Benutzerkonten und Systemeinstellungen wurden beibehalten.',
    'import_failed' => 'Import fehlgeschlagen',
    'view' => [
        'title' => 'Backup & Umzug',
        'intro' => 'Die Sicherung enthält <b>Teilnehmer, Budgets, Einkünfte, Kosten, Monatsabschlüsse, Benutzerkonten mit Passwort-Hashes, Passkeys, Einladungen, Systemeinstellungen und persönliche Dashboard-Anordnungen</b>. Beim Wiederherstellen werden alle zugehörigen Daten ersetzt. Prüfsummen erkennen beschädigte oder veränderte Datenbestände vor dem Import.',
        'users' => 'Nach einer vollständigen Wiederherstellung gelten die Benutzerkonten und Passwörter aus der Sicherung. Bestehende Anmeldungen und Passwort-Reset-Links werden verworfen. Die ZIP-Datei enthält sensible Kontodaten: sicher und privat aufbewahren.',
    ],
    'scope' => [
        'limits' => 'Die Sicherung stellt Anwendungsdaten in einer kompatiblen SplitNest-Installation wieder her. Serverkonfiguration und Zugangsdaten aus .env, etwa Datenbank- und SMTP-Verbindungen, müssen für die Zielinstallation eingerichtet werden. Sitzungen, Caches, Warteschlangen und temporäre Uploads sind nicht enthalten. Passkeys bleiben an ihre ursprüngliche Domain gebunden; bei einem Domainwechsel mit Passwort anmelden und Passkeys neu registrieren.',
        'legacy' => 'Backups älterer Versionen bleiben importierbar, enthalten aber nur die damals gesicherten Haushaltsdaten. Nicht enthaltene Benutzer und Einstellungen können daraus nicht wiederhergestellt werden; vorhandene bleiben bestehen.',
    ],
    'errors' => [
        'export_failed' => 'Die Sicherung konnte nicht vollständig erstellt werden. Bitte Speicherplatz und Schreibrechte prüfen.',
        'unsupported_format' => 'Die Datei ist keine unterstützte SplitNest-Sicherung oder ihr Manifest ist beschädigt.',
        'checksum' => 'Der Datenbestand „:table“ ist beschädigt oder wurde nach der Sicherung verändert.',
        'restore_failed' => 'Die Sicherung ist nicht mit dem aktuellen Datenbankschema kompatibel oder enthält ungültige Daten. Die bisherigen Daten wurden nicht ersetzt.',
        'no_admin' => 'Diese Sicherung enthält Benutzerkonten, aber keinen Administrator. Der Import wurde zum Schutz des Zugangs abgebrochen.',

        'not_zip' => 'Die Datei ist kein lesbares ZIP-Archiv.',
        'missing_table' => 'In der Datei fehlt der Datenbestand „:table" oder er ist beschädigt.',
        'invalid_rows' => 'Der Datenbestand „:table" enthält ungültige Zeilen.',
    ],
];
