<?php

/**
 * E-mails: closing summary, household invitation and password reset.
 */
return [
    'closing_subject' => ':app statement :month',
    'closing_heading' => 'Statement :month',
    'closing_attachment' => ':app-Statement-:month.pdf',
    'pdf_note' => 'The complete statement is attached as a PDF.',
    'default_body' => "Hello {name},\n\nthe statement for {monat} is ready and attached as a PDF.\n\nYour balancing payments:\n{ueberweisungen}\n\nBest regards\nSplitNest",
    'no_transfers' => 'None — you are even.',
    'transfer_line' => '- :amount to :name',
    'password_reset' => [
        'subject' => ':app – Reset your password',
        'title' => 'Reset your password',
        'intro' => 'We received a request to reset the password for your :app account. Choose a new password using the button below:',
        'button' => 'Reset password',
        'validity' => 'This link is valid for :minutes minutes and can only be used once.',
        'fallback' => 'If the button does not work, copy this link into your browser:',
        'ignore' => 'If you did not request a password reset, you can ignore this e-mail. Your password will remain unchanged.',
    ],
    'invitation' => [
        'subject' => ':app – Invitation to the household',
        'body' => "Hello!\n\nYou have been invited to join a household at :app.\n\nCreate your account via this link:\n:url\n\nThe link is valid for :hours hours and can only be used once. After that an administrator has to invite you again.\n\nIf you did not expect this invitation, you can simply ignore this e-mail.",
        'title' => 'Welcome to :app!',
        'intro' => 'You have been invited to join the household at :app. Create your account with one click:',
        'button' => 'Create account',
        'validity' => 'The link is valid for :hours hours and can only be used once. After that an administrator has to invite you again.',
        'fallback' => 'If the button does not work, copy this link into your browser:',
        'ignore' => 'If you did not expect this invitation, you can simply ignore this e-mail.',
    ],
];
