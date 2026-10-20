<?php

/**
 * E-Mails: Abschluss-Zusammenfassung, Haushalts-Einladung und Passwort-Zurücksetzung.
 */
return [
    'closing_subject' => ':app Abrechnung :month',
    'closing_heading' => 'Abrechnung :month',
    'closing_attachment' => ':app-Abrechnung-:month.pdf',
    'pdf_note' => 'Die komplette Abrechnung hängt als PDF an.',
    'default_body' => "Hallo {name},\n\ndie Abrechnung für {monat} ist fertig und hängt als PDF an.\n\nDeine Ausgleichszahlungen:\n{ueberweisungen}\n\nViele Grüße\nSplitNest",
    'no_transfers' => 'Keine — du bist ausgeglichen.',
    'transfer_line' => '- :amount an :name',
    'password_reset' => [
        'subject' => ':app – Passwort zurücksetzen',
        'title' => 'Passwort zurücksetzen',
        'intro' => 'Wir haben eine Anfrage erhalten, das Passwort für dein Konto bei :app zurückzusetzen. Über den folgenden Button kannst du ein neues Passwort festlegen:',
        'button' => 'Passwort zurücksetzen',
        'validity' => 'Dieser Link ist :minutes Minuten gültig und kann nur einmal verwendet werden.',
        'fallback' => 'Wenn der Button nicht funktioniert, kopiere diesen Link in deinen Browser:',
        'ignore' => 'Wenn du kein neues Passwort angefordert hast, kannst du diese E-Mail ignorieren. Dein Passwort bleibt unverändert.',
    ],
    'invitation' => [
        'subject' => ':app – Einladung in den Haushalt',
        'body' => "Hallo!\n\nDu wurdest eingeladen, im Haushalt bei :app mitzuarbeiten.\n\nLege über diesen Link dein Konto an:\n:url\n\nDer Link ist :hours Stunden gültig und kann nur einmal verwendet werden. Danach muss dich ein Administrator erneut einladen.\n\nWenn du diese Einladung nicht erwartet hast, kannst du diese E-Mail einfach ignorieren.",
        'title' => 'Willkommen bei :app!',
        'intro' => 'Du wurdest eingeladen, im Haushalt bei :app mitzuarbeiten. Lege dein Konto mit einem Klick an:',
        'button' => 'Konto anlegen',
        'validity' => 'Der Link ist :hours Stunden gültig und kann nur einmal verwendet werden. Danach muss dich ein Administrator erneut einladen.',
        'fallback' => 'Wenn der Button nicht funktioniert, kopiere diesen Link in deinen Browser:',
        'ignore' => 'Wenn du diese Einladung nicht erwartet hast, kannst du diese E-Mail einfach ignorieren.',
    ],
];
