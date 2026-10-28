<?php

/**
 * Income resource: form and table.
 */
return [
    'label' => 'Income',
    'plural' => 'Incomes',
    'help' => [
        'title' => 'How to read your household incomes',
        'intro' => 'This list contains receipts for everyone in your household, not just the money you receive yourself. “Recipient” identifies the person who holds the money for the settlement.',
        'recipient_title' => 'Recipient does not mean sender',
        'recipient_text' => 'For net salary, the assigned person receives that salary. The entry does not represent a transfer between participants. You can include the original source in the name, for example “Salary from employer” or “Forwarded childcare subsidy”.',
        'forwarding_title' => 'Record money already forwarded only once',
        'forwarding_text' => 'When a childcare subsidy is forwarded to another participant, record the receipt once at its final recipient. Select the forwarding person as payer. The subsidy must not also be included in that person’s allocation income. Enable the transfer-plan option if the forwarding payment should appear there. Leave it disabled when handled separately.',
        'payments_title' => 'Payer and recipient in the transfer plan',
        'payments_text' => 'For forwarded money, select the forwarding person as payer and the person who will use the money as recipient. For a contribution from outside the household, enter an external payer. Enable “Also show this payment in the transfer plan” to plan these amounts alongside household balancing. They remain the same receipts in the calculation and are never counted twice.',
        'allocation_title' => 'Distinguish salary, budgets and reimbursements',
        'allocation_text' => 'The “Include in cost allocation income” check determines your income-based ratio. Assign child benefit and the childcare subsidy to the same child budget with this check off; assign the related expenses to that budget. For a Netflix contribution, switch the check off and select the Netflix bill under “Reimbursement for”. Only uncovered costs are split using the selected rule.',
    ],
    'fields' => [
        'name' => 'Name',
        'name_placeholder' => 'Net salary',
        'participant' => 'Recipient',
        'participant_hint' => 'Who should receive and use this money in the monthly plan? Record a forwarding payment only once at its final recipient. If needed, specify its payer and include the payment in the transfer plan.',
    ],
    'table' => [
        'name' => 'Name',
        'participant' => 'Recipient',
        'amount' => 'Amount',
        'from' => 'From',
        'until' => 'To',
        'interval' => 'Interval',
    ],
];
