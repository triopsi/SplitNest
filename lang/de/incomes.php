<?php

/**
 * Einkünfte-Ressource: Formular und Tabelle.
 */
return [
    'label' => 'Einkunft',
    'plural' => 'Einkünfte',
    'help' => [
        'title' => 'So liest du eure Einkünfte',
        'intro' => 'Hier stehen die Einnahmen aller Personen in eurem Haushalt. Die Tabelle zeigt nicht nur das Geld, das du selbst bekommst. „Empfänger“ bedeutet: Bei dieser Person steht das Geld für die Abrechnung zur Verfügung.',
        'recipient_title' => 'Empfänger ist nicht Absender',
        'recipient_text' => 'Bei einem Nettogehalt ist die zugeordnete Person der Empfänger des Gehalts. Der Eintrag bedeutet keine Überweisung zwischen Teilnehmern. Die ursprüngliche Quelle lässt sich in der Bezeichnung ergänzen, zum Beispiel „Gehalt vom Arbeitgeber“ oder „Weitergeleiteter Kita-Zuschuss“.',
        'forwarding_title' => 'Bereits weitergeleitetes Geld nur einmal erfassen',
        'forwarding_text' => 'Wird ein Kita-Zuschuss an einen anderen Teilnehmer weitergeleitet, erfasst ihr die Einnahme einmal beim endgültigen Empfänger. Als Zahler wählt ihr die weiterleitende Person. Der Zuschuss darf nicht zusätzlich in deren Verteilungseinkommen enthalten sein. Aktiviert die Überweisungsplan-Option, wenn die Weiterleitung dort erscheinen soll. Wird sie separat erledigt, lasst die Option deaktiviert.',
        'payments_title' => 'Zahler und Empfänger für den Überweisungsplan',
        'payments_text' => 'Bei einer Weiterleitung wählt ihr die weiterleitende Person als Zahler und die Person, die das Geld verwenden soll, als Empfänger. Bei einem Beitrag von außerhalb des Haushalts gebt ihr einen externen Zahler an. Mit „Zahlung zusätzlich im Überweisungsplan anzeigen“ werden diese Beträge neben dem Haushaltsausgleich eingeplant. Für die Berechnung bleiben es dieselben Einnahmen; sie werden nicht doppelt gezählt.',
        'allocation_title' => 'Gehalt, Budget und Erstattung unterscheiden',
        'allocation_text' => 'Der Haken bei „Für die Kostenaufteilung berücksichtigen“ bestimmt eure einkommensabhängige Verteilungsquote. Kindergeld und Kita-Zuschuss gehören ohne Haken ins gemeinsame Kinderbudget; die zugehörigen Ausgaben verwenden dieses Budget. Ein Netflix-Beitrag wird ohne Haken unter „Erstattung für“ der Netflix-Rechnung zugeordnet. Nur ungedeckte Kosten werden nach der gewählten Regel verteilt.',
    ],
    'fields' => [
        'name' => 'Bezeichnung',
        'name_placeholder' => 'Nettogehalt',
        'participant' => 'Empfänger',
        'participant_hint' => 'Wer soll das Geld in diesem Monatsplan erhalten und verwenden? Eine Weiterleitung nur einmal beim endgültigen Empfänger erfassen. Bei Bedarf den Zahler angeben und die Zahlung zusätzlich im Überweisungsplan anzeigen lassen.',
    ],
    'table' => [
        'name' => 'Bezeichnung',
        'participant' => 'Empfänger',
        'amount' => 'Betrag',
        'from' => 'Ab',
        'until' => 'Bis',
        'interval' => 'Intervall',
    ],
];
