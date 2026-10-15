<?php

/**
 * E-Mails: Abschluss-Zusammenfassung und Haushalts-Einladung.
 */
return [
    'closing_subject' => ':app Abrechnung :month',
    'default_body' => "Hallo {name},\n\ndie Abrechnung für {monat} ist fertig und hängt als PDF an.\n\nDeine Ausgleichszahlungen:\n{ueberweisungen}\n\nViele Grüße\nSplitNest",
    'no_transfers' => 'Keine — du bist ausgeglichen.',
    'transfer_line' => '- :amount an :name',
    'invitation_subject' => ':app – Einladung in den Haushalt',
    'invitation_body' => "Hallo!\n\nDu wurdest eingeladen, im Haushalt bei :app mitzuarbeiten.\n\nLege über diesen Link dein Konto an:\n:url\n\nDer Link ist :hours Stunden gültig und kann nur einmal verwendet werden. Danach muss dich ein Administrator erneut einladen.\n\nWenn du diese Einladung nicht erwartet hast, kannst du diese E-Mail einfach ignorieren.",
];
