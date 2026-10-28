<?php

/**
 * Backup page and the household ZIP export/import.
 */
return [
    'title' => 'Backup',
    'export' => 'Download backup',
    'import' => 'Restore backup',
    'import_heading' => 'Restore backup and replace all data?',
    'import_description' => 'Durable application data, including accounts, passkeys, settings, budgets and monthly closings, will be replaced by the backup. Afterwards, sign in again with an account from the backup. Legacy household backups leave accounts and settings unchanged.',
    'file' => 'Backup file (.zip)',
    'imported' => 'Backup restored. All data has been replaced.',
    'imported_accounts' => 'Complete backup restored. Please sign in again with a restored account.',
    'imported_legacy' => 'Legacy household backup restored. Accounts and system settings were preserved.',
    'import_failed' => 'Import failed',
    'view' => [
        'title' => 'Backup & moving',
        'intro' => 'The backup contains <b>participants, budgets, incomes, expenses, monthly closings, user accounts with password hashes, passkeys, invitations, system settings and personal dashboard layouts</b>. Restoration replaces all corresponding data. Checksums detect damaged or modified datasets before import.',
        'users' => 'After a complete restoration, use the accounts and passwords from the backup. Existing sessions and password-reset links are discarded. The ZIP contains sensitive account data: keep it safe and private.',
    ],
    'scope' => [
        'limits' => 'The backup restores application data in a compatible SplitNest installation. Configure server settings and credentials from .env, such as database and SMTP connections, for the target installation. Sessions, caches, queues and temporary uploads are not included. Passkeys remain bound to their original domain; after a domain change, sign in with a password and register passkeys again.',
        'legacy' => 'Backups from older versions remain importable but contain only the household data saved at that time. Missing accounts and settings cannot be recovered from them; existing ones are preserved.',
    ],
    'errors' => [
        'export_failed' => 'The complete backup could not be created. Check available space and write permissions.',
        'unsupported_format' => 'This is not a supported SplitNest backup, or its manifest is damaged.',
        'checksum' => 'The dataset “:table” is damaged or was modified after export.',
        'restore_failed' => 'The backup is incompatible with the current database schema or contains invalid data. Existing data was not replaced.',
        'no_admin' => 'This backup contains user accounts but no administrator. Restoration was stopped to protect access.',

        'not_zip' => 'The file is not a readable ZIP archive.',
        'missing_table' => 'The file is missing the dataset ":table" or it is damaged.',
        'invalid_rows' => 'The dataset ":table" contains invalid rows.',
    ],
];
