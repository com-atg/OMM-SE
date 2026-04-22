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
        $graduationYear = fake()->numberBetween(2027, 2034);
        $academicYearStart = $graduationYear - 3;

        return [
            'academic_year' => $academicYearStart.'-'.($academicYearStart + 1),
            'graduation_year' => $graduationYear,
            'redcap_pid' => fake()->unique()->numberBetween(1000, 9999),
            'redcap_token' => strtoupper(fake()->regexify('[A-F0-9]{32}')),
        ];
    }
}
