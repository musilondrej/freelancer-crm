<?php

namespace Database\Factories;

use App\Models\ProjectStatusOption;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ProjectStatusOption>
 */
class ProjectStatusOptionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $label = fake()->unique()->words(2, true);

        return [
            'owner_id' => User::factory(),
            'code' => Str::slug($label, '_'),
            'label' => Str::title($label),
            'color' => fake()->randomElement(['gray', 'primary', 'info', 'success', 'warning', 'danger']),
            'icon' => null,
            'sort_order' => fake()->numberBetween(1, 100),
            'is_default' => false,
            'is_open' => fake()->boolean(70),
            'is_trackable' => fake()->boolean(75),
        ];
    }
}
