<?php

namespace Database\Factories;

use App\Models\ProjectMapping;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProjectMapping>
 */
class ProjectMappingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $academicYearStart = fake()->numberBetween(2024, 2031);

        return [
            'academic_year' => $academicYearStart.'-'.($academicYearStart + 1),
            'redcap_pid' => fake()->unique()->numberBetween(1000, 9999),
            'redcap_token' => strtoupper(fake()->regexify('[A-F0-9]{32}')),
            'is_active' => false,
        ];
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes) => ['is_active' => true]);
    }
}
