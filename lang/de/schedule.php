<?php

/**
 * Geteilte Zeitplan-Felder (Betrag, Intervall, erster/letzter Monat) der
 * Kosten- und Einkünfte-Formulare, plus davon abgeleitete Tabellen-Texte.
 */
return [
    'amount' => 'Betrag in Euro',
    'amount_hint' => 'Betrag pro Fälligkeit, z. B. 89,99 oder 1701,00. Komma oder Punkt sind erlaubt, aber keine Tausenderpunkte.',
    'interval' => 'Wiederholung',
    'interval_hint' => 'Wie oft fällt der Betrag an? „Monatlich" für laufende Posten wie die Miete, „Jährlich" z. B. für eine Jahresabrechnung, „Einmalig" für eine einzelne Zahlung.',
    'intervals' => [
        'one_off' => 'Einmalig',
        'monthly' => 'Monatlich',
        'every_3' => 'Alle 3 Monate',
        'every_4' => 'Alle 4 Monate',
        'half_yearly' => 'Halbjährlich',
        'yearly' => 'Jährlich',
    ],
    'start' => 'Erster Monat',
    'start_hint' => 'Ab diesem Monat zählt der Eintrag in der Abrechnung, z. B. 2026-11.',
    'end' => 'Letzter Monat (optional)',
    'end_hint' => 'Dieser Monat zählt noch mit. Leer lassen, wenn der Eintrag unbegrenzt läuft. Tipp: Bei einer Erhöhung den alten Eintrag hier beenden und einen neuen ab dem Folgemonat anlegen.',
    'every_x_months' => 'Alle :count Monate',
    'one_off' => 'Einmalig',
    'unlimited' => 'Unbegrenzt',
];
