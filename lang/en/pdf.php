<?php

/**
 * PDF statement for a closed month.
 */
return [
    'personal' => [
        'title' => 'Your monthly statement :month',
        'scope' => 'This statement includes only your own costs, contributions and transfers.',
        'to_pay' => 'Total for you to transfer',
        'to_receive' => 'Payments planned for you to receive',
        'payments' => 'Your transfers',
        'no_payments' => 'No transfers are planned for you this month.',
        'costs' => 'Your costs and contributions',
        'kind' => 'Type',
        'your_amount' => 'Your amount',
        'share' => 'Own cost share',
        'contribution' => 'Contribution / forwarding',
        'no_costs' => 'No costs or contributions are recorded for you this month.',
        'note' => 'Amounts in EUR. Costs show your own share, not the full price of a bill. Planned transfers appear above; bills you paid yourself may reduce balancing amounts. Contributions and forwarding payments are already included in the transfers and must not be added again. This is not confirmation of a bank payment.',
    ],
    'title' => 'Household statement :month',
    'heading' => 'Statement :month',
    'saved_on' => 'Binding monthly plan · saved on :date',
    'incomes' => 'Incomes',
    'costs' => 'Costs',
    'left' => 'Left',
    'transfers_title' => 'Please transfer these amounts',
    'to' => 'to',
    'reference' => 'Reference: household share :month',
    'no_transfers' => 'No balancing transfers are required.',
    'overview_title' => 'Personal overview',
    'participant' => 'Participant',
    'own_share' => 'Own share',
    'paid' => 'Paid*',
    'balance' => 'Balance**',
    'footnote' => '* According to the planned payer, not a confirmation of a bank payment. ** Positive: receives money. Negative: pays money.',
    'costs_title' => 'Costs and split',
    'payer' => 'Payer',
    'incomes_title' => 'Incomes considered',
    'income' => 'Income',
    'amount' => 'Amount',
    'footer' => 'Amounts in EUR. Split per cost item in whole cents, remainder cents by largest fractional part (ties broken by participant ID). The saved state is not changed by later edits to master data.',
];
