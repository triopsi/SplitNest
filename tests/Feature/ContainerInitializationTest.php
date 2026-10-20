<?php

namespace Tests\Feature;

use Illuminate\Filesystem\Filesystem;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class ContainerInitializationTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        parent::setUp();

        $this->root = sys_get_temp_dir().'/splitnest-init-'.bin2hex(random_bytes(8));
        mkdir($this->root.'/.docker', 0700, true);
        mkdir($this->root.'/storage', 0700);
        copy(base_path('.docker/initialize.php'), $this->root.'/.docker/initialize.php');
        symlink(base_path('vendor'), $this->root.'/vendor');
    }

    protected function tearDown(): void
    {
        (new Filesystem)->deleteDirectory($this->root);

        parent::tearDown();
    }

    /**
     * Initialize a fresh SQLite installation twice and retain its database and key.
     */
    public function test_fresh_installation_creates_database_and_reuses_key(): void
    {
        $first = $this->initialize();
        file_put_contents($this->root.'/storage/database.sqlite', 'existing database');

        $second = $this->initialize();

        $this->assertSame(0, $first->getExitCode(), $first->getErrorOutput());
        $this->assertSame(0, $second->getExitCode(), $second->getErrorOutput());
        $this->assertStringStartsWith('base64:', $first->getOutput());
        $this->assertSame($first->getOutput(), $second->getOutput());
        $this->assertSame('existing database', file_get_contents($this->root.'/storage/database.sqlite'));
    }

    /**
     * Preserve an explicitly supplied key without generating a replacement.
     */
    public function test_explicit_application_key_takes_priority_over_stored_key(): void
    {
        $key = 'base64:'.base64_encode(str_repeat('a', 32));
        file_put_contents($this->root.'/storage/app.key', 'previous stored key');

        $process = $this->initialize(['APP_KEY' => $key]);

        $this->assertSame(0, $process->getExitCode(), $process->getErrorOutput());
        $this->assertSame($key, $process->getOutput());
        $this->assertSame('previous stored key', file_get_contents($this->root.'/storage/app.key'));
    }

    /**
     * Leave externally managed databases alone when starting a MariaDB installation.
     */
    public function test_external_database_does_not_create_sqlite_file(): void
    {
        $process = $this->initialize(['DB_CONNECTION' => 'mariadb']);

        $this->assertSame(0, $process->getExitCode(), $process->getErrorOutput());
        $this->assertFileDoesNotExist($this->root.'/storage/database.sqlite');
    }

    /**
     * Keep database initialization opt-in for existing installations.
     */
    public function test_disabled_auto_migration_does_not_create_database(): void
    {
        $process = $this->initialize(['AUTO_MIGRATE' => 'false']);

        $this->assertSame(0, $process->getExitCode(), $process->getErrorOutput());
        $this->assertFileDoesNotExist($this->root.'/storage/database.sqlite');
    }

    /**
     * Allow console commands started with docker exec to read the persisted key.
     */
    public function test_console_configuration_reads_key_without_entrypoint_environment(): void
    {
        $key = 'base64:'.base64_encode(str_repeat('b', 32));
        file_put_contents($this->root.'/storage/app.key', $key);
        $process = new Process([
            PHP_BINARY,
            '-r',
            'require $argv[1]."/vendor/autoload.php"; $app = require $argv[1]."/bootstrap/app.php"; $app->useStoragePath($argv[2]); $config = require $argv[1]."/config/app.php"; echo $config["key"];',
            base_path(),
            $this->root.'/storage',
        ], null, ['APP_KEY' => '']);

        $process->run();

        $this->assertSame(0, $process->getExitCode(), $process->getErrorOutput());
        $this->assertSame($key, $process->getOutput());
    }

    /**
     * Default to immediate job delivery while preserving explicit queue backends.
     *
     * Read configuration in a separate process so PHPUnit's sync override cannot hide regressions.
     */
    public function test_queue_defaults_to_sync_and_respects_explicit_backend(): void
    {
        foreach ([[false, 'sync'], ['database', 'database']] as [$connection, $expected]) {
            $process = new Process([
                PHP_BINARY,
                '-r',
                'require $argv[1]."/vendor/autoload.php"; $config = require $argv[1]."/config/queue.php"; echo $config["default"];',
                base_path(),
            ], null, ['QUEUE_CONNECTION' => $connection]);

            $process->run();

            $this->assertSame(0, $process->getExitCode(), $process->getErrorOutput());
            $this->assertSame($expected, $process->getOutput());
        }
    }

    /**
     * Run the real startup initializer against an isolated installation directory.
     *
     * @param  array<string, string|false>  $environment
     */
    private function initialize(array $environment = []): Process
    {
        $process = new Process([PHP_BINARY, $this->root.'/.docker/initialize.php'], $this->root, array_merge([
            'APP_KEY' => false,
            'AUTO_MIGRATE' => 'true',
            'DB_CONNECTION' => 'sqlite',
            'DB_DATABASE' => $this->root.'/storage/database.sqlite',
        ], $environment));
        $process->run();

        return $process;
    }
}
