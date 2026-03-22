<?php

namespace Database\Factories;

use App\Models\Task;
use App\Models\TimeEntry;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TimeEntry>
 */
class TimeEntryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startedAt = fake()->dateTimeBetween('-14 days', '-1 hour');
        $endedAt = (clone $startedAt)->modify('+'.fake()->numberBetween(30, 240).' minutes');

        return [
            'task_id' => Task::factory()->hourly(),
            'project_id' => fn (array $attributes): ?int => Task::query()->find($attributes['task_id'])?->project_id,
            'owner_id' => fn (array $attributes): ?int => Task::query()->find($attributes['task_id'])?->owner_id,
            'description' => fake()->optional(0.6)->sentence(),
            'is_billable_override' => fake()->optional(0.2)->boolean(),
            'hourly_rate_override' => fake()->optional(0.15)->randomFloat(2, 500, 4000),
            'started_at' => $startedAt,
            'ended_at' => $endedAt,
            'minutes' => max(1, (int) ceil((strtotime($endedAt->format('c')) - strtotime($startedAt->format('c'))) / 60)),
            'locked_at' => null,
            'meta' => [
                'source' => 'factory',
            ],
        ];
    }
}
