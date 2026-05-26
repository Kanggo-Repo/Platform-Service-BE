<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    public function definition(): array
    {
        return [
            'keycloak_subject' => 'kc-'.fake()->unique()->uuid(),
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'display_name' => fake()->name(),
            'status' => 'pending_access',
            'preferred_app' => null,
            'email_verified_at' => now(),
            'last_login_at' => now(),
        ];
    }
}
