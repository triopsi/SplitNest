<?php

/**
 * Backup page and the household ZIP export/import.
 */
return [
    'title' => 'Backup',
    'export' => 'Download backup',
    'import' => 'Restore backup',
    'import_heading' => 'Restore backup and replace all data?',
    'import_description' => 'All currently saved participants, incomes, expenses and monthly closings are replaced by the content of the file. This cannot be undone.',
    'file' => 'Backup file (.zip)',
    'imported' => 'Backup restored. All data has been replaced.',
    'import_failed' => 'Import failed',
    'view' => [
        'title' => 'Backup & moving',
        'intro' => 'The export downloads <b>participants, incomes, expenses and monthly closings</b> as a ZIP file. Keep the file as a backup or restore it in another SplitNest instance — there it replaces the entire dataset.',
        'users' => 'User accounts are not transferred for security reasons. On the target instance the first administrator registers through the web form, further users are invited by e-mail.',
    ],
    'errors' => [
        'not_zip' => 'The file is not a readable ZIP archive.',
        'missing_table' => 'The file is missing the dataset ":table" or it is damaged.',
        'invalid_rows' => 'The dataset ":table" contains invalid rows.',
    ],
];
