<?php

/**
 * Teilnehmer-Ressource, Löschschutz und Teilnehmer-Detailseite.
 */
return [
    'label' => 'Teilnehmer',
    'fields' => [
        'name' => 'Name',
        'email' => 'E-Mail (für spätere Benachrichtigungen)',
        'notes' => 'Notizen',
    ],
    'table' => [
        'name' => 'Name',
        'email' => 'E-Mail',
        'no_email' => 'Noch nicht hinterlegt',
    ],
    'delete_protected' => 'Teilnehmer bleiben für historische Zuordnungen erhalten.',
    'detail' => [
        'kicker' => 'Teilnehmer-Detail',
        'intro' => 'Alle Einkünfte und Kostenanteile, die :name in diesem Monat betreffen.',
        'back' => 'Zurück zur Monatsübersicht',
        'income' => 'Einkünfte',
        'income_hint' => 'Geplante Einnahmen in diesem Monat.',
        'share' => 'Eigener Kostenanteil',
        'share_hint' => 'Anteil an den Monatskosten laut Aufteilungsregeln.',
        'paid' => 'Rechnungen bezahlt',
        'paid_hint' => 'Rechnungen, die :name bezahlt hat.',
        'balance' => 'Ausgleich',
        'balance_hint' => 'Positiv = erhält Geld, negativ = zahlt Geld.',
        'left' => 'Übrig',
        'left_hint' => 'Einkommen minus eigener Kostenanteil.',
        'costs_title' => 'Kosten im :month',
        'costs_count' => ':count Posten betreffen :name',
        'item' => 'Posten',
        'payer' => 'Bezahlt von',
        'rule' => 'Regel',
        'total' => 'Gesamt',
        'share_of' => 'Anteil :name',
        'costs_empty' => 'Keine Kosten in diesem Monat.',
        'incomes_title' => 'Einkünfte im :month',
        'incomes_count' => ':count Positionen',
        'income_name' => 'Bezeichnung',
        'amount' => 'Betrag',
        'incomes_empty' => 'Keine Einkünfte in diesem Monat.',
    ],
];
