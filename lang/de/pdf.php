<?php

/**
 * PDF-Abrechnung eines abgeschlossenen Monats.
 */
return [
    'personal' => [
        'title' => 'Deine Monatsabrechnung :month',
        'scope' => 'Diese Abrechnung enthält nur deine eigenen Kosten, Beiträge und Überweisungen.',
        'to_pay' => 'Von dir insgesamt zu überweisen',
        'to_receive' => 'Für dich vorgesehene Zahlungseingänge',
        'payments' => 'Deine Überweisungen',
        'no_payments' => 'Für dich sind in diesem Monat keine Überweisungen vorgesehen.',
        'costs' => 'Deine Kosten und Beiträge',
        'kind' => 'Art',
        'your_amount' => 'Dein Betrag',
        'share' => 'Eigener Kostenanteil',
        'contribution' => 'Beitrag / Weiterleitung',
        'no_costs' => 'Für dich sind in diesem Monat keine Kosten oder Beiträge hinterlegt.',
        'note' => 'Beträge in EUR. Die Kosten zeigen deinen eigenen Anteil, nicht den Gesamtpreis einer Rechnung. Oben stehen die geplanten Überweisungen; bereits selbst bezahlte Rechnungen können den Ausgleich mindern. Beiträge und Weiterleitungen sind in den Überweisungen enthalten und werden nicht nochmals addiert. Dies ist keine Bestätigung einer Bankzahlung.',
    ],
    'title' => 'Haushaltsabrechnung :month',
    'heading' => 'Abrechnung :month',
    'saved_on' => 'Verbindlicher Monatsplan · gespeichert am :date',
    'incomes' => 'Einkünfte',
    'costs' => 'Kosten',
    'left' => 'Übrig',
    'transfers_title' => 'Diese Beträge bitte überweisen',
    'to' => 'an',
    'reference' => 'Verwendungszweck: Haushaltsanteil :month',
    'no_transfers' => 'Es sind keine Ausgleichszahlungen erforderlich.',
    'overview_title' => 'Persönliche Übersicht',
    'participant' => 'Teilnehmer',
    'own_share' => 'Eigener Anteil',
    'paid' => 'Bezahlt*',
    'balance' => 'Ausgleich**',
    'footnote' => '* Laut geplantem Bezahler, keine Bestätigung einer Bankzahlung. ** Positiv: erhält Geld. Negativ: zahlt Geld.',
    'costs_title' => 'Kosten und Aufteilung',
    'payer' => 'Bezahler',
    'incomes_title' => 'Berücksichtigte Einkünfte',
    'income' => 'Einkunft',
    'amount' => 'Betrag',
    'footer' => 'Beträge in EUR. Aufteilung pro Kostenposition auf ganze Cent, Restcents nach größtem Nachkommaanteil (bei Gleichstand nach Teilnehmer-ID). Der gespeicherte Stand wird durch spätere Änderungen an Stammdaten nicht verändert.',
];
