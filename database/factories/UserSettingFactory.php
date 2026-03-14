<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\UserSetting;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserSetting>
 */
class UserSettingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'preferences' => [
                'time_tracking' => [
                    'rounding' => [
                        'enabled' => true,
                        'mode' => fake()->randomElement(['ceil', 'nearest', 'floor']),
                        'interval_minutes' => fake()->randomElement([1, 5, 10, 15, 30, 60]),
                        'minimum_minutes' => fake()->numberBetween(0, 15),
                    ],
                ],
                'ui' => [
                    'locale' => fake()->randomElement(['cs', 'en']),
                    'timezone' => fake()->randomElement(['Europe/Prague', 'UTC']),
                    'week_starts_on' => fake()->randomElement(['monday', 'sunday']),
                ],
            ],
        ];
    }
}
