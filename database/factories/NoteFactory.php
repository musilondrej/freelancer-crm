<?php

namespace Database\Factories;

use App\Models\ClientContact;
use App\Models\Customer;
use App\Models\Lead;
use App\Models\Note;
use App\Models\Project;
use App\Models\RecurringService;
use App\Models\Task;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Note>
 */
class NoteFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'owner_id' => fn (array $attributes): ?int => match ($attributes['noteable_type']) {
                Customer::class => Customer::query()->find($attributes['noteable_id'])?->owner_id,
                ClientContact::class => ClientContact::query()->find($attributes['noteable_id'])?->owner_id,
                Project::class => Project::query()->find($attributes['noteable_id'])?->owner_id,
                Task::class => Task::query()->find($attributes['noteable_id'])?->owner_id,
                RecurringService::class => RecurringService::query()->find($attributes['noteable_id'])?->owner_id,
                Lead::class => Lead::query()->find($attributes['noteable_id'])?->owner_id,
                default => null,
            },
            'noteable_type' => Customer::class,
            'noteable_id' => Customer::factory(),
            'body' => fake()->paragraphs(asText: true),
            'is_pinned' => fake()->boolean(10),
            'noted_at' => fake()->optional(0.7)->dateTimeBetween('-3 months', 'now'),
            'meta' => [
                'source' => fake()->randomElement(['call', 'meeting', 'email', 'chat']),
            ],
        ];
    }

    public function pinned(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_pinned' => true,
        ]);
    }
}
