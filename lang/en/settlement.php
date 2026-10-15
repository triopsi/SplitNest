<?php

/**
 * Monthly overview page, month closing/reopening and settlement calculation.
 */
return [
    'title' => 'Monthly overview',
    'hero' => [
        'kicker' => 'Plan together · Split fairly',
        'title' => 'One month. Everything at a glance.',
        'text' => 'Incomes, costs and the payments that balance your household.',
    ],
    'picker' => [
        'month' => 'Settlement month',
        'year' => 'Year',
    ],
    'months' => ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'],
    'manage_participants' => 'Manage participants',
    'closed_badge' => 'Closed on :date',
    'open_badge' => 'Open monthly plan :month',
    'pdf_button' => 'Statement as PDF',
    'close_hint' => '"Close month" freezes incomes, costs and their split for this month: the closing is immutable afterwards and the PDF statement stays identical when entries change later.',
    'planned_note' => 'Planned payments, not bank transactions. Transfers are not tracked as paid yet.',
    'cards' => [
        'income' => 'Incomes combined',
        'income_hint' => 'All incomes planned for this month.',
        'costs' => 'Costs combined',
        'costs_hint' => 'All cost items due in this month.',
        'left' => 'Left after all costs',
        'left_hint' => 'Incomes minus costs — your freely available remainder.',
    ],
    'transfers' => [
        'title' => 'These amounts are due',
        'count' => ':count balancing transfers',
        'to' => 'to',
        'reference' => 'Household share :month',
        'balanced' => 'The household is balanced. No balancing transfer is needed.',
    ],
    'overview' => [
        'title' => 'Your personal overview',
        'participant' => 'Participant',
        'income' => 'Incomes',
        'share' => 'Own cost share',
        'share_pct' => 'Share of costs',
        'paid' => 'Bills paid*',
        'balance' => 'Balance**',
        'left' => 'Left',
        'footnote' => 'Share of costs = percentage of the shared cost pool. * According to the planned payer. ** Positive = receives money, negative = pays money. Left = income minus own cost share.',
    ],
    'costs' => [
        'title' => 'How the costs are split',
        'manage' => 'Manage costs',
        'item' => 'Item',
        'payer' => 'Payer',
        'rule' => 'Rule',
        'total' => 'Total',
        'empty' => 'No costs recorded for this month yet.',
    ],
    'close_action' => [
        'label' => 'Close month',
        'heading' => 'Save the statement permanently?',
        'description' => 'The planned bills and their split are frozen. The closing can also be created at the beginning of the month. The PDF remains unchanged by later edits.',
        'success' => 'Month closed. The PDF is ready.',
        'error' => 'Closing not possible',
    ],
    'reopen_action' => [
        'label' => 'Reopen month',
        'heading' => 'Reopen the closing?',
        'description' => 'The frozen state is discarded and the previous PDF statement is no longer available. Afterwards the month can be edited and closed again.',
        'success' => 'Month reopened.',
        'error' => 'Reopening not possible',
    ],
    'closing' => [
        'immutable' => 'This month is closed and immutable.',
        'no_delete' => 'Monthly closings must not be deleted.',
        'not_closed' => 'This month is not closed.',
    ],
    'calculator' => [
        'no_participants' => 'Please create participants first.',
        'missing_income' => 'For ":name" all assigned participants are missing income. Please add incomes or choose a different split.',
        'invalid_amount' => 'Invalid amount or weighting.',
    ],
    'shares_chart' => [
        'heading' => 'Cost shares',
        'description' => 'Who carries how much of the costs in this month?',
        'dataset' => 'Cost share in €',
    ],
];
