<?php

namespace Database\Factories;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => strtolower(fake()->unique()->safeEmail()),
            'role' => Role::Student,
            'okta_nameid' => fake()->uuid(),
            'redcap_record_id' => null,
            'last_login_at' => null,
        ];
    }

    public function service(): static
    {
        return $this->state(fn (array $attributes) => ['role' => Role::Service]);
    }

    public function admin(): static
    {
        return $this->state(fn (array $attributes) => ['role' => Role::Admin]);
    }

    public function student(): static
    {
        return $this->state(fn (array $attributes) => ['role' => Role::Student]);
    }

    public function withRedcapRecord(string $recordId): static
    {
        return $this->state(fn (array $attributes) => ['redcap_record_id' => $recordId]);
    }
}
