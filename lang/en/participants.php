<?php

/**
 * Participant resource, model protection and the participant detail page.
 */
return [
    'label' => 'Participant',
    'fields' => [
        'name' => 'Name',
        'email' => 'E-mail (for later notifications)',
        'notes' => 'Notes',
    ],
    'table' => [
        'name' => 'Name',
        'email' => 'E-mail',
        'no_email' => 'Not recorded yet',
    ],
    'delete_protected' => 'Participants are kept for historical assignments.',
    'detail' => [
        'kicker' => 'Participant detail',
        'intro' => 'All incomes and cost shares affecting :name this month.',
        'back' => 'Back to the monthly overview',
        'income' => 'Incomes',
        'income_hint' => 'Planned earnings this month.',
        'share' => 'Own cost share',
        'share_hint' => 'Share of the monthly costs according to the split rules.',
        'paid' => 'Bills paid',
        'paid_hint' => 'Bills :name has paid.',
        'balance' => 'Balance',
        'balance_hint' => 'Positive = receives money, negative = pays money.',
        'left' => 'Left',
        'left_hint' => 'Income minus own cost share.',
        'costs_title' => 'Costs in :month',
        'costs_count' => ':count items affect :name',
        'item' => 'Item',
        'payer' => 'Paid by',
        'rule' => 'Rule',
        'total' => 'Total',
        'share_of' => 'Share :name',
        'costs_empty' => 'No costs this month.',
        'incomes_title' => 'Incomes in :month',
        'incomes_count' => ':count items',
        'income_name' => 'Name',
        'amount' => 'Amount',
        'incomes_empty' => 'No incomes this month.',
    ],
];
