<?php

namespace Database\Factories;

use App\Models\Activity;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Activity>
 */
class ActivityFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $project = fake()->optional(0.7)->randomElement(Project::query()->inRandomOrder()->limit(1)->get()) ?: null;

        return [
            'owner_id' => $project instanceof Project ? $project->owner_id : User::factory(),
            'project_id' => $project?->id,
            'name' => ucfirst(fake()->words(fake()->numberBetween(1, 3), true)),
            'description' => fake()->optional(0.6)->sentence(),
            'default_hourly_rate' => fake()->optional(0.7)->randomFloat(2, 500, 3500),
            'is_billable' => fake()->boolean(90),
            'is_active' => fake()->boolean(95),
            'sort_order' => fake()->numberBetween(0, 100),
            'meta' => [
                'seeded' => 'factory',
            ],
        ];
    }
}
