<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Default seeder: intentionally empty, no default accounts or credentials.
 * Run the splitnest:user command to create a login.
 */
class DatabaseSeeder extends Seeder
{
    /**
     * Seed nothing; logins are created explicitly via artisan.
     */
    public function run(): void
    {
        // No default accounts or credentials. Run household:user to create a login.
    }
}
