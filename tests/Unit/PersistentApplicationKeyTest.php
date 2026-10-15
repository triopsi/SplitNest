<?php

namespace Tests\Unit;

use App\Support\PersistentApplicationKey;
use Illuminate\Encryption\Encrypter;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class PersistentApplicationKeyTest extends TestCase
{
    private string $path;

    protected function setUp(): void
    {
        parent::setUp();

        $this->path = sys_get_temp_dir().'/splitnest-key-'.bin2hex(random_bytes(8));
    }

    protected function tearDown(): void
    {
        if (file_exists($this->path)) {
            unlink($this->path);
        }

        parent::tearDown();
    }

    /**
     * Create a key, encrypt data and verify it remains decryptable after startup.
     */
    public function test_generated_key_remains_usable_after_restart(): void
    {
        $key = PersistentApplicationKey::getOrCreate($this->path);
        $encrypter = new Encrypter(base64_decode(substr($key, 7)), 'AES-256-CBC');
        $encrypted = $encrypter->encryptString('household data');

        $storedKey = PersistentApplicationKey::getOrCreate($this->path);
        $afterRestart = new Encrypter(base64_decode(substr($storedKey, 7)), 'AES-256-CBC');

        $this->assertSame($key, $storedKey);
        $this->assertSame('household data', $afterRestart->decryptString($encrypted));
        $this->assertSame(0600, fileperms($this->path) & 0777);
    }

    /**
     * Reject a damaged key without silently replacing it and losing access to data.
     */
    public function test_corrupt_key_is_rejected_and_preserved(): void
    {
        file_put_contents($this->path, 'damaged-key');

        try {
            PersistentApplicationKey::getOrCreate($this->path);
            $this->fail('A corrupt application key must prevent startup.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('Restore it from your backup', $exception->getMessage());
        }

        $this->assertSame('damaged-key', file_get_contents($this->path));
    }
}
