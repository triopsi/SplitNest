<?php

use App\Support\PersistentApplicationKey;

$root = dirname(__DIR__);

require $root.'/vendor/autoload.php';

if (getenv('AUTO_MIGRATE') === 'true' && (getenv('DB_CONNECTION') ?: 'sqlite') === 'sqlite') {
    $database = getenv('DB_DATABASE') ?: $root.'/database/database.sqlite';

    if ($database !== ':memory:' && ! file_exists($database) && ! touch($database)) {
        throw new RuntimeException('Cannot create the SQLite database. Check the database path and storage permissions.');
    }
}

echo getenv('APP_KEY') ?: PersistentApplicationKey::getOrCreate($root.'/storage/app.key');
