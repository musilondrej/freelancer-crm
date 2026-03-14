<?php

namespace Database\Factories;

use App\Models\ProjectActivityStatusOption;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ProjectActivityStatusOption>
 */
class ProjectActivityStatusOptionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $label = fake()->unique()->words(2, true);
        $isDone = fake()->boolean(20);
        $isCancelled = ! $isDone && fake()->boolean(15);
        $isOpen = ! $isDone && ! $isCancelled && fake()->boolean(80);

        return [
            'owner_id' => User::factory(),
            'code' => Str::slug($label, '_'),
            'label' => Str::title($label),
            'color' => fake()->randomElement(['gray', 'primary', 'info', 'success', 'warning', 'danger']),
            'icon' => null,
            'sort_order' => fake()->numberBetween(1, 100),
            'is_default' => false,
            'is_open' => $isOpen,
            'is_done' => $isDone,
            'is_cancelled' => $isCancelled,
            'is_running' => false,
        ];
    }
}
