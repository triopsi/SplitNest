<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Protect the schema lifecycle used by clean installations and rollbacks.
 */
class MigrationRollbackTest extends TestCase
{
    use DatabaseMigrations;

    /**
     * Roll back the schema and recreate it without leaving passkey foreign keys behind.
     */
    public function test_schema_can_be_rolled_back_and_recreated_with_passkeys(): void
    {
        $this->assertTrue(Schema::hasTable('passkeys'));

        $this->artisan('migrate:reset', ['--force' => true, '--no-interaction' => true])
            ->assertSuccessful();

        $this->assertFalse(Schema::hasTable('passkeys'));
        $this->assertFalse(Schema::hasTable('users'));

        $this->artisan('migrate', ['--force' => true, '--no-interaction' => true])
            ->assertSuccessful();

        $this->assertTrue(Schema::hasTable('users'));
        $this->assertTrue(Schema::hasTable('passkeys'));
    }
}
