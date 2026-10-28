<?php

namespace Database\Factories;

use App\Models\Budget;
use Illuminate\Database\Eloquent\Factories\Factory;

/** Create earmarked pools for household fixtures.
 * @extends Factory<Budget>
 */
class BudgetFactory extends Factory
{
    /** Provide a descriptive default name.
     * @return array{name: string}
     */
    public function definition(): array
    {
        return ['name' => fake()->words(2, true)];
    }
}
