<?php

/**
 * E-mails: closing summary and household invitation.
 */
return [
    'closing_subject' => ':app statement :month',
    'default_body' => "Hello {name},\n\nthe statement for {monat} is ready and attached as a PDF.\n\nYour balancing payments:\n{ueberweisungen}\n\nBest regards\nSplitNest",
    'no_transfers' => 'None — you are even.',
    'transfer_line' => '- :amount to :name',
    'invitation_subject' => ':app – Invitation to the household',
    'invitation_body' => "Hello!\n\nYou have been invited to join a household at :app.\n\nCreate your account via this link:\n:url\n\nThe link is valid for :hours hours and can only be used once. After that an administrator has to invite you again.\n\nIf you did not expect this invitation, you can simply ignore this e-mail.",
];
