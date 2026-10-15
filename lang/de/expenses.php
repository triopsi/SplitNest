<?php

/**
 * Kosten-Ressource: Formular, Tabelle, Aufteilungsmodi und Modell-Validierung.
 */
return [
    'label' => 'Kostenposition',
    'plural' => 'Kosten',
    'sections' => [
        'what' => 'Was wird gezahlt?',
        'what_desc' => 'Wofür ist die Kostenposition und wer bezahlt die Rechnung?',
        'schedule' => 'Wie viel und wie oft?',
        'schedule_desc' => 'Betrag, Rhythmus und Zeitraum der Kostenposition.',
        'split' => 'Wer trägt die Kosten?',
        'split_desc' => 'Lege fest, wie der Betrag auf die Teilnehmer verteilt wird.',
    ],
    'fields' => [
        'name' => 'Bezeichnung',
        'name_placeholder' => 'z. B. Miete, Strom, Spotify',
        'name_hint' => 'Wofür ist die Ausgabe? Ein kurzer Name genügt, z. B. „Miete" oder „Internet".',
        'payer' => 'Bezahlt von',
        'payer_hint' => 'Wer bezahlt die Rechnung bzw. von wessen Konto geht das Geld ab? Beispiel: Daniel überweist die Miete → „Daniel" auswählen.',
        'split_mode' => 'Aufteilung',
        'split_hint' => 'Wie wird der Betrag aufgeteilt? Beispiele für 100 €: Feste Prozente — ihr legt die Anteile selbst fest, z. B. Daniel 60 %, Svenja 40 %. Nach Einkommen — wer mehr verdient, zahlt mehr: bei 3.000 € und 2.000 € Einkommen zahlt Daniel 60 €, Svenja 40 €. Gleiche Anteile — jede Person zahlt gleich viel, also je 50 €. Persönlich — eine Person trägt alles, z. B. Svenjas Handyvertrag.',
        'shares' => 'Personen',
        'add_person' => 'Weitere Person hinzufügen',
        'participant' => 'Teilnehmer',
        'percentage' => 'Anteil',
        'percentage_hint' => 'Anteil dieser Person in Prozent, z. B. 60. Alle Anteile zusammen müssen genau 100 % ergeben.',
        'notes' => 'Notizen',
        'notes_hint' => 'Optional, z. B. Vertragslaufzeit, Kündigungsfrist oder wer die Lastschrift eingerichtet hat.',
    ],
    'share_hints' => [
        'percentage' => 'Wähle alle Personen aus, die sich beteiligen, und lege ihre Anteile in Prozent fest. Zusammen muss es genau 100 % ergeben, z. B. 60 % + 40 %.',
        'income' => 'Wähle alle Personen aus, die sich beteiligen. Die Anteile berechnen sich automatisch aus den Einkünften: Wer mehr verdient, trägt mehr.',
        'equal' => 'Wähle alle Personen aus, die sich beteiligen. Der Betrag wird gleichmäßig auf alle verteilt.',
        'personal' => 'Diese eine Person trägt die Kosten allein — voreingestellt ist der Bezahler.',
        'default' => 'Wer beteiligt sich an den Kosten?',
    ],
    'modes' => [
        'percentage' => 'Feste Prozente',
        'income' => 'Nach Einkommen',
        'equal' => 'Gleiche Anteile',
        'personal' => 'Persönlich (100 %)',
    ],
    'table' => [
        'name' => 'Bezeichnung',
        'payer' => 'Bezahler',
        'amount' => 'Betrag',
        'next_billing' => 'Nächste Abrechnung',
        'split_mode' => 'Aufteilung',
        'from' => 'Ab',
        'until' => 'Bis',
    ],
    'filters' => [
        'payer' => 'Bezahler',
        'split_mode' => 'Aufteilung',
    ],
    'validation' => [
        'personal_one' => 'Persönliche Kosten brauchen genau einen Teilnehmer.',
        'max_100' => 'Ein Anteil darf höchstens 100 % betragen.',
        'sum_100' => 'Die Anteile müssen zusammen genau 100 % ergeben.',
    ],
];
