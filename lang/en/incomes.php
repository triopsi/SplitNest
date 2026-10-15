<?php

/**
 * Income resource: form and table.
 */
return [
    'label' => 'Income',
    'plural' => 'Incomes',
    'fields' => [
        'name' => 'Name',
        'name_placeholder' => 'Net salary',
        'participant' => 'Participant',
    ],
    'table' => [
        'name' => 'Name',
        'participant' => 'Participant',
        'amount' => 'Amount',
        'from' => 'From',
        'until' => 'To',
        'interval' => 'Interval',
    ],
];
