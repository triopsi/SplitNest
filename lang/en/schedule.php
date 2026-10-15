<?php

/**
 * Shared schedule fields (amount, interval, first/last month) used by the
 * expense and income forms, plus the table labels derived from them.
 */
return [
    'amount' => 'Amount in euros',
    'amount_hint' => 'Amount per occurrence, e.g. 89.99 or 1701.00. Comma or dot are allowed, but no thousands separators.',
    'interval' => 'Repeat',
    'interval_hint' => 'How often is the amount due? "Monthly" for recurring items like rent, "Yearly" e.g. for an annual statement, "One-off" for a single payment.',
    'intervals' => [
        'one_off' => 'One-off',
        'monthly' => 'Monthly',
        'every_3' => 'Every 3 months',
        'every_4' => 'Every 4 months',
        'half_yearly' => 'Half-yearly',
        'yearly' => 'Yearly',
    ],
    'start' => 'First month',
    'start_hint' => 'From this month on the entry counts in the settlement, e.g. 2026-11.',
    'end' => 'Last month (optional)',
    'end_hint' => 'This month still counts. Leave empty when the entry runs indefinitely. Tip: when the amount increases, end the old entry here and create a new one starting the following month.',
    'every_x_months' => 'Every :count months',
    'one_off' => 'One-off',
    'unlimited' => 'Unlimited',
];
