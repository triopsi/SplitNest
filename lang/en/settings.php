<?php

/**
 * System settings page.
 */
return [
    'title' => 'Settings',
    'save' => 'Save',
    'avatars' => 'Avatars',
    'avatars_desc' => 'Show profile pictures based on the recorded e-mail address.',
    'gravatar' => 'Use Gravatar',
    'gravatar_hint' => 'When active, Gravatar images appear for the logged-in user at the top right and everywhere participants with an e-mail address are mentioned. The images are loaded from gravatar.com.',
    'gravatar_style' => 'Fallback style',
    'gravatar_style_hint' => 'Which picture Gravatar shows when no account image exists.',
    'gravatar_styles' => [
        'initials' => 'Initials',
        'identicon' => 'Identicon',
        'monsterid' => 'MonsterID',
        'wavatar' => 'Wavatar',
        'retro' => 'Retro',
        'robohash' => 'Robohash',
        'mp' => 'Mystery person',
        'blank' => 'Blank',
    ],
    'closing_mail' => 'Closing e-mail',
    'mail_body' => 'E-mail text',
    'mail_body_hint' => 'When a month is closed, all participants with an e-mail address receive the statement as a PDF and their payment plan by e-mail. Placeholders: {name} = participant name, {monat} = settlement month, {ueberweisungen} = list of the participant\'s payments.',
    'saved' => 'Settings saved.',
];
