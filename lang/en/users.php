<?php

/**
 * User management, e-mail invitations, first-run registration and the
 * splitnest:user console command.
 */
return [
    'label' => 'User',
    'plural' => 'Users',
    'menu' => 'Users',
    'fields' => [
        'name' => 'Name',
        'email' => 'E-mail',
        'password' => 'Password',
        'password_hint' => 'At least 12 characters. Leave empty when editing to keep the password.',
        'is_admin' => 'Administrator',
        'is_admin_hint' => 'Only administrators may log in and manage the household.',
    ],
    'table' => [
        'name' => 'Name',
        'email' => 'E-mail',
        'admin' => 'Admin',
        'created' => 'Created',
    ],
    'invite' => [
        'action' => 'Invite by e-mail',
        'success' => 'Invitation sent. The link is valid for :hours hours.',
        'error' => 'Invitation not possible',
        'account_exists' => 'An account already exists for this e-mail address.',
    ],
    'first_run' => [
        'title' => 'Initial setup',
        'heading' => 'Create the first administrator',
        'done' => 'The initial setup is already complete.',
        'password_hint' => 'At least 12 characters.',
    ],
    'invitation' => [
        'title' => 'Accept invitation',
        'heading' => 'Create your household account',
        'email_hint' => 'The invitation is for this e-mail address.',
        'invalid' => 'This invitation is invalid, has expired or has already been used.',
        'password_hint' => 'At least 12 characters.',
    ],
    'command' => [
        'description' => 'Creates a household administrator (the password is asked hidden).',
        'name_option' => 'Display name',
        'email' => 'E-mail address',
        'name' => 'Name',
        'password' => 'Password (at least 12 characters)',
        'password_repeat' => 'Repeat password',
        'success' => 'Login for :email created. This user may manage the whole household.',
    ],
];
