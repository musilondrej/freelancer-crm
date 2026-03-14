<?php

namespace Database\Factories;

use App\Models\Tag;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Tag>
 */
class TagFactory extends Factory
{
    /**
     * @var list<string>
     */
    private const COLOR_PALETTE = [
        '#0F172A',
        '#1D4ED8',
        '#059669',
        '#D97706',
        '#DC2626',
        '#7C3AED',
        '#0EA5E9',
        '#4B5563',
    ];

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->randomElement([
            'VIP',
            'Urgent',
            'Long-term',
            'Maintenance',
            'Potential Upsell',
            'Paused',
            'International',
            'Enterprise',
            'SMB',
            'Invoice Delayed',
        ]);

        return [
            'owner_id' => User::factory(),
            'name' => $name,
            'slug' => Str::slug($name),
            'color' => fake()->randomElement(self::COLOR_PALETTE),
            'sort_order' => fake()->numberBetween(1, 50) * 10,
            'meta' => [
                'is_system' => false,
            ],
        ];
    }
}
