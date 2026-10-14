<?php

namespace Database\Factories;

use App\Models\HouseholdInvitation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HouseholdInvitation>
 */
class HouseholdInvitationFactory extends Factory
{
    /**
     * Define the model's default state: a pending invitation expiring in 24 hours.
     *
     * The `token` column stores the SHA-256 hash of a random plain token.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'email' => fake()->unique()->safeEmail(),
            'token' => HouseholdInvitation::hashToken(HouseholdInvitation::generateToken()),
            'invited_by' => null,
            'expires_at' => now()->addDay(),
            'accepted_at' => null,
        ];
    }

    /**
     * State: an invitation whose validity has already run out.
     */
    public function expired(): static
    {
        return $this->state(['expires_at' => now()->subHour()]);
    }

    /**
     * State: an invitation that was already used to register an account.
     */
    public function accepted(): static
    {
        return $this->state(['accepted_at' => now()]);
    }
}
