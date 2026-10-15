<?php

/**
 * Expense resource: form, table, split modes and model validation.
 */
return [
    'label' => 'Expense',
    'plural' => 'Costs',
    'sections' => [
        'what' => 'What is being paid?',
        'what_desc' => 'What is the expense for and who pays the bill?',
        'schedule' => 'How much and how often?',
        'schedule_desc' => 'Amount, rhythm and period of the expense.',
        'split' => 'Who bears the costs?',
        'split_desc' => 'Define how the amount is distributed across the participants.',
    ],
    'fields' => [
        'name' => 'Name',
        'name_placeholder' => 'e.g. rent, electricity, Spotify',
        'name_hint' => 'What is the expense for? A short name is enough, e.g. "Rent" or "Internet".',
        'payer' => 'Paid by',
        'payer_hint' => 'Who pays the bill, i.e. from whose account the money leaves? Example: Daniel transfers the rent → choose "Daniel".',
        'split_mode' => 'Split',
        'split_hint' => 'How is the amount split? Examples for 100 €: Fixed percentages — you set the shares yourself, e.g. Daniel 60 %, Svenja 40 %. Income-weighted — who earns more pays more: with 3,000 € and 2,000 € incomes Daniel pays 60 €, Svenja 40 €. Equal shares — every person pays the same, so 50 € each. Personal — one person bears everything, e.g. Svenja\'s phone contract.',
        'shares' => 'People',
        'add_person' => 'Add another person',
        'participant' => 'Participant',
        'percentage' => 'Share',
        'percentage_hint' => 'Share of this person in percent, e.g. 60. All shares together must total exactly 100 %.',
        'notes' => 'Notes',
        'notes_hint' => 'Optional, e.g. contract period, cancellation deadline or who set up the direct debit.',
    ],
    'share_hints' => [
        'percentage' => 'Choose everyone who participates and set their shares in percent. Together it must be exactly 100 %, e.g. 60 % + 40 %.',
        'income' => 'Choose everyone who participates. The shares are calculated automatically from the incomes: who earns more, bears more.',
        'equal' => 'Choose everyone who participates. The amount is distributed equally across all of them.',
        'personal' => 'This one person bears the costs alone — the payer is preset.',
        'default' => 'Who shares in the costs?',
    ],
    'modes' => [
        'percentage' => 'Fixed percentages',
        'income' => 'Income-weighted',
        'equal' => 'Equal shares',
        'personal' => 'Personal (100 %)',
    ],
    'table' => [
        'name' => 'Name',
        'payer' => 'Payer',
        'amount' => 'Amount',
        'next_billing' => 'Next billing',
        'split_mode' => 'Split',
        'from' => 'From',
        'until' => 'To',
    ],
    'filters' => [
        'payer' => 'Payer',
        'split_mode' => 'Split',
    ],
    'validation' => [
        'personal_one' => 'Personal costs need exactly one participant.',
        'max_100' => 'A share may not exceed 100 %.',
        'sum_100' => 'The shares must total exactly 100 %.',
    ],
];
