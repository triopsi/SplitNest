<?php

namespace Database\Seeders;

use App\Models\Budget;
use Illuminate\Database\Seeder;

/** Seed an optional example pool without changing existing household data. */
class BudgetSeeder extends Seeder
{
    /** Create a child-related pool when this seeder is explicitly selected. */
    public function run(): void
    {
        Budget::firstOrCreate(['name' => 'Kind']);
    }
}
